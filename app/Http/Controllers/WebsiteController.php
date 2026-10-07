<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\AccountService;
use App\Services\DeliveryChargeService;
use App\Services\OnlineOrderTrackingService;
use App\Services\StockService;
use App\Services\WebsiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WebsiteController extends Controller
{
    public function __construct(
        private WebsiteService $website,
        private AccountService $accounts,
        private StockService $stock,
        private OnlineOrderTrackingService $tracking,
        private DeliveryChargeService $delivery,
    ) {}

    public function home()
    {
        return view('website.home', $this->website->homepageData());
    }

    public function shop(Request $request)
    {
        $shopId = $this->website->shopId();
        abort_unless($shopId, 404);

        $query = $this->website->catalogQuery($shopId)->with(['category', 'brand']);

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->whereSlugOrId($request->category));
        }

        $this->applyShopFilters($query, $request);

        if ($request->filter === 'deals') {
            $query->onSale();
        } elseif ($request->filter === 'new') {
            $query->newArrivals();
        } elseif (in_array($request->filter, ['bestsellers', 'best'], true)) {
            $query->trending()->orderByDesc('review_count');
        }

        $sort = $request->query('sort', 'featured');
        match ($sort) {
            'price_asc' => $query->orderBy('selling_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('selling_price')->orderBy('id'),
            'name' => $query->orderBy('name')->orderBy('id'),
            'latest' => $query->latest('id'),
            'bestsellers' => $query->orderByDesc('is_best_seller')->orderByDesc('review_count')->latest('id'),
            default => $request->filter === 'new'
                ? $query->latest('id')
                : $query->orderByDesc('is_best_seller')->orderByDesc('review_count')->latest('id'),
        };

        $this->website->applyVariantGroupListing($query);
        $products = $query->paginate(12)->withQueryString();

        $pageTitle = match ($request->filter) {
            'deals' => 'Deals',
            'new' => 'New Arrivals',
            'bestsellers', 'best' => 'Best Sellers',
            default => 'Shop',
        };

        if ($request->boolean('ajax') || $request->ajax()) {
            return $this->listingJson($request, $products, $pageTitle);
        }

        $sidebar = $this->shopSidebarData($shopId);

        return view('website.shop', array_merge($this->website->homepageData(), $sidebar, compact(
            'products',
            'pageTitle',
            'sort',
        )));
    }

    /** Brand, search, price, storage and RAM filters from the shop sidebar. */
    protected function applyShopFilters($query, Request $request): void
    {
        $brandIds = array_values(array_filter(array_map('intval', (array) $request->input('brands', []))));
        if ($brandIds) {
            $query->whereIn('brand_id', $brandIds);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $like = Schema::getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', $like, "%{$search}%")
                    ->orWhere('brand_name', $like, "%{$search}%")
                    ->orWhere('sku', $like, "%{$search}%")
                    ->orWhere('barcode', $like, "%{$search}%")
                    ->orWhere('short_description', $like, "%{$search}%")
                    ->orWhereHas('brand', fn ($brand) => $brand->where('name', $like, "%{$search}%"))
                    ->orWhereHas('category', fn ($category) => $category->where('name', $like, "%{$search}%"));
            });
        }

        if ($request->filled('min_price')) {
            $query->where('selling_price', '>=', (float) $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('selling_price', '<=', (float) $request->max_price);
        }

        $storages = array_values(array_filter(array_map('strval', (array) $request->input('storage', []))));
        if ($storages !== []) {
            $query->where(function ($q) use ($storages) {
                foreach ($storages as $value) {
                    $compact = memory_size_compact($value);
                    if ($compact === '') {
                        continue;
                    }
                    $q->orWhereRaw("LOWER(REPLACE(COALESCE(storage, ''), ' ', '')) = ?", [$compact]);
                }
            });
        }

        $rams = array_values(array_filter(array_map('strval', (array) $request->input('ram', []))));
        if ($rams !== []) {
            $query->where(function ($q) use ($rams) {
                foreach ($rams as $value) {
                    $compact = memory_size_compact($value);
                    if ($compact === '') {
                        continue;
                    }
                    $q->orWhereRaw("LOWER(REPLACE(COALESCE(ram, ''), ' ', '')) = ?", [$compact]);
                }
            });
        }
    }

    /** Sorting for category and brand listings (default: latest). */
    protected function applyListingSort($query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('selling_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('selling_price')->orderBy('id'),
            'name' => $query->orderBy('name')->orderBy('id'),
            'featured', 'bestsellers' => $query->orderByDesc('is_best_seller')->orderByDesc('review_count')->latest('id'),
            default => $query->latest()->orderByDesc('id'),
        };
    }

    /** JSON payload used by the live shop listing (pagination, sort, filters). */
    protected function listingJson(Request $request, $products, string $pageTitle)
    {
        $products->appends('ajax', null);
        $countFrom = $products->firstItem() ?? 0;
        $countTo = $products->lastItem() ?? 0;
        $countTotal = $products->total();
        $settings = $this->website->settings();

        return response()->json([
            'html' => view('website.partials.shop-results', compact('products', 'settings'))->render(),
            'count_text' => "Showing {$countFrom}–{$countTo} of ".format_taka_number($countTotal).' products',
            'title' => $pageTitle,
            'url' => $request->fullUrlWithoutQuery(['ajax']),
        ]);
    }

    public function searchSuggest(Request $request)
    {
        $shopId = $this->website->shopId();
        if (! $shopId) {
            return response()->json(['products' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));
        $limit = min(10, max(1, (int) $request->query('limit', 8)));
        $like = Schema::getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = $this->website->catalogQuery($shopId)->with(['category', 'brand']);

        if ($category !== '') {
            $query->whereHas('category', fn ($builder) => $builder->whereSlugOrId($category));
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q, $like) {
                $builder->where('name', $like, "%{$q}%")
                    ->orWhere('brand_name', $like, "%{$q}%")
                    ->orWhere('sku', $like, "%{$q}%")
                    ->orWhere('barcode', $like, "%{$q}%")
                    ->orWhere('short_description', $like, "%{$q}%")
                    ->orWhereHas('brand', fn ($brand) => $brand->where('name', $like, "%{$q}%"))
                    ->orWhereHas('category', fn ($category) => $category->where('name', $like, "%{$q}%"));
            })->orderBy('name');
        } else {
            $query->orderByDesc('is_best_seller')
                ->orderByDesc('review_count')
                ->latest();
        }

        $products = $this->website->dedupeVariantCollection($query->limit($limit * 3)->get(), $limit)
            ->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->storefrontDisplayName(),
            'brand' => $product->brand?->name ?? $product->brand_name,
            'price' => $product->currentPrice(),
            'image' => $this->website->productImageUrl($product),
            'url' => route('website.product', $product),
            'in_stock' => $product->availableStock() > 0,
        ]);

        return response()->json([
            'products' => $products,
            'mode' => $q === '' ? 'best' : 'search',
        ]);
    }

    public function category(string $slug, Request $request)
    {
        $shopId = $this->website->shopId();
        abort_unless($shopId, 404);

        $category = Category::where('shop_id', $shopId)
            ->whereSlugOrId($slug)
            ->firstOrFail();

        if (blank($category->slug)) {
            $category->save();
        }

        $filterConfig = \App\Support\CategoryFilterConfig::for($category);
        $showSidebar = (bool) ($filterConfig['enabled'] ?? false);

        // Category pages can show out-of-stock when filters are on (availability facet)
        $query = Product::query()
            ->where('shop_id', $shopId)
            ->where('category_id', $category->id)
            ->where(function ($q) {
                $q->where('is_published', true)->orWhereNull('is_published');
            })
            ->with(['category', 'brand']);

        if (! $showSidebar) {
            $query->availableForSale();
        }

        if ($showSidebar) {
            $this->applyCategoryFilters($query, $request, $filterConfig, $category);
        } else {
            $this->applyShopFilters($query, $request);
        }

        $sort = (string) $request->query('sort', 'latest');
        $this->applyListingSort($query, $sort);

        $this->website->applyVariantGroupListing($query);
        $products = $query->paginate(12)->withQueryString();

        if ($request->boolean('ajax') || $request->ajax()) {
            return $this->listingJson($request, $products, $category->name);
        }

        $sidebarFacets = $showSidebar
            ? $this->buildSidebarFacets($category, $filterConfig)
            : [];

        $priceBoundsQuery = Product::query()
            ->where('shop_id', $shopId)
            ->where('category_id', $category->id)
            ->where(fn ($q) => $q->where('is_published', true)->orWhereNull('is_published'));

        $sidebar = $this->shopSidebarData($shopId);
        // Keep price slider scoped to this category's range.
        $sidebar['priceBounds'] = [
            'min' => 0,
            'max' => (float) ((clone $priceBoundsQuery)->max('selling_price') ?? 0),
        ];

        return view('website.shop', array_merge($this->website->homepageData(), $sidebar, [
            'products' => $products,
            'activeCategory' => $category,
            'pageTitle' => $category->name,
            'pageSubtitle' => $category->description ?: 'Browse all products in this category.',
            'showSidebar' => $showSidebar,
            'filterConfig' => $filterConfig,
            'sidebarFacets' => $sidebarFacets,
            'sort' => $sort,
        ]));
    }

    /**
     * Shared category / brand / price data for the shop listing sidebar.
     */
    protected function shopSidebarData(int $shopId): array
    {
        $this->website->linkOrphanProductsToBrands($shopId);
        $this->website->mergeDuplicateBrands($shopId);

        $catalog = $this->website->catalogQuery($shopId);

        $categories = Category::where('shop_id', $shopId)
            ->orderBy('name')
            ->withCount(['products as published_count' => function ($q) use ($shopId) {
                $q->where('shop_id', $shopId)
                    ->availableForSale()
                    ->where(function ($qq) {
                        $qq->where('is_published', true)->orWhereNull('is_published');
                    });
            }])
            ->get();

        $visibleBrandProducts = function ($q) use ($shopId) {
            $q->where('shop_id', $shopId)
                ->availableForSale()
                ->where(function ($qq) {
                    $qq->where('is_published', true)->orWhereNull('is_published');
                });
        };

        $brands = Brand::where('shop_id', $shopId)
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->orderBy('name')
            ->withCount([
                'products as published_count' => $visibleBrandProducts,
                'products as products_count' => $visibleBrandProducts,
            ])
            ->get()
            ->filter(fn (Brand $b) => (int) ($b->products_count ?? $b->published_count ?? 0) > 0)
            ->values();

        $storageOptions = unique_memory_sizes(
            (clone $catalog)
                ->whereNotNull('storage')
                ->where('storage', '!=', '')
                ->distinct()
                ->pluck('storage')
        );

        $ramOptions = unique_memory_sizes(
            (clone $catalog)
                ->whereNotNull('ram')
                ->where('ram', '!=', '')
                ->distinct()
                ->pluck('ram')
        );

        return [
            'categories' => $categories,
            'brands' => $brands,
            'storageOptions' => $storageOptions,
            'ramOptions' => $ramOptions,
            'categoryTotal' => (clone $catalog)->count(),
            'priceBounds' => [
                'min' => 0,
                'max' => (float) ((clone $catalog)->max('selling_price') ?? 0),
            ],
        ];
    }

    protected function applyCategoryFilters($query, Request $request, array $filterConfig, Category $category): void
    {
        if (! empty($filterConfig['price_enabled'])) {
            if ($request->filled('min_price')) {
                $query->where('selling_price', '>=', (float) $request->min_price);
            }
            if ($request->filled('max_price')) {
                $query->where('selling_price', '<=', (float) $request->max_price);
            }
        }

        foreach ($filterConfig['groups'] ?? [] as $group) {
            if (empty($group['enabled'])) {
                continue;
            }

            $key = $group['key'] ?? '';
            $selected = array_filter((array) $request->query($key, []));
            if ($selected === [] || $key === '') {
                continue;
            }

            $type = $group['type'] ?? 'custom';

            if ($type === 'availability') {
                $query->where(function ($q) use ($selected) {
                    foreach ($selected as $value) {
                        $q->orWhere(function ($inner) use ($value) {
                            if ($value === 'in_stock') {
                                $inner->whereRaw('stock_quantity > COALESCE(reserved_stock, 0)')
                                    ->where(function ($a) {
                                        $a->whereNull('availability')
                                            ->orWhere('availability', 'in_stock');
                                    });
                            } elseif ($value === 'out_of_stock') {
                                $inner->whereRaw('stock_quantity <= COALESCE(reserved_stock, 0)')
                                    ->where(function ($a) {
                                        $a->whereNull('availability')
                                            ->orWhere('availability', 'out_of_stock')
                                            ->orWhere('availability', 'in_stock');
                                    });
                            } else {
                                $inner->where('availability', $value);
                            }
                        });
                    }
                });
                continue;
            }

            if ($type === 'brand') {
                $query->where(function ($q) use ($selected, $category) {
                    $brands = Brand::where('shop_id', $category->shop_id)->get();
                    foreach ($selected as $value) {
                        $match = $brands->first(fn ($b) => \Illuminate\Support\Str::slug($b->name, '_') === $value
                            || strtolower($b->name) === str_replace('_', ' ', strtolower($value)));
                        if ($match) {
                            $q->orWhere('brand_id', $match->id)->orWhere('brand_name', $match->name);
                        } else {
                            $label = str_replace('_', ' ', $value);
                            $q->orWhereRaw('LOWER(COALESCE(brand_name, \'\')) = ?', [strtolower($label)]);
                        }
                    }
                });
                continue;
            }

            if ($type === 'storage') {
                $query->where(function ($q) use ($selected) {
                    foreach ($selected as $value) {
                        $compact = memory_size_compact(str_replace('_', ' ', (string) $value));
                        if ($compact === '') {
                            continue;
                        }
                        $q->orWhereRaw("LOWER(REPLACE(COALESCE(storage, ''), ' ', '')) = ?", [$compact]);
                    }
                });
                continue;
            }

            if ($type === 'ram') {
                $query->where(function ($q) use ($selected) {
                    foreach ($selected as $value) {
                        $compact = memory_size_compact(str_replace('_', ' ', (string) $value));
                        if ($compact === '') {
                            continue;
                        }
                        $q->orWhereRaw("LOWER(REPLACE(COALESCE(ram, ''), ' ', '')) = ?", [$compact]);
                    }
                });
                continue;
            }

            if ($type === 'color') {
                $query->where(function ($q) use ($selected) {
                    foreach ($selected as $value) {
                        $label = str_replace('_', ' ', $value);
                        $q->orWhereRaw('LOWER(REPLACE(COALESCE(color, \'\'), \' \', \'_\')) = ?', [strtolower($value)])
                            ->orWhereRaw('LOWER(color) = ?', [strtolower($label)]);
                    }
                });
                continue;
            }

            // Custom attributes JSON
            $query->where(function ($q) use ($selected, $key) {
                foreach ($selected as $value) {
                    $q->orWhere("filter_attributes->{$key}", $value)
                        ->orWhereJsonContains("filter_attributes->{$key}", $value);
                }
            });
        }
    }

    protected function buildSidebarFacets(Category $category, array $filterConfig): array
    {
        $facets = [];
        foreach ($filterConfig['groups'] ?? [] as $group) {
            if (empty($group['enabled'])) {
                continue;
            }

            $type = $group['type'] ?? 'custom';
            $options = $group['options'] ?? [];

            if (in_array($type, ['brand', 'storage', 'ram', 'color', 'custom'], true) && $options === []) {
                $options = \App\Support\CategoryFilterConfig::facetValues($category, $type, $group['key'] ?? '')
                    ->all();
            }

            if ($options === [] && $type !== 'availability') {
                continue;
            }

            $facets[] = [
                'key' => $group['key'],
                'label' => $group['label'],
                'type' => $type,
                'options' => $options,
            ];
        }

        return $facets;
    }

    public function brand(string $slug, Request $request)
    {
        $shopId = $this->website->shopId();
        abort_unless($shopId, 404);

        $brand = Brand::where('shop_id', $shopId)
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->withCount(['products' => function ($q) use ($shopId) {
                $q->where('shop_id', $shopId)
                    ->availableForSale()
                    ->where(function ($qq) {
                        $qq->where('is_published', true)->orWhereNull('is_published');
                    });
            }])
            ->get()
            ->filter(fn ($b) => \Illuminate\Support\Str::slug($b->name) === $slug)
            // Prefer the brand row that actually has products (handles samsung / Samsung duplicates).
            ->sortByDesc('products_count')
            ->first();

        abort_unless($brand, 404);

        $this->website->linkOrphanProductsToBrands($shopId);
        $this->website->mergeDuplicateBrands($shopId);

        // Re-resolve after merge in case the chosen row was absorbed.
        $brand = Brand::where('shop_id', $shopId)
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->get()
            ->filter(fn ($b) => \Illuminate\Support\Str::slug($b->name) === $slug)
            ->sortByDesc(fn ($b) => Product::where('brand_id', $b->id)->count())
            ->first() ?? $brand;

        $brandQuery = $this->website->catalogQuery($shopId)
            ->where(function ($q) use ($brand) {
                $q->where('brand_id', $brand->id)
                    ->orWhereRaw('LOWER(TRIM(COALESCE(brand_name, \'\'))) = ?', [strtolower(trim($brand->name))]);
            })
            ->with(['category', 'brand']);

        if ($request->filled('category')) {
            $brandQuery->whereHas('category', fn ($q) => $q->whereSlugOrId($request->category));
        }
        $this->applyShopFilters($brandQuery, $request->duplicate($request->except('brands')));

        $sort = (string) $request->query('sort', 'latest');
        $this->applyListingSort($brandQuery, $sort);

        $this->website->applyVariantGroupListing($brandQuery);
        $products = $brandQuery->paginate(12)->withQueryString();

        if ($request->boolean('ajax') || $request->ajax()) {
            return $this->listingJson($request, $products, $brand->name);
        }

        return view('website.shop', array_merge($this->website->homepageData(), $this->shopSidebarData($shopId), [
            'products' => $products,
            'activeBrand' => $brand,
            'pageTitle' => $brand->name,
            'pageSubtitle' => 'Shop all products from ' . $brand->name . '.',
            'sort' => $sort,
        ]));
    }

    public function product(Request $request, Product $product)
    {
        $shopId = $this->website->shopId();
        $published = $product->is_published !== false;
        abort_unless($shopId && $product->shop_id === $shopId && $published, 404);

        $product->loadMissing(['category', 'brand']);

        $relatedQuery = $this->website->catalogQuery($shopId)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id);
        if ($product->variant_group) {
            $relatedQuery->where(function ($q) use ($product) {
                $q->whereNull('variant_group')
                    ->orWhere('variant_group', '!=', $product->variant_group);
            });
        }
        $this->website->applyVariantGroupListing($relatedQuery);
        $related = $relatedQuery->take(4)->get();

        $variantOptions = $this->website->productVariantOptions($product);

        $reviews = \App\Models\CmsReview::where('shop_id', $shopId)
            ->where('is_published', true)
            ->where(function ($q) use ($product) {
                $q->where('product_id', $product->id)->orWhereNull('product_id');
            })
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->take(8)
            ->get();

        $home = $this->website->homepageData();
        $settings = $home['settings'] ?? $this->website->settings();
        $features = $home['features'] ?? collect();

        if ($request->boolean('ajax') || $request->ajax()) {
            $displayName = $product->storefrontDisplayName();

            $storeName = data_get($settings, 'store_name', 'Shop');

            return response()->json([
                'html' => view('website.partials.product-live', compact(
                    'product',
                    'related',
                    'reviews',
                    'variantOptions',
                    'settings',
                    'features'
                ))->render(),
                'url' => route('website.product', $product),
                'title' => $displayName.' | '.$storeName,
            ]);
        }

        return view('website.product', array_merge($home, compact('product', 'related', 'reviews', 'variantOptions')));
    }

    public function page(string $slug)
    {
        $page = $this->website->publishedPage($slug);
        abort_unless($page, 404);

        return view('website.cms-page', array_merge($this->website->homepageData(), compact('page')));
    }

    public function blogs(Request $request)
    {
        $data = $this->website->blogPageData(
            $request->query('q'),
            $request->query('category')
        );

        return view('website.blogs', $data);
    }

    public function blog(string $slug)
    {
        $blog = $this->website->publishedBlog($slug);
        abort_unless($blog, 404);

        $blog->increment('views_count');
        $blog->refresh();

        $related = \App\Models\CmsBlog::where('shop_id', $blog->shop_id)
            ->published()
            ->where('id', '!=', $blog->id)
            ->when($blog->category_id, fn ($q) => $q->where('category_id', $blog->category_id))
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('website.blog-show', array_merge($this->website->homepageData(), [
            'blog' => $blog,
            'relatedPosts' => $related,
            'popularPosts' => $this->website->popularBlogs(3),
            'blogCategories' => $this->website->blogCategories(),
        ]));
    }

    public function subscribeNewsletter(Request $request)
    {
        $request->validate(['email' => 'required|email|max:255']);

        $shopId = $this->website->shopId();
        abort_unless($shopId, 404);

        \App\Models\CmsNewsletterSubscriber::firstOrCreate(
            ['shop_id' => $shopId, 'email' => strtolower(trim($request->email))]
        );

        return back()->with('newsletter_success', 'Thanks for subscribing!');
    }

    public function faqs(Request $request)
    {
        return view('website.faqs', $this->website->faqPageData(
            $request->query('q'),
            $request->query('category')
        ));
    }

    public function contact()
    {
        return view('website.contact', $this->website->contactPageData());
    }

    public function submitContact(Request $request)
    {
        $shopId = $this->website->shopId();
        abort_unless($shopId, 404);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:200',
            'order_number' => 'nullable|string|max:80',
            'message' => 'required|string|max:5000',
        ]);

        \App\Models\CmsContactMessage::create([
            'shop_id' => $shopId,
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'order_number' => $data['order_number'] ?? null,
            'message' => $data['message'],
            'is_read' => false,
        ]);

        return back()->with('contact_success', 'Thanks! Your message has been sent. We\'ll get back to you soon.');
    }

    public function trackOrder(Request $request)
    {
        $invoice = trim((string) $request->query('invoice', ''));
        $phone = trim((string) $request->query('phone', ''));
        $tracking = null;
        $error = null;

        if ($invoice !== '' && $phone !== '') {
            $order = $this->findTrackableOrder($invoice, $phone);
            if ($order) {
                $tracking = $this->trackingView($order);
            } else {
                $error = 'No order found for that Order ID and phone number.';
            }
        }

        return view('website.track-order', array_merge($this->website->homepageData(), [
            'tracking' => $tracking,
            'trackError' => $error,
            'invoiceNo' => $invoice,
            'phone' => $phone,
        ]));
    }

    public function trackOrderLookup(Request $request)
    {
        $data = $request->validate([
            'invoice_no' => ['required', 'string', 'max:64'],
            'phone' => ['required', 'string', 'max:32'],
        ]);

        return redirect()->route('website.track', [
            'invoice' => strtoupper(trim($data['invoice_no'])),
            'phone' => trim($data['phone']),
        ]);
    }

    /** Forgotten Order ID: list the latest online orders placed with a mobile number. */
    public function findGuestOrders(Request $request)
    {
        $data = $request->validate([
            'find_phone' => ['required', 'string', 'max:32'],
        ], [
            'find_phone.required' => 'Enter the mobile number you ordered with.',
        ]);

        $shopId = $this->website->shopId();
        abort_unless($shopId, 404);

        $notFound = fn () => redirect()->route('website.track')
            ->withInput()
            ->with('findError', 'No orders found for this mobile number. Use the same number you entered at checkout.');

        $mobile = Customer::bdMobile($data['find_phone']);
        if ($mobile === '') {
            return redirect()->route('website.track')
                ->withInput()
                ->with('findError', 'Enter a valid mobile number (01XXXXXXXXX).');
        }

        $customerIds = Customer::where('shop_id', $shopId)->wherePhone($mobile)->pluck('id');

        if ($customerIds->isEmpty()) {
            return $notFound();
        }

        $orders = Order::where('shop_id', $shopId)
            ->onlineOrders()
            ->whereIn('customer_id', $customerIds)
            ->latest('id')
            ->limit(10)
            ->get(['id', 'invoice_no', 'status', 'total_amount', 'created_at']);

        if ($orders->isEmpty()) {
            return $notFound();
        }

        $labels = $this->tracking->statusLabels();

        return redirect()->route('website.track')
            ->withInput()
            ->with('foundOrders', $orders->map(fn (Order $o) => [
                'invoice' => $o->invoice_no,
                'status' => $labels[$o->status] ?? ucfirst(str_replace('_', ' ', (string) $o->status)),
                'total' => (float) $o->total_amount,
                'date' => asian_datetime($o->created_at, 'd M Y'),
                'url' => route('website.track', ['invoice' => $o->invoice_no, 'phone' => $mobile]),
            ])->all());
    }

    /** Online order matching both the Order ID and the phone used at checkout. */
    private function findTrackableOrder(string $invoice, string $phone): ?Order
    {
        $shopId = $this->website->shopId();
        abort_unless($shopId, 404);

        $order = Order::where('shop_id', $shopId)
            ->onlineOrders()
            ->where('invoice_no', strtoupper(trim($invoice)))
            ->with(['customer', 'items.product', 'statusLogs'])
            ->first();

        if (! $order || ! $order->customer || ! Customer::samePhone($order->customer->phone, $phone)) {
            return null;
        }

        return $order;
    }

    private function trackingView(Order $order): array
    {
        $total = (float) $order->total_amount;
        $paid = (float) ($order->paid_amount ?? 0);

        return array_merge($this->tracking->trackingPayload($order), [
            'total_amount' => $total,
            'delivery_charge' => (float) ($order->delivery_charge ?? 0),
            'paid_amount' => $paid,
            'due_amount' => max(0, $total - $paid),
            'payment_label' => $order->payment_method === DeliveryChargeService::PAY_CONFIRMATION
                ? 'Confirmation charge + balance on delivery'
                : 'Cash on delivery',
        ]);
    }

    public function wishlist()
    {
        return view('website.wishlist', $this->website->homepageData());
    }

    /**
     * Re-price and stock-cap the storefront cart from the live catalog.
     * Client may only send product ids + quantities — never trusted prices.
     */
    public function syncCart(Request $request)
    {
        $shopId = $this->website->shopId();
        if (! $shopId) {
            return response()->json(['items' => [], 'subtotal' => 0, 'warnings' => ['Store unavailable.']], 404);
        }

        $rawItems = collect((array) $request->input('items', $request->input('cart', [])));
        $requested = $rawItems
            ->map(function ($item) {
                return [
                    'id' => (int) (is_array($item) ? ($item['id'] ?? 0) : 0),
                    'qty' => max(1, (int) (is_array($item) ? ($item['qty'] ?? 1) : 1)),
                ];
            })
            ->filter(fn (array $item) => $item['id'] > 0)
            ->values();

        if ($requested->isEmpty()) {
            return response()->json(['items' => [], 'subtotal' => 0.0, 'warnings' => []]);
        }

        $products = Product::query()
            ->where('shop_id', $shopId)
            ->whereIn('id', $requested->pluck('id')->all())
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = 0.0;
        $warnings = [];

        foreach ($requested as $item) {
            $product = $products->get($item['id']);
            if (! $product || $product->is_published === false) {
                $warnings[] = 'A product was removed because it is no longer available.';
                continue;
            }

            $stock = max(0, (int) $product->availableStock());
            if ($stock < 1) {
                $warnings[] = $product->storefrontDisplayName().' is out of stock and was removed.';
                continue;
            }

            $qty = min($item['qty'], $stock);
            if ($qty < $item['qty']) {
                $warnings[] = $product->storefrontDisplayName().' quantity was limited to '.$stock.' available.';
            }

            $unitPrice = (float) $product->currentPrice();
            $lines[] = [
                'id' => $product->id,
                'name' => $product->cartDisplayName(),
                'price' => $unitPrice,
                'image' => $this->website->productImageUrl($product),
                'qty' => $qty,
                'stock' => $stock,
            ];
            $subtotal += $unitPrice * $qty;
        }

        return response()->json([
            'items' => $lines,
            'subtotal' => round($subtotal),
            'warnings' => array_values(array_unique($warnings)),
        ]);
    }

    public function checkout(Request $request)
    {
        $user = $request->user('web');
        $isMember = (bool) $user?->isStorefrontCustomer();
        // A staff session on the storefront checks out as a guest, never as the staff user.
        if (! $isMember) {
            $user = null;
        }

        $shopId = $this->website->shopId();
        if (! $shopId || empty($request->cart)) {
            return response()->json(['success' => false, 'message' => 'Cart is empty or store unavailable.']);
        }

        $request->validate([
            'customer_name' => 'required|string|min:2|max:255',
            'customer_phone' => 'required|string|min:8|max:20',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'required|string|min:5|max:1000',
            'delivery_zone' => 'nullable|string|in:inside_dhaka,outside_dhaka',
            'payment_method' => 'nullable|string|in:cash_on_delivery,confirmation_charge',
        ], [
            'customer_address.required' => 'Delivery address is required to place your order.',
            'customer_address.min' => 'Please enter a complete delivery address.',
            'customer_phone.min' => 'Enter a valid mobile number (01XXXXXXXXX).',
        ]);

        $deliveryAddress = trim(preg_replace('/\s+/u', ' ', (string) $request->customer_address) ?? '');
        $request->merge(['customer_address' => $deliveryAddress]);

        if ($isMember) {
            $customer = Customer::where('shop_id', $shopId)
                ->where('user_id', $user->id)
                ->first();

            if (! $customer) {
                $customer = Customer::create([
                    'shop_id' => $shopId,
                    'user_id' => $user->id,
                    'name' => $request->customer_name,
                    'email' => $user->email,
                    'phone' => $request->customer_phone,
                    'address' => $request->customer_address,
                ]);
            } else {
                $customer->update([
                    'name' => $request->customer_name,
                    'phone' => $request->customer_phone,
                    'address' => $request->customer_address,
                    'email' => $user->email,
                ]);
            }

            $user->update(['name' => $request->customer_name]);
        } else {
            $mobile = Customer::bdMobile($request->customer_phone);
            if ($mobile === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Enter a valid mobile number (01XXXXXXXXX).',
                    'errors' => ['customer_phone' => ['Enter a valid mobile number (01XXXXXXXXX).']],
                ], 422);
            }
            $request->merge(['customer_phone' => $mobile]);

            // Reuse the same CRM record for repeat guests / walk-in POS customers with this number.
            $customer = Customer::where('shop_id', $shopId)->wherePhone($mobile)->orderByRaw('user_id IS NULL DESC')->first();

            if (! $customer) {
                $customer = Customer::create([
                    'shop_id' => $shopId,
                    'user_id' => null,
                    'name' => $request->customer_name,
                    'email' => $request->customer_email,
                    'phone' => $mobile,
                    'address' => $request->customer_address,
                ]);
            } else {
                // Admin screens and receipts read the delivery address from the customer record.
                $updates = [
                    'name' => $request->customer_name,
                    'address' => $request->customer_address,
                ];
                if ($customer->user_id === null && $request->customer_email) {
                    $updates['email'] = $request->customer_email;
                }
                $customer->update($updates);
            }
        }

        $shopAdmin = \App\Models\User::where('shop_id', $shopId)->whereIn('role', ['admin', 'shop_owner', 'Shop Owner'])->first()
            ?? \App\Models\User::where('shop_id', $shopId)->whereNotIn('role', ['customer', 'Customer'])->orderBy('id')->first();
        $fallbackUserId = $shopAdmin?->id ?? $user?->id;
        if (! $fallbackUserId) {
            return response()->json(['success' => false, 'message' => 'Store is not ready to take orders yet. Please contact us.'], 503);
        }

        // Resolve cart against live catalog prices/stock (never trust client prices).
        $resolvedLines = [];
        $subtotal = 0.0;

        foreach ((array) $request->cart as $item) {
            $productId = (int) ($item['id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);
            if ($productId < 1 || $qty < 1) {
                return response()->json(['success' => false, 'message' => 'Invalid cart item.']);
            }

            $product = Product::where('shop_id', $shopId)->find($productId);
            if (! $product || $product->is_published === false) {
                return response()->json(['success' => false, 'message' => 'A product in your cart is no longer available.']);
            }
            if ($product->availableStock() < $qty) {
                return response()->json([
                    'success' => false,
                    'message' => "Not enough stock for {$product->name}. Only {$product->availableStock()} left.",
                ]);
            }

            $unitPrice = $product->currentPrice();
            $lineTotal = $unitPrice * $qty;
            $subtotal += $lineTotal;

            $resolvedLines[] = [
                'product' => $product,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $lineTotal,
            ];
        }

        if ($resolvedLines === []) {
            return response()->json(['success' => false, 'message' => 'Cart is empty or store unavailable.']);
        }

        $quote = $this->delivery->quote(
            $subtotal,
            $request->input('delivery_zone'),
            $request->input('payment_method'),
        );

        $deliveryFee = $quote['delivery_fee'];
        $finalTotal = $quote['grand_total'];
        $paidNow = $quote['amount_paid_now'];
        $confirmationCharge = $quote['confirmation_amount'];
        $paymentMethod = $quote['payment_method'];

        try {
            DB::beginTransaction();

            $invoiceNo = Order::nextWebInvoiceNo($shopId);
            if ($invoiceNo === '') {
                throw new \RuntimeException('Could not generate an order ID. Please try again.');
            }

            $order = Order::create([
                'shop_id' => $shopId,
                'user_id' => $fallbackUserId,
                'invoice_no' => $invoiceNo,
                'customer_id' => $customer->id,
                'total_amount' => $finalTotal,
                'delivery_charge' => $deliveryFee,
                'delivery_zone' => $quote['zone'],
                'confirmation_charge' => $confirmationCharge,
                'paid_amount' => $paidNow,
                'payment_method' => $paymentMethod,
                'status' => 'pending_fulfillment',
                'counter_id' => null,
            ]);

            if (! $order->id || blank($order->invoice_no)) {
                throw new \RuntimeException('Order was created without an order ID. Please try again.');
            }

            foreach ($resolvedLines as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'quantity' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $line['subtotal'],
                ]);
            }

            $order->load('items.product');
            // Hold inventory immediately so POS/website cannot oversell COD units.
            $this->stock->reserveWebOrderStock($order, $fallbackUserId);
            $this->accounts->postWebSale($order);
            $this->tracking->logInitialPlacement($order);

            DB::commit();

            $payNote = $paymentMethod === DeliveryChargeService::PAY_CONFIRMATION
                ? 'Confirmation charge ৳'.format_taka_number($paidNow).' · balance due on delivery ৳'.format_taka_number($quote['amount_due_later'])
                : 'Cash on delivery · total due ৳'.format_taka_number($finalTotal);

            return response()->json([
                'success' => true,
                'guest' => ! $isMember,
                'track_url' => route('website.track', ['invoice' => $order->invoice_no, 'phone' => $customer->phone]),
                'phone' => $customer->phone,
                'order_id' => $order->id,
                'invoice' => $order->invoice_no,
                'delivery_fee' => $deliveryFee,
                'grand_total' => $finalTotal,
                'payment_method' => $paymentMethod,
                'amount_paid_now' => $paidNow,
                'amount_due_later' => $quote['amount_due_later'],
                'message' => 'Order placed successfully. Your Order ID is '.$order->invoice_no.'. '.$payNote,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Order failed: '.$e->getMessage()]);
        }
    }
}
