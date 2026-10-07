@extends('website.layout')
@section('title', 'All brands — '.($settings->store_name ?? config('app.name', 'Maks Gadget')))
@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 sm:py-10"
     x-data="{
        q: '',
        names: @js($allBrands->map(fn ($b) => mb_strtolower($b->name))->values()),
        shows(name) { const t = this.q.trim().toLowerCase(); return !t || name.includes(t); },
        get none() { return this.q.trim() !== '' && !this.names.some((n) => this.shows(n)); },
     }">
    <nav class="text-xs text-slate-500 mb-4" aria-label="Breadcrumb">
        <a href="{{ route('home') }}" class="hover:text-blue-600">Home</a>
        <span class="mx-1.5">/</span>
        <span class="text-slate-700">Brands</span>
    </nav>

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6 sm:mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">All brands</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $allBrands->count() }} {{ \Illuminate\Support\Str::plural('brand', $allBrands->count()) }} in store — pick one to see its products.</p>
        </div>
        @if($allBrands->count() > 8)
            <div class="relative w-full sm:w-72">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" x-model="q" placeholder="Search brands…" autocomplete="off"
                       class="w-full rounded-xl border-slate-200 pl-9 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
        @endif
    </div>

    @if($allBrands->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 py-20 text-center">
            <p class="text-slate-600 font-semibold">No brands yet</p>
            <a href="{{ route('website.shop') }}" class="mt-5 inline-flex rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Browse all products</a>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 sm:gap-4">
            @foreach($allBrands as $brand)
                @php
                    $slug = \Illuminate\Support\Str::slug($brand->name);
                    $logo = $brand->logo_url ?: ($brand->logo_path ? public_storage_url($brand->logo_path) : null);
                    if ($brand->logo_path === 'brands/'.$slug.'.svg') {
                        $logo = null;
                    }
                    $count = (int) ($brand->products_count ?? 0);
                @endphp
                <a href="{{ route('website.brand', $slug) }}"
                   x-show="shows(@js(mb_strtolower($brand->name)))"
                   class="group flex flex-col items-center rounded-2xl border border-slate-200 bg-white p-4 text-center transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-lg hover:shadow-blue-100/60">
                    <span class="flex h-16 w-full items-center justify-center">
                        @if($logo)
                            <img src="{{ $logo }}" alt="{{ $brand->name }}" loading="lazy" decoding="async"
                                 class="max-h-14 max-w-[80%] object-contain transition group-hover:scale-105"
                                 onerror="this.replaceWith(Object.assign(document.createElement('span'), { className: 'text-2xl font-extrabold text-slate-300', textContent: @js(mb_strtoupper(mb_substr($brand->name, 0, 1))) }))">
                        @else
                            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-xl font-extrabold text-slate-500 group-hover:bg-blue-50 group-hover:text-blue-600">
                                {{ mb_strtoupper(mb_substr($brand->name, 0, 1)) }}
                            </span>
                        @endif
                    </span>
                    <span class="mt-3 text-sm font-semibold text-slate-900 group-hover:text-blue-600 line-clamp-1">{{ $brand->name }}</span>
                    <span class="mt-0.5 text-[11px] text-slate-500">
                        {{ $count > 0 ? $count.' '.\Illuminate\Support\Str::plural('product', $count) : 'Coming soon' }}
                    </span>
                </a>
            @endforeach
        </div>
        <p x-show="none" x-cloak
           class="py-12 text-center text-sm text-slate-500">No brand matches “<span x-text="q"></span>”.</p>
    @endif
</div>
@endsection
