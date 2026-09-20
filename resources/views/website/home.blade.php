@extends('website.layout')
@php $ws = app(\App\Services\WebsiteService::class); @endphp

@section('content')

{{-- Hero: 3 portions — main slider + 2 CMS side cards --}}
@php
    $heroSideCards = ($heroSideCards ?? collect())->take(2)->map(function ($banner) {
        return (object) [
            'title' => $banner->title,
            'sub' => $banner->subtitle ?: ($banner->badge_text ?: ''),
            'url' => $banner->button_url ?: route('website.shop'),
            'cta' => $banner->button_text ?: 'Shop',
            'badge' => $banner->discount_badge ?: $banner->badge_text,
            'image' => $banner->image_path ? public_storage_url($banner->image_path) : null,
            'tone' => $banner->theme === 'light' ? 'light' : 'dark',
        ];
    });

    // Keep 3-portion layout visible until CMS cards are added
    if ($heroSideCards->isEmpty()) {
        $heroSideCards = collect([
            (object) [
                'title' => 'New Arrivals',
                'sub' => 'Fresh tech, just landed',
                'url' => route('website.shop', ['filter' => 'new']),
                'cta' => 'See new',
                'badge' => 'New',
                'image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=640&q=80',
                'tone' => 'dark',
            ],
            (object) [
                'title' => 'Best Sellers',
                'sub' => 'Most loved gadgets',
                'url' => route('website.shop'),
                'cta' => 'Shop',
                'badge' => 'Hot',
                'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=640&q=80',
                'tone' => 'light',
            ],
        ]);
    }
@endphp
<section class="mg-hero3">
    <div class="tn-container">
        <div class="mg-hero3-grid">
            <div
                class="mg-hero3-slider tn-hero"
                @if($heroSlides->count() > 1)
                    x-data="{
                        slide: 0,
                        total: {{ $heroSlides->count() }},
                        timer: null,
                        paused: false,
                        go(i) { this.slide = ((i % this.total) + this.total) % this.total; this.restart(); },
                        next() { this.go(this.slide + 1); },
                        prev() { this.go(this.slide - 1); },
                        restart() {
                            clearInterval(this.timer);
                            if (this.paused || this.total < 2) return;
                            this.timer = setInterval(() => { if (!this.paused) this.slide = (this.slide + 1) % this.total; }, 5500);
                        },
                        init() { this.restart(); }
                    }"
                    @mouseenter="paused = true; clearInterval(timer)"
                    @mouseleave="paused = false; restart()"
                    @focusin="paused = true; clearInterval(timer)"
                    @focusout="paused = false; restart()"
                @else
                    x-data="{ slide: 0, total: 1 }"
                @endif
            >
                @if($heroSlides->count() > 1)
                    <button type="button" @click="prev()" class="tn-hero-arrow left" aria-label="Previous">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" @click="next()" class="tn-hero-arrow right" aria-label="Next">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                @endif
                <div class="tn-hero-track">
                    @forelse($heroSlides as $i => $slide)
                        @php
                            $posterUrl = $slide->image_path ? public_storage_url($slide->image_path) : null;
                            if ($posterUrl && $slide->image_path) {
                                $full = public_storage_path($slide->image_path);
                                $posterUrl .= '?v='.(is_file($full) ? filemtime($full) : time());
                            }
                            $link = $slide->button_url ?: route('website.shop');
                        @endphp
                        <div
                            class="tn-hero-slide{{ $i === 0 ? ' is-active' : '' }}"
                            :class="{ 'is-active': slide === {{ $i }} }"
                            x-bind:aria-hidden="slide !== {{ $i }}"
                        >
                            @if($posterUrl)
                                <a href="{{ $link }}" class="tn-hero-poster" aria-label="{{ $slide->title }}" x-bind:tabindex="slide === {{ $i }} ? 0 : -1">
                                    <img
                                        src="{{ $posterUrl }}"
                                        alt="{{ $slide->title }}"
                                        class="tn-hero-img"
                                        width="1920"
                                        height="640"
                                        decoding="async"
                                        @if($i === 0) fetchpriority="high" @else loading="lazy" @endif
                                    >
                                </a>
                            @else
                                <div class="tn-hero-fallback">
                                    <div class="tn-hero-fallback-inner">
                                        <p class="tn-hero-kicker">{{ data_get($settings, 'special_offer_text') ?: 'Premium Electronics' }}</p>
                                        <h1 class="tn-hero-title">Upgrade Your Digital Life</h1>
                                        <p class="tn-hero-sub">Discover the latest gadgets, unbeatable deals, and premium tech at {{ $settings->store_name ?? 'our store' }}.</p>
                                        <div class="tn-hero-actions">
                                            <a href="{{ route('website.shop') }}" class="tn-btn tn-btn-primary">Shop Now</a>
                                            <a href="{{ route('website.shop', ['filter' => 'new']) }}" class="tn-btn tn-btn-outline">Explore Collection</a>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="tn-hero-slide is-active">
                            <div class="tn-hero-fallback">
                                <div class="tn-hero-fallback-inner">
                                    <p class="tn-hero-kicker">{{ data_get($settings, 'special_offer_text') ?: 'Premium Electronics' }}</p>
                                    <h1 class="tn-hero-title">Upgrade Your Digital Life</h1>
                                    <p class="tn-hero-sub">Discover the latest gadgets, unbeatable deals, and premium tech at {{ $settings->store_name ?? config('app.name', 'Maks Gadget') }}.</p>
                                    <div class="tn-hero-actions">
                                        <a href="{{ route('website.shop') }}" class="tn-btn tn-btn-primary">Shop Now</a>
                                        <a href="{{ route('website.shop', ['filter' => 'new']) }}" class="tn-btn tn-btn-outline">Explore Collection</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforelse
                </div>
                @if($heroSlides->count() > 1)
                    <div class="tn-hero-dots" role="tablist" aria-label="Hero slides">
                        @foreach($heroSlides as $di => $ds)
                            <button
                                type="button"
                                class="tn-hero-dot"
                                :class="{ 'is-active': slide === {{ $di }} }"
                                @click="go({{ $di }})"
                                role="tab"
                                :aria-selected="slide === {{ $di }}"
                                aria-label="Slide {{ $di + 1 }}"
                            >
                                <template x-if="slide === {{ $di }}">
                                    <span class="tn-hero-dot-fill"></span>
                                </template>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <aside class="mg-hero3-side" aria-label="Featured offers">
                @foreach($heroSideCards->take(2) as $side)
                    <a href="{{ $side->url }}" class="mg-hero3-card is-{{ $side->tone }}">
                        @if($side->image)
                            <img src="{{ $side->image }}" alt="" class="mg-hero3-card-img" loading="lazy" decoding="async">
                        @endif
                        <span class="mg-hero3-card-veil" aria-hidden="true"></span>
                        <span class="mg-hero3-card-body">
                            @if($side->badge)
                                <span class="mg-hero3-card-badge">{{ $side->badge }}</span>
                            @endif
                            <span class="mg-hero3-card-title">{{ $side->title }}</span>
                            @if(($side->sub ?? '') !== '')
                                <span class="mg-hero3-card-sub">{{ $side->sub }}</span>
                            @endif
                            <span class="mg-hero3-card-cta">{{ $side->cta }} <i aria-hidden="true">→</i></span>
                        </span>
                    </a>
                @endforeach
            </aside>
        </div>
    </div>
</section>

{{-- Service features — premium strip under hero --}}
@if($features->isNotEmpty())
<section class="tn-features">
    <div class="tn-container">
        <div class="tn-features-panel">
            <div class="tn-features-grid tn-features-grid--{{ min(max($features->count(), 1), 4) }}">
                @foreach($features as $feature)
                    <div class="tn-feature">
                        <div class="tn-feature-icon">@include('website.partials.feature-icon', ['icon' => $feature->icon])</div>
                        <div class="tn-feature-copy">
                            <p class="tn-feature-title">{{ $feature->title }}</p>
                            @if($feature->subtitle)<p class="tn-feature-sub">{{ $feature->subtitle }}</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif

{{-- Shop by Category — 3D coverflow with scroll / swipe / click --}}
@if($categories->isNotEmpty())
@php
    $catTaglines = [
        'smartphones' => 'Power in your pocket.',
        'phones' => 'Power in your pocket.',
        'laptops' => 'Create. Work. Win.',
        'tablets' => 'Light. Fast. Ready.',
        'headphones' => 'Immersive sound.',
        'earbuds' => 'Pure sound. Zero limits.',
        'earphones' => 'Pure sound. Zero limits.',
        'smartwatches' => 'Smarter. Healthier. You.',
        'watches' => 'Smarter. Healthier. You.',
        'cameras' => 'Capture every moment.',
        'gaming' => 'Play without limits.',
        'speakers' => 'Fill the room.',
        'chargers-cables' => 'Power that lasts.',
        'accessories' => 'Finish the look.',
        'monitors' => 'See every detail.',
        'drones' => 'Sky is the limit.',
    ];
    $catFallbacks = [
        'phone' => 'https://images.unsplash.com/photo-1592890288564-766794220d53?w=900&q=85',
        'laptop' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=900&q=85',
        'tablet' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=900&q=85',
        'headphones' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=900&q=85',
        'earbuds' => 'https://images.unsplash.com/photo-1606220588913-b3aacb4d2f46?w=900&q=85',
        'watch' => 'https://images.unsplash.com/photo-1434493789847-2f02dc6ca35d?w=900&q=85',
        'camera' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=900&q=85',
        'game' => 'https://images.unsplash.com/photo-1606144042614-b2417e99c4e3?w=900&q=85',
        'speaker' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=900&q=85',
        'drone' => 'https://images.unsplash.com/photo-1473968512647-3e447244af8f?w=900&q=85',
        'monitor' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=900&q=85',
        'mouse' => 'https://images.unsplash.com/photo-1527814050087-3793815479db?w=900&q=85',
        'plug' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=900&q=85',
    ];
    $catThemes = ['is-ink', 'is-graphite', 'is-midnight', 'is-steel', 'is-ink', 'is-graphite', 'is-midnight', 'is-steel'];
    $coverCats = $categories->values();
    $coverTotal = $coverCats->count();
    $coverStart = $coverTotal > 1 ? min(1, $coverTotal - 1) : 0;
@endphp
<section
    class="mg-cover"
    x-data="{
        active: {{ $coverStart }},
        total: {{ $coverTotal }},
        dragging: false,
        startX: 0,
        lastWheel: 0,
        go(i) {
            if (this.total < 1) return;
            this.active = ((i % this.total) + this.total) % this.total;
        },
        next() { this.go(this.active + 1); },
        prev() { this.go(this.active - 1); },
        delta(i) {
            const n = this.total;
            if (n < 2) return 0;
            let d = ((i - this.active) % n + n) % n;
            if (d > n / 2) d -= n;
            return d;
        },
        onWheel(e) {
            const now = Date.now();
            if (now - this.lastWheel < 380) return;
            this.lastWheel = now;
            (e.deltaY > 0 || e.deltaX > 0) ? this.next() : this.prev();
        },
        styleFor(i) {
            const d = this.delta(i);
            const abs = Math.abs(d);
            if (abs > 3) {
                return 'opacity:0; visibility:hidden; pointer-events:none; transform: translate(-50%, -50%) scale(0.58);';
            }
            const x = d * 54;
            const y = abs * 2.5;
            const rot = d * -22;
            const scale = Math.max(0.72, 1 - abs * 0.1);
            const z = 50 - abs;
            const opacity = abs === 0 ? 1 : (abs === 1 ? 0.92 : (abs === 2 ? 0.7 : 0.42));
            return `transform: translate(-50%, -50%) translateX(${x}%) translateY(${y}%) rotateY(${rot}deg) scale(${scale}); z-index:${z}; opacity:${opacity};`;
        },
        onPointerDown(e) {
            this.dragging = true;
            this.startX = e.clientX ?? (e.touches && e.touches[0]?.clientX) ?? 0;
        },
        onPointerUp(e) {
            if (!this.dragging) return;
            const endX = e.clientX ?? (e.changedTouches && e.changedTouches[0]?.clientX) ?? this.startX;
            const dx = endX - this.startX;
            this.dragging = false;
            if (Math.abs(dx) < 40) return;
            dx < 0 ? this.next() : this.prev();
        }
    }"
    @keydown.left.window="prev()"
    @keydown.right.window="next()"
>
    <div class="tn-container">
        <div class="mg-cover-head">
            <div class="mg-cover-copy">
                <p class="mg-cover-eyebrow">Curated collections</p>
                <h2 class="mg-cover-title">Shop by <span>Category</span></h2>
                <p class="mg-cover-sub">Premium gadgets, sorted for how you live — scroll to explore.</p>
            </div>

            <div class="mg-cover-marquee" aria-label="Categories preview">
                <div class="mg-cover-marquee-fade mg-cover-marquee-fade--left" aria-hidden="true"></div>
                <div class="mg-cover-marquee-fade mg-cover-marquee-fade--right" aria-hidden="true"></div>
                <div class="mg-cover-marquee-track">
                    @foreach([0, 1] as $loopPass)
                        @foreach($coverCats as $i => $category)
                            @php
                                $iconMeta = $category->iconMeta();
                                $count = (int) ($category->products_count ?? 0);
                                $countLabel = $count > 0 ? ($count >= 100 ? '100+' : $count.'+') : 'New';
                            @endphp
                            <button
                                type="button"
                                class="mg-cover-marquee-item"
                                :class="{ 'is-active': active === {{ $i }} }"
                                @click="go({{ $i }})"
                                tabindex="{{ $loopPass === 0 ? 0 : -1 }}"
                                aria-hidden="{{ $loopPass === 0 ? 'false' : 'true' }}"
                            >
                                <span class="mg-cover-marquee-ico">
                                    @include('website.partials.category-icon-svg', ['icon' => $iconMeta['key'], 'class' => 'mg-cover-marquee-svg'])
                                </span>
                                <span class="mg-cover-marquee-text">
                                    <span class="mg-cover-marquee-name">{{ $category->name }}</span>
                                    <span class="mg-cover-marquee-count">{{ $countLabel }}</span>
                                </span>
                            </button>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <a href="{{ route('website.shop') }}" class="mg-cover-all">View all <span aria-hidden="true">→</span></a>
        </div>
    </div>

    <div
        class="mg-cover-stage"
        @wheel.prevent="onWheel($event)"
        @pointerdown="onPointerDown($event)"
        @pointerup="onPointerUp($event)"
        @pointercancel="dragging=false"
        @touchstart.passive="onPointerDown($event)"
        @touchend.passive="onPointerUp($event)"
    >
        <div class="mg-cover-track" aria-live="polite">
            @foreach($coverCats as $i => $category)
                @php
                    $iconMeta = $category->iconMeta();
                    $slug = $category->slug ?? \Illuminate\Support\Str::slug($category->name);
                    $img = $ws->categoryImageUrl($category) ?: ($catFallbacks[$iconMeta['key']] ?? $catFallbacks['phone']);
                    $tagline = $catTaglines[$slug] ?? ($catTaglines[$iconMeta['key']] ?? 'Explore the collection.');
                    $count = (int) ($category->products_count ?? 0);
                    $countLabel = $category->product_count_label
                        ?: ($count > 0 ? ($count >= 100 ? '100+ products' : $count.' products') : 'Shop now');
                    $theme = $catThemes[$i % count($catThemes)];
                    $url = route('website.category', $category->slug ?? $category->id);
                @endphp
                <article
                    class="mg-cover-card {{ $theme }}"
                    :class="{ 'is-active': active === {{ $i }} }"
                    :style="styleFor({{ $i }})"
                    @click="active === {{ $i }} ? (window.location.href = @js($url)) : go({{ $i }})"
                    role="button"
                    tabindex="0"
                    @keydown.enter.prevent="active === {{ $i }} ? (window.location.href = @js($url)) : go({{ $i }})"
                    aria-label="{{ $category->name }}"
                >
                    <div class="mg-cover-media">
                        <img src="{{ $img }}" alt="{{ $category->name }}" class="mg-cover-img" loading="lazy" decoding="async">
                    </div>
                    <div class="mg-cover-veil" aria-hidden="true"></div>
                    <div class="mg-cover-shine" aria-hidden="true"></div>
                    <div class="mg-cover-chip">
                        <span class="mg-cover-chip-icon" style="--cat-color: {{ $iconMeta['color'] }};">
                            @include('website.partials.category-icon-svg', ['icon' => $iconMeta['key'], 'class' => 'mg-cover-chip-svg'])
                        </span>
                        <span>{{ $countLabel }}</span>
                    </div>
                    <div class="mg-cover-foot">
                        <div class="mg-cover-foot-text">
                            <h3 class="mg-cover-name">{{ $category->name }}</h3>
                            <p class="mg-cover-tagline">{{ $tagline }}</p>
                        </div>
                        <a href="{{ $url }}" class="mg-cover-cta" @click.stop aria-label="Shop {{ $category->name }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                    </div>
                    <div class="mg-cover-pager" x-show="active === {{ $i }}" x-cloak>
                        <span x-text="String(active + 1).padStart(2,'0')"></span>
                        <i aria-hidden="true"></i>
                        <span x-text="String(total).padStart(2,'0')"></span>
                    </div>
                </article>
            @endforeach
        </div>

        <button type="button" class="mg-cover-arrow left" @click="prev()" aria-label="Previous category">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <button type="button" class="mg-cover-arrow right" @click="next()" aria-label="Next category">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>
</section>
@endif

{{-- Flash Sale --}}
@if($flashSaleProducts->isNotEmpty())
<section class="tn-flash">
    <div class="tn-container">
        <div class="tn-flash-head">
            <div class="tn-flash-head-left">
                <div class="tn-flash-title-row">
                    <span class="tn-flash-bolt" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L4.5 13.5H11l-1 8.5L19.5 10.5H13L13 2z"/></svg>
                    </span>
                    <h2 class="tn-flash-title">Flash Sale</h2>
                    <span class="tn-flash-live">Live</span>
                </div>
                <div class="tn-countdown tn-countdown--flash" x-data="{
                    h:0,m:0,s:0,
                    end: {{ ($flashSaleEndsAt ?? null) ? ((int) $flashSaleEndsAt->timestamp * 1000) : 'null' }},
                    tick(){
                        const target = this.end ?? new Date().setHours(23,59,59,999);
                        const d = Math.max(0, target - Date.now());
                        this.h = Math.floor(d/3600000);
                        this.m = Math.floor((d%3600000)/60000);
                        this.s = Math.floor((d%60000)/1000);
                    }
                }" x-init="tick(); setInterval(()=>tick(),1000)">
                    <span class="tn-countdown-label">Ends in</span>
                    <span class="tn-countdown-box"><strong x-text="String(h).padStart(2,'0')">00</strong><small>Hrs</small></span>
                    <span class="tn-countdown-sep">:</span>
                    <span class="tn-countdown-box"><strong x-text="String(m).padStart(2,'0')">00</strong><small>Min</small></span>
                    <span class="tn-countdown-sep">:</span>
                    <span class="tn-countdown-box"><strong x-text="String(s).padStart(2,'0')">00</strong><small>Sec</small></span>
                </div>
            </div>
            <a href="{{ route('website.shop', ['filter' => 'deals']) }}" class="tn-flash-link">View All Deals &rarr;</a>
        </div>

        <div class="tn-flash-grid tn-flash-grid--8">
            @foreach($flashSaleProducts as $product)
                @include('website.partials.tn-product-card', ['product' => $product, 'flash' => true])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- New Arrivals --}}
@if(($newArrivals ?? collect())->isNotEmpty())
<section class="tn-section tn-section-new">
    <div class="tn-container">
        <div class="tn-section-head">
            <div class="tn-section-head-left">
                <h2 class="tn-section-title">New Arrivals</h2>
                <span class="tn-new-pill">Just in</span>
            </div>
            <a href="{{ route('website.shop', ['filter' => 'new']) }}" class="tn-section-link">View All New Arrivals &rarr;</a>
        </div>
        <div class="tn-flash-grid tn-flash-grid--8">
            @foreach($newArrivals as $product)
                @include('website.partials.tn-product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- What's Trending Now --}}
@if($trendingProducts->isNotEmpty())
<section class="tn-trending" aria-labelledby="tn-trending-heading">
    <div class="tn-container">
        <div class="tn-trending-head">
            <div class="tn-trending-copy">
                <p class="tn-trending-kicker">Most loved</p>
                <h2 id="tn-trending-heading" class="tn-trending-title">What's <span>Trending</span></h2>
                <p class="tn-trending-sub">Customer favorites — grab them before they sell out.</p>
            </div>
            <a href="{{ route('website.shop', ['filter' => 'bestsellers']) }}" class="tn-trending-all">
                View all <span aria-hidden="true">→</span>
            </a>
        </div>

        <div class="tn-trending-grid">
            @foreach($trendingProducts as $product)
                @include('website.partials.tn-trending-card', [
                    'product' => $product,
                    'trendingRank' => $loop->iteration,
                ])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Brands We Carry — continuous slow marquee --}}
@if($brands->isNotEmpty())
@php
    $brandTaglines = [
        'apple' => 'iPhone · iPad · Mac · Watch',
        'samsung' => 'Galaxy · Tablets · Watches',
        'xiaomi' => 'Smartphones · IoT · Accessories',
        'oneplus' => 'Phones · Buds · Accessories',
        'realme' => 'Smartphones · AIoT · Accessories',
        'jbl' => 'Audio · Headphones · Speakers',
        'anker' => 'Chargers · Power Banks · Cables',
        'logitech' => 'Accessories · Keyboards · Mice',
        'baseus' => 'Chargers · Cables · Car Accessories',
        'boat' => 'Audio · Wearables · Accessories',
        'sony' => 'Audio · Cameras · Gaming',
        'bose' => 'Headphones · Speakers · Audio',
        'dell' => 'Laptops · Monitors · PCs',
        'hp' => 'Laptops · Printers · PCs',
        'asus' => 'Laptops · Gaming · Components',
        'lenovo' => 'Laptops · Tablets · PCs',
        'acer' => 'Laptops · Monitors · PCs',
        'canon' => 'Cameras · Lenses · Printers',
        'gopro' => 'Action Cams · Mounts · Accessories',
        'google' => 'Pixel · Nest · Accessories',
        'razer' => 'Gaming · Keyboards · Mice',
        'nothing' => 'Phones · Audio · Accessories',
        'microsoft' => 'Surface · Accessories · Software',
    ];
    $partnerBrands = $brands->values();
@endphp
<section class="tn-brands" aria-labelledby="tn-brands-heading">
    <div class="tn-container">
        <div class="tn-brands-head">
            <div class="tn-brands-copy">
                <p class="tn-brands-eyebrow">Partners</p>
                <h2 id="tn-brands-heading" class="tn-brands-title">Brands We <span>Carry</span></h2>
                <p class="tn-brands-sub">Trusted names in gadgets — quality, performance, and innovation.</p>
            </div>
        </div>
    </div>

    <div class="tn-brands-marquee" aria-label="Brand partners">
        <div class="tn-brands-track">
            @foreach([0, 1] as $loopPass)
                @foreach($partnerBrands as $brand)
                    @php
                        $brandSlug = \Illuminate\Support\Str::slug($brand->name);
                        $logoUrl = $brand->logo_url
                            ?: ($brand->logo_path ? public_storage_url($brand->logo_path) : null);
                        $tagline = $brandTaglines[$brandSlug] ?? 'Gadgets · Accessories';
                    @endphp
                    <a href="{{ route('website.brand', $brandSlug) }}"
                       class="tn-brand-card"
                       title="Shop {{ $brand->name }}"
                       tabindex="{{ $loopPass === 0 ? 0 : -1 }}"
                       aria-hidden="{{ $loopPass === 0 ? 'false' : 'true' }}">
                        <span class="tn-brand-logo-frame">
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}"
                                     alt="{{ $brand->name }}"
                                     class="tn-brand-logo"
                                     loading="lazy"
                                     decoding="async"
                                     onerror="this.classList.add('is-broken'); this.nextElementSibling?.classList.add('is-visible');">
                                <span class="tn-brand-fallback">{{ $brand->name }}</span>
                            @else
                                <span class="tn-brand-fallback is-visible">{{ $brand->name }}</span>
                            @endif
                        </span>
                        <span class="tn-brand-name">{{ $brand->name }}</span>
                        <span class="tn-brand-cats">{{ $tagline }}</span>
                    </a>
                @endforeach
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Deals You'll Love (CMS → Landing Page promo banners) --}}
@if($promoBanners->isNotEmpty())
@php
    $dealsKicker = data_get($settings, 'deals_kicker') ?: 'Special Offers';
    $dealsTitle = data_get($settings, 'deals_title') ?: "Deals You'll";
    $dealsAccent = data_get($settings, 'deals_title_accent') ?: 'Love';
    $dealsSub = data_get($settings, 'deals_subtitle') ?: 'Grab the best deals on top-quality gadgets and accessories.';
@endphp
<section class="tn-deals">
    <div class="tn-container">
        <div class="tn-deals-head">
            <div class="tn-deals-kicker">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/>
                    <circle cx="7" cy="7" r="1.2" fill="currentColor" stroke="none"/>
                </svg>
                {{ $dealsKicker }}
            </div>
            <h2 class="tn-deals-title">
                <span class="tn-deals-rays tn-deals-rays--left" aria-hidden="true"><i></i><i></i><i></i></span>
                <span>{{ $dealsTitle }} <em>{{ $dealsAccent }}</em></span>
                <span class="tn-deals-rays tn-deals-rays--right" aria-hidden="true"><i></i><i></i><i></i></span>
            </h2>
            <p class="tn-deals-sub">{{ $dealsSub }}</p>
        </div>

        <div class="tn-deals-grid">
            @foreach($promoBanners->take(2) as $banner)
                @php
                    $isLight = $banner->theme === 'light';
                    $url = $banner->button_url ?: route('website.shop');
                    $sub = (string) ($banner->subtitle ?? '');
                    $hi = trim((string) ($banner->highlight_text ?? ''));
                    if ($hi !== '' && $sub !== '' && str_contains($sub, $hi)) {
                        $subHtml = str_replace($hi, '<strong>'.e($hi).'</strong>', e($sub));
                    } else {
                        $subHtml = e($sub);
                    }
                @endphp
                <article class="tn-deal {{ $isLight ? 'is-light' : 'is-dark' }}">
                    @if($banner->discount_badge)
                        <span class="tn-deal-disc">{{ $banner->discount_badge }}</span>
                    @endif

                    <div class="tn-deal-body">
                        @if($banner->badge_text)
                            <span class="tn-deal-badge">
                                @if($isLight)
                                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13 2L4 14h7l-1 8 10-14h-7l1-6z"/></svg>
                                @else
                                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.5 2.2c.3-.5 1.1-.3 1.1.3v5.2h4.2c.6 0 .9.7.5 1.1l-8.4 9.7c-.4.5-1.2.2-1.1-.5l.8-5.5H5.5c-.6 0-.9-.7-.5-1.1L12.5 2.2z"/></svg>
                                @endif
                                {{ $banner->badge_text }}
                            </span>
                        @endif

                        <div class="tn-deal-copy">
                            <div class="tn-deal-copy-main">
                                <h3 class="tn-deal-name">{{ $banner->title }}</h3>
                                @if($sub !== '')
                                    <p class="tn-deal-offer">{!! $subHtml !!}</p>
                                @endif
                            </div>
                            @if($banner->price_from)
                                <div class="tn-deal-price">
                                    <span>From</span>
                                    <strong>{{ $ws->formatPrice($banner->price_from, $settings) }}</strong>
                                </div>
                            @endif
                        </div>

                        <div class="tn-deal-actions">
                            <a href="{{ $url }}" class="tn-deal-cta">
                                @if($isLight)
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l3-8H6.4M7 13L5.4 5M7 13l-2 7h14M10 20a1 1 0 102 0 1 1 0 00-2 0zm8 0a1 1 0 102 0 1 1 0 00-2 0z"/></svg>
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                @endif
                                {{ $banner->button_text ?: 'Shop Now' }}
                            </a>
                            <a href="{{ $url }}" class="tn-deal-arrow" aria-label="{{ $banner->button_text ?: 'Shop Now' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </a>
                        </div>
                    </div>

                    <div class="tn-deal-media">
                        <div class="tn-deal-podium" aria-hidden="true"></div>
                        @if($banner->image_path)
                            <img src="{{ public_storage_url($banner->image_path) }}" alt="{{ $banner->title }}" class="tn-deal-img">
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Testimonials (CMS → Reviews) --}}
@if(($featuredReviews ?? collect())->isNotEmpty())
<section class="tn-section">
    <div class="tn-container">
        <div class="tn-section-head tn-section-head-center">
            <h2 class="tn-section-title">What Our Customers Say</h2>
            <p class="tn-section-desc">Real feedback from shoppers who love our products and service.</p>
        </div>
        <div class="tn-review-grid">
            @foreach($featuredReviews->take(3) as $review)
                <div class="tn-review-card">
                    <div class="tn-review-stars">
                        @for($i = 1; $i <= 5; $i++)
                            <span class="{{ $i <= (int) $review->rating ? 'filled' : '' }}">&#9733;</span>
                        @endfor
                    </div>
                    <p class="tn-review-body">&ldquo;{{ $review->body }}&rdquo;</p>
                    <div class="tn-review-author">
                        @if($review->avatar_path)
                            <img src="{{ public_storage_url($review->avatar_path) }}" alt="" class="tn-review-avatar">
                        @else
                            <div class="tn-review-avatar tn-review-avatar--letter">{{ strtoupper(mb_substr($review->customer_name, 0, 1)) }}</div>
                        @endif
                        <div>
                            <p class="tn-review-name">{{ $review->customer_name }}</p>
                            @if($review->customer_title)
                                <p class="tn-review-role">{{ $review->customer_title }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Latest from the Blog --}}
@if(($latestBlogs ?? collect())->isNotEmpty())
<section class="tn-blog" aria-labelledby="tn-blog-heading">
    <div class="tn-container">
        <div class="tn-blog-head">
            <p class="tn-blog-kicker">
                <span class="tn-blog-kicker-line" aria-hidden="true"></span>
                Latest from the Blog
                <span class="tn-blog-kicker-line" aria-hidden="true"></span>
            </p>
            <div class="tn-blog-head-main">
                <div class="tn-blog-head-copy">
                    <h2 id="tn-blog-heading" class="tn-blog-heading">Latest from the <em>Blog</em></h2>
                    <p class="tn-blog-sub">Stay ahead with gadget tips, reviews, guides and tech insights.</p>
                </div>
                <a href="{{ route('website.blogs') }}" class="tn-blog-all">
                    View All Articles
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6"/></svg>
                </a>
            </div>
        </div>

        <div class="tn-blog-grid">
            @foreach($latestBlogs as $post)
                @php
                    $excerpt = trim((string) ($post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $post->body), 120)));
                @endphp
                <article class="tn-blog-card">
                    <a href="{{ route('website.blog', $post->slug) }}" class="tn-blog-media" tabindex="-1" aria-hidden="true">
                        <img src="{{ $post->coverUrl() }}" alt="" class="tn-blog-img" loading="lazy" decoding="async">
                    </a>
                    <div class="tn-blog-body">
                        @if($post->category)
                            <span class="tn-blog-tag">{{ $post->category->name }}</span>
                        @endif
                        <h3 class="tn-blog-title">
                            <a href="{{ route('website.blog', $post->slug) }}">{{ $post->title }}</a>
                        </h3>
                        <p class="tn-blog-meta">
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M8 3v4M16 3v4M3 10h18"/></svg>
                                {{ optional($post->published_at)->format('M j, Y') ?: 'Recently' }}
                            </span>
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                                {{ $post->readingTimeLabel() }}
                            </span>
                        </p>
                        @if($excerpt !== '')
                            <p class="tn-blog-excerpt">{{ $excerpt }}</p>
                        @else
                            <p class="tn-blog-excerpt tn-blog-excerpt--empty" aria-hidden="true">&nbsp;</p>
                        @endif
                        <a href="{{ route('website.blog', $post->slug) }}" class="tn-blog-read">
                            Read Article
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6"/></svg>
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

@endsection
