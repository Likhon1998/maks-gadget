@php
    $ws = app(\App\Services\WebsiteService::class);
    $currentPrice = $product->currentPrice();
    $img = $ws->productImageUrl($product);
    $displayName = $product->storefrontDisplayName();
    $catLabel = strtoupper($product->category?->name ?? $product->brand_name ?? 'Gadgets');
    $rank = (int) ($trendingRank ?? 1);
    $rating = (float) ($product->rating ?? 0);
    $reviews = (int) ($product->review_count ?? 0);
    $reviewLabel = $reviews >= 1000
        ? rtrim(rtrim(number_format($reviews / 1000, 1), '0'), '.').'k'
        : number_format($reviews);
    $availableQty = max(0, (int) $product->availableStock());

    $cartItem = [
        'id' => $product->id,
        'name' => $displayName,
        'price' => $currentPrice,
        'image' => $img,
        'stock' => $availableQty,
    ];
@endphp

<article class="tn-trending-card">
    <span class="tn-trending-badge">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2s3.2 3.1 3.2 6.1c0 1.7-1 3.1-2.4 3.9.4-1.5.1-3.1-1-4.3C10.6 9.2 9 11 9 13.2 9 16.5 11.2 19 14 19c3.3 0 5.5-2.6 5.5-5.8C19.5 8.4 15.8 5.2 12 2zM8.8 20.2C6.2 18.8 5 16.4 5 13.8c0-2.1.9-4 2.3-5.4-.2 3.2 1.1 5.1 2.7 6.4-2 .7-3.4 2.5-3.4 4.6 0 .3 0 .6.1.9 1-.5 1.5-.7 2.1-.1z"/></svg>
        #{{ $rank }} Trending
    </span>

    <a href="{{ route('website.product', $product) }}" class="tn-trending-media" aria-label="{{ $displayName }}">
        <img src="{{ $img }}" alt="{{ $displayName }}" class="tn-trending-img" loading="lazy" decoding="async">
    </a>

    <div class="tn-trending-body">
        <p class="tn-trending-cat">{{ $catLabel }}</p>
        <a href="{{ route('website.product', $product) }}" class="tn-trending-name">{{ $displayName }}</a>

        <div class="tn-trending-rating" aria-label="Rating {{ number_format($rating, 1) }}">
            @for($i = 1; $i <= 5; $i++)
                <span class="tn-trending-star {{ $i > round($rating) ? 'is-empty' : '' }}">★</span>
            @endfor
            @if($rating > 0)
                <strong>{{ number_format($rating, 1) }}</strong>
            @endif
            @if($reviews > 0)
                <span>({{ $reviewLabel }})</span>
            @endif
        </div>

        <div class="tn-trending-foot">
            <span class="tn-trending-price">{{ $ws->formatPrice($currentPrice, $settings) }}</span>
            <button type="button"
                    class="tn-trending-cart"
                    title="Add to cart"
                    aria-label="Add {{ $displayName }} to cart"
                    data-add-to-cart='@json($cartItem)'
                    data-qty="1"
                    data-open-cart="1"
                    @if($availableQty < 1) disabled @endif>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l3-8H6.4M7 13L5.4 5M7 13l-2 6h12m-8 0a1 1 0 100 2 1 1 0 000-2zm8 0a1 1 0 100 2 1 1 0 000-2z"/>
                </svg>
            </button>
        </div>
    </div>
</article>
