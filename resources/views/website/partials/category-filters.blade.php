<form method="GET" action="{{ route('website.category', $activeCategory->slug ?? $activeCategory->id) }}" class="gaget-filter-sidebar" data-gs-sidebar-slot x-data="{ open: true }">
    <div class="gaget-filter-sidebar-pin" data-gs-sidebar-pin>
    @if(request('sort'))
        <input type="hidden" name="sort" value="{{ request('sort') }}">
    @endif

    <div class="gaget-filter-sidebar__card">
        @if(!empty($filterConfig['price_enabled']))
            @php
                $boundMin = (float) ($priceBounds['min'] ?? 0);
                $boundMax = max($boundMin, (float) ($priceBounds['max'] ?? 0));
                $currency = $settings->currency_symbol ?? '৳';
                $minVal = request()->filled('min_price') ? request('min_price') : '';
                $maxVal = request()->filled('max_price') ? request('max_price') : '';
            @endphp
            <div class="gaget-filter-block">
                <h3 class="gaget-filter-block__title">Price</h3>
                @if($boundMax > 0)
                    <p class="gaget-filter-price-hint">{{ $currency }}{{ number_format($boundMin, 0) }} – {{ $currency }}{{ number_format($boundMax, 0) }}</p>
                @endif
                <div class="gaget-filter-price-inputs">
                    <label>
                        <span>Min</span>
                        <input type="number" name="min_price" min="0" step="1" value="{{ $minVal }}" placeholder="{{ $currency }}{{ $boundMin > 0 ? number_format($boundMin, 0) : '0' }}">
                    </label>
                    <label>
                        <span>Max</span>
                        <input type="number" name="max_price" min="0" step="1" value="{{ $maxVal }}" placeholder="{{ $currency }}{{ $boundMax > 0 ? number_format($boundMax, 0) : '0' }}">
                    </label>
                </div>
            </div>
        @endif

        @foreach($sidebarFacets ?? [] as $facet)
            <div class="gaget-filter-block" x-data="{ open: true }">
                <button type="button" class="gaget-filter-block__title gaget-filter-block__toggle" @click="open = !open">
                    <span>{{ $facet['label'] }}</span>
                    <svg class="w-4 h-4 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="gaget-filter-options" x-show="open" x-cloak>
                    @foreach($facet['options'] as $option)
                        @php
                            $selected = (array) request($facet['key'], []);
                            $checked = in_array($option['value'], $selected, true);
                        @endphp
                        <label class="gaget-filter-check">
                            <input type="checkbox" name="{{ $facet['key'] }}[]" value="{{ $option['value'] }}" @checked($checked) onchange="this.form.submit()">
                            <span>{{ $option['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="gaget-filter-actions">
            <button type="submit" class="gaget-filter-apply">Apply filters</button>
            <a href="{{ route('website.category', $activeCategory->slug ?? $activeCategory->id) }}" class="gaget-filter-clear">Clear</a>
        </div>
    </div>
    </div>
</form>
