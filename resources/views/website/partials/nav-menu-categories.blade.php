@php $menuCats = collect($allCategories ?? []); @endphp
<div class="gaget-nav-dropdown-menu gaget-mega"
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
        <span class="gaget-mega-title">Shop by category</span>
        <a href="{{ $allUrl }}" class="gaget-mega-all">View all <span aria-hidden="true">→</span></a>
    </div>
    @if($menuCats->isNotEmpty())
        <div class="gaget-mega-grid">
            @foreach($menuCats as $i => $cat)
                @php $meta = $cat->iconMeta(); @endphp
                <a href="{{ route('website.category', $cat->slug) }}"
                   class="gaget-mega-item"
                   style="--i: {{ min($i, 14) }}; --cat-color: {{ $meta['color'] }}; --cat-bg: {{ $meta['bg'] }};">
                    <span class="gaget-mega-icon" aria-hidden="true">
                        @include('website.partials.category-icon-svg', ['icon' => $meta['key'], 'class' => 'gaget-mega-svg'])
                    </span>
                    <span class="gaget-mega-name">{{ $cat->name }}</span>
                    <svg class="gaget-mega-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endforeach
        </div>
    @else
        <span class="gaget-nav-dropdown-empty">No categories yet</span>
    @endif
</div>
