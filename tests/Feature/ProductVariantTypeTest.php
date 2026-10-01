<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\SiteSetting;
use App\Services\WebsiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantTypeTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::create([
            'name' => 'Test Shop',
            'email' => 'shop@example.com',
            'phone' => '01700000000',
            'address' => 'Dhaka',
            'is_active' => true,
        ]);

        SiteSetting::create([
            'default_shop_id' => $this->shop->id,
            'store_name' => 'GAGET STORE',
            'currency_code' => 'BDT',
            'currency_symbol' => '৳',
        ]);
    }

    private function variant(string $barcode, string $color, string $type, float $price, int $stock = 5): Product
    {
        return Product::create([
            'shop_id' => $this->shop->id,
            'name' => '67W Fast Charging Adapter',
            'barcode' => $barcode,
            'variant_group' => 'somostel-67w',
            'color' => $color,
            'variant_type' => $type,
            'cost_price' => 500,
            'selling_price' => $price,
            'stock_quantity' => $stock,
            'is_published' => true,
        ]);
    }

    public function test_color_and_type_combine_into_separate_pickers(): void
    {
        $whiteCable = $this->variant('W-C', 'White', 'With cable', 1490);
        $whiteBare = $this->variant('W-N', 'White', 'Without cable', 1190);
        $blackCable = $this->variant('B-C', 'Black', 'With cable', 1490);

        $options = app(WebsiteService::class)->productVariantOptions($whiteBare);

        $this->assertSame(['White', 'Black'], array_column($options['colors'], 'label'));
        $this->assertSame(['With cable', 'Without cable'], array_column($options['types'], 'label'));

        $active = collect($options['types'])->firstWhere('active', true);
        $this->assertSame('Without cable', $active['label']);

        $cableChip = collect($options['types'])->firstWhere('label', 'With cable');
        $this->assertSame($whiteCable->id, $cableChip['product_id']);

        // Black only comes with a cable, so its type list is just that one chip.
        $blackOptions = app(WebsiteService::class)->productVariantOptions($blackCable);
        $this->assertSame(['With cable'], array_column($blackOptions['types'], 'label'));

        // Switching color keeps the selected type when that combination exists.
        $blackSwatch = collect($options['colors'])->firstWhere('label', 'Black');
        $this->assertSame($blackCable->id, $blackSwatch['product_id']);
        $whiteSwatch = collect(app(WebsiteService::class)->productVariantOptions($blackCable)['colors'])->firstWhere('label', 'White');
        $this->assertSame($whiteCable->id, $whiteSwatch['product_id']);
    }

    public function test_product_page_shows_type_picker_and_cart_name_includes_options(): void
    {
        $whiteCable = $this->variant('W-C', 'White', 'With cable', 1490);
        $this->variant('W-N', 'White', 'Without cable', 1190);

        $this->assertSame('67W Fast Charging Adapter (White · With cable)', $whiteCable->cartDisplayName());
        $this->assertContains(['label' => 'Type', 'value' => 'With cable'], $whiteCable->receiptSpecLines());

        $this->get(route('website.product', $whiteCable))
            ->assertOk()
            ->assertSee('pd-variant-label">Type</p>', false)
            ->assertSee('Without cable');
    }
}
