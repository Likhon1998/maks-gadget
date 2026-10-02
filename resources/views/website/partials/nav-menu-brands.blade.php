@php $menuBrands = collect($brands ?? []); @endphp
<div class="gaget-nav-dropdown-menu gaget-nav-dropdown-menu--end gaget-mega gaget-mega--brands"
     x-show="open"
     x-cloak
     x-transition:enter="gaget-mega-enter"
     x-transition:enter-start="gaget-mega-from"
     x-transition:enter-end="gaget-mega-to"
     x-transition:leave="gaget-mega-leave"
     x-transition:leave-start="gaget-mega-to"
     x-transition:leave-end="gaget-mega-from"
     style="display: none;">
    <div class="gaget-mega-head">
        <span class="gaget-mega-title">Top brands</span>
        <a href="{{ $allUrl }}" class="gaget-mega-all">View all <span aria-hidden="true">→</span></a>
    </div>
    @if($menuBrands->isNotEmpty())
        <div class="gaget-mega-grid">
            @foreach($menuBrands as $i => $brand)
                <a href="{{ route('website.brand', \Illuminate\Support\Str::slug($brand->name)) }}"
                   class="gaget-mega-item"
                   style="--i: {{ min($i, 14) }};">
                    @php
                        // Generated text-only wordmarks are unreadable at tile size; use the initial instead.
                        $brandLogo = $brand->logo_url;
                        if ($brand->logo_path === 'brands/'.\Illuminate\Support\Str::slug($brand->name).'.svg') {
                            $brandLogo = null;
                        }
                    @endphp
                    <span class="gaget-mega-icon gaget-mega-icon--brand" aria-hidden="true">
                        @if($brandLogo)
                            <img src="{{ $brandLogo }}" alt="" class="gaget-mega-logo" loading="lazy" decoding="async">
                        @else
                            {{ mb_strtoupper(mb_substr($brand->name, 0, 1)) }}
                        @endif
                    </span>
                    <span class="gaget-mega-name">{{ $brand->name }}</span>
                    <svg class="gaget-mega-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    @else
        <span class="gaget-nav-dropdown-empty">No brands yet</span>
    @endif
</div>
