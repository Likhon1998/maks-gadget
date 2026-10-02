@extends('website.layout')

@section('title', 'Track Order — '.($settings->store_name ?? config('app.name', 'Maks Gadget')))

@php
    $cur = $settings->currency_symbol ?? '৳';
    $money = fn ($v) => $cur.number_format((float) $v, 0);
    $errorMessage = $trackError ?? session('error');
    $foundOrders = session('foundOrders', []);
    $findError = session('findError');
    $startTab = ($findError || !empty($foundOrders) || $errors->has('find_phone')) ? 'find' : 'id';
    $supportPhone = trim((string) data_get($settings, 'contact_phone'));
    $isMember = (bool) auth('web')->user()?->isStorefrontCustomer();
    $hasResult = !empty($tracking);

    $timeline = $tracking['timeline'] ?? [];
    $isClosed = in_array($tracking['status_raw'] ?? null, ['cancelled', 'returned', 'refunded'], true);
    $activeIndex = 0;
    foreach ($timeline as $i => $step) {
        if (!empty($step['active']) || !empty($step['done'])) {
            $activeIndex = $i;
        }
    }
    $progress = count($timeline) > 1 ? round($activeIndex / (count($timeline) - 1) * 100) : 100;
    $items = collect($tracking['items'] ?? []);
    $latestUpdate = $tracking['updates'][0] ?? null;

    $tone = match (true) {
        $isClosed => ['pill' => 'bg-rose-50 text-rose-700 ring-rose-200', 'banner' => 'bg-rose-50/70 border-rose-100', 'icon' => 'bg-rose-100 text-rose-600'],
        ($tracking['status'] ?? null) === 'completed' => ['pill' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'banner' => 'bg-emerald-50/70 border-emerald-100', 'icon' => 'bg-emerald-100 text-emerald-600'],
        ($tracking['status'] ?? null) === 'shipped' => ['pill' => 'bg-indigo-50 text-indigo-700 ring-indigo-200', 'banner' => 'bg-indigo-50/70 border-indigo-100', 'icon' => 'bg-indigo-100 text-indigo-600'],
        ($tracking['status'] ?? null) === 'processing' => ['pill' => 'bg-amber-50 text-amber-700 ring-amber-200', 'banner' => 'bg-amber-50/70 border-amber-100', 'icon' => 'bg-amber-100 text-amber-600'],
        default => ['pill' => 'bg-blue-50 text-blue-700 ring-blue-200', 'banner' => 'bg-blue-50/70 border-blue-100', 'icon' => 'bg-blue-100 text-blue-600'],
    };

    $stepIcons = [
        'pending' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        'processing' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'shipped' => 'M1 7h13v10H1V7zm13 3h5l3 3v4h-8v-7zM5.5 19.5a1.75 1.75 0 100-3.5 1.75 1.75 0 000 3.5zm12 0a1.75 1.75 0 100-3.5 1.75 1.75 0 000 3.5z',
        'completed' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    ];

    $iconPhone = 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z';
    $iconHash = 'M7 20l4-16m2 16l4-16M6 9h14M4 15h14';
    $inputClass = 'h-11 w-full rounded-xl border border-slate-200 bg-slate-50/70 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-100';
    $card = 'rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04),0_8px_24px_-12px_rgba(15,23,42,0.12)]';
    $label = 'text-[10.5px] font-bold uppercase tracking-[0.14em] text-slate-500';
@endphp

@section('content')
<div class="bg-gradient-to-b from-blue-50/60 via-white to-white"
     x-data="{
        tab: @js($startTab),
        recent: [],
        copied: false,
        init() {
            try {
                const list = JSON.parse(localStorage.getItem('gaget_guest_orders') || '[]');
                this.recent = Array.isArray(list) ? list.filter(o => o && o.invoice && o.phone) : [];
            } catch (e) { this.recent = []; }
        },
        trackUrl(o) {
            return @js(route('website.track')) + '?' + new URLSearchParams({ invoice: o.invoice, phone: o.phone }).toString();
        },
        forget(o) {
            this.recent = this.recent.filter(r => r.invoice !== o.invoice);
            try { localStorage.setItem('gaget_guest_orders', JSON.stringify(this.recent)); } catch (e) {}
        },
        fmtDate(iso) {
            const d = new Date(iso);
            return isNaN(d) ? '' : d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
        },
        copy(text) {
            navigator.clipboard?.writeText(text).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 1600);
            });
        },
     }">
    <div class="mx-auto max-w-6xl px-4 py-5 sm:px-6 lg:px-8 lg:py-6">

        {{-- Title --}}
        <div class="mb-4 flex flex-wrap items-end justify-between gap-x-6 gap-y-1">
            <div>
                <p class="text-[10.5px] font-bold uppercase tracking-[0.18em] text-blue-600">Order tracking</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-[28px]">Where's my order?</h1>
            </div>
            <p class="text-[13px] text-slate-500">No account needed — use your Order ID or mobile number.</p>
        </div>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">

            {{-- Main --}}
            <div class="min-w-0">
                @if($hasResult)
                    <section class="{{ $card }} overflow-hidden">
                        {{-- Result header --}}
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5">
                            <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="{{ $label }}">Order</span>
                                <span class="font-mono text-[17px] font-extrabold tracking-wide text-slate-900">{{ $tracking['invoice'] }}</span>
                                <button type="button" @click="copy(@js($tracking['invoice']))"
                                        class="-ml-1 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" title="Copy Order ID">
                                    <svg x-show="!copied" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    <svg x-show="copied" x-cloak class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <span class="text-xs text-slate-500">Placed {{ $tracking['date'] }}</span>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold ring-1 {{ $tone['pill'] }}">{{ $tracking['status_label'] }}</span>
                        </div>

                        <div class="grid md:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] md:divide-x md:divide-slate-100">
                            {{-- Status side --}}
                            <div class="space-y-5 p-5">
                                <div class="flex items-start gap-3 rounded-xl border px-4 py-3 {{ $tone['banner'] }}">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $tone['icon'] }}">
                                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $isClosed ? 'M6 18L18 6M6 6l12 12' : ($stepIcons[$tracking['status'] ?? 'pending'] ?? $stepIcons['pending']) }}"/></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-slate-900">{{ $tracking['status_label'] }}</p>
                                        <p class="text-[13px] leading-snug text-slate-600">{{ $tracking['where_is_product'] ?? '' }}</p>
                                        @if(!empty($tracking['courier']) || !empty($tracking['tracking_number']))
                                            <p class="mt-1.5 text-xs font-medium text-slate-700">
                                                {{ $tracking['courier'] ?: 'Courier' }}@if(!empty($tracking['tracking_number'])) · <span class="font-mono font-semibold">#{{ $tracking['tracking_number'] }}</span>@endif
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                @if(!$isClosed && count($timeline) > 1)
                                    <div class="relative pt-1">
                                        <div class="absolute left-[12.5%] right-[12.5%] top-[22px] h-1 rounded-full bg-slate-100" aria-hidden="true">
                                            <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-blue-600" style="width: {{ $progress }}%"></div>
                                        </div>
                                        <ol class="relative grid" style="grid-template-columns: repeat({{ count($timeline) }}, minmax(0, 1fr));">
                                            @foreach($timeline as $step)
                                                @php
                                                    $done = !empty($step['done']);
                                                    $active = !empty($step['active']);
                                                    $reached = $done || $active;
                                                @endphp
                                                <li class="flex flex-col items-center text-center">
                                                    <span class="relative flex h-9 w-9 items-center justify-center rounded-full
                                                        {{ $done ? 'bg-blue-600 text-white shadow-md shadow-blue-600/25' : ($active ? 'bg-white text-blue-600 ring-[3px] ring-blue-600' : 'bg-white text-slate-300 ring-2 ring-slate-200') }}">
                                                        @if($active && !$done)
                                                            <span class="absolute inset-0 animate-ping rounded-full bg-blue-400/30" aria-hidden="true"></span>
                                                        @endif
                                                        <svg class="relative h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="{{ $done ? 'M5 13l4 4L19 7' : ($stepIcons[$step['key'] ?? ''] ?? $stepIcons['pending']) }}"/></svg>
                                                    </span>
                                                    <span class="mt-2 text-[11.5px] font-bold leading-tight {{ $reached ? 'text-slate-900' : 'text-slate-400' }}">{{ $step['label'] ?? '' }}</span>
                                                    <span class="mt-0.5 min-h-[14px] text-[10.5px] leading-tight text-slate-500">{{ $reached ? ($step['at'] ?? '') : '' }}</span>
                                                </li>
                                            @endforeach
                                        </ol>
                                    </div>
                                @endif

                                @if($latestUpdate)
                                    <div class="rounded-xl bg-slate-50 px-4 py-3">
                                        <div class="flex items-baseline justify-between gap-3">
                                            <p class="{{ $label }}">Latest update</p>
                                            <p class="text-[11px] text-slate-400">{{ $latestUpdate['at'] }}</p>
                                        </div>
                                        <p class="mt-1 text-[13px] font-semibold text-slate-800">{{ $latestUpdate['label'] ?: ucfirst(str_replace('_', ' ', $latestUpdate['status'])) }}</p>
                                        @if(!empty($latestUpdate['note']))
                                            <p class="text-[12.5px] leading-snug text-slate-600">{{ $latestUpdate['note'] }}</p>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Order side --}}
                            <div class="space-y-4 border-t border-slate-100 p-5 md:border-t-0">
                                @if($items->isNotEmpty())
                                    <div>
                                        <p class="{{ $label }} mb-2">Items · {{ $items->sum('qty') }}</p>
                                        <ul class="space-y-2">
                                            @foreach($items->take(2) as $item)
                                                <li class="flex items-center gap-3">
                                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-100 bg-slate-50 p-1">
                                                        @if(!empty($item['image']))
                                                            <img src="{{ $item['image'] }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                                                        @endif
                                                    </span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-[13px] font-semibold text-slate-900">{{ $item['name'] }}</span>
                                                        <span class="block text-[11.5px] text-slate-500">Qty {{ $item['qty'] }}</span>
                                                    </span>
                                                    <span class="text-[13px] font-bold text-slate-900">{{ $money(str_replace(',', '', $item['subtotal'])) }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                        @if($items->count() > 2)
                                            <p class="mt-1.5 text-[11.5px] font-medium text-slate-500">+ {{ $items->count() - 2 }} more {{ \Illuminate\Support\Str::plural('item', $items->count() - 2) }}</p>
                                        @endif
                                    </div>
                                @endif

                                <div class="rounded-xl border border-slate-200 px-4 py-3">
                                    <dl class="space-y-1.5 text-[13px]">
                                        @if(($tracking['delivery_charge'] ?? 0) > 0)
                                            <div class="flex justify-between"><dt class="text-slate-500">Delivery</dt><dd class="font-medium text-slate-800">{{ $money($tracking['delivery_charge']) }}</dd></div>
                                        @endif
                                        <div class="flex justify-between"><dt class="text-slate-500">Order total</dt><dd class="font-semibold text-slate-900">{{ $money($tracking['total_amount']) }}</dd></div>
                                        @if(($tracking['paid_amount'] ?? 0) > 0)
                                            <div class="flex justify-between"><dt class="text-slate-500">Paid</dt><dd class="font-semibold text-emerald-700">− {{ $money($tracking['paid_amount']) }}</dd></div>
                                        @endif
                                        <div class="flex items-baseline justify-between border-t border-dashed border-slate-200 pt-2">
                                            <dt class="font-semibold text-slate-700">Due on delivery</dt>
                                            <dd class="text-base font-extrabold text-slate-900">{{ $money($tracking['due_amount']) }}</dd>
                                        </div>
                                    </dl>
                                    <p class="mt-1 text-[11px] text-slate-500">{{ $tracking['payment_label'] }}</p>
                                </div>

                                <div class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3">
                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-orange-50 text-orange-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="{{ $label }}">Delivering to</p>
                                        <p class="mt-0.5 text-[13px] font-semibold text-slate-900">{{ $tracking['customer_name'] ?: '—' }}</p>
                                        <p class="text-[12.5px] leading-snug text-slate-600 break-words">{{ $tracking['delivery_address'] ?: 'No address on file' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                @else
                    @include('website.partials.track-lookup', ['compact' => false])
                @endif
            </div>

            {{-- Sidebar --}}
            <aside class="space-y-4">
                @if($hasResult)
                    @include('website.partials.track-lookup', ['compact' => true])
                @endif

                <template x-if="recent.length">
                    <section class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4">
                        <div class="mb-2.5 flex items-baseline justify-between gap-2">
                            <h2 class="text-[13px] font-bold text-slate-900">Recent orders on this device</h2>
                            <span class="text-[10.5px] text-slate-500">No ID needed</span>
                        </div>
                        <ul class="max-h-[132px] space-y-1.5 overflow-y-auto pr-0.5">
                            <template x-for="o in recent" :key="o.invoice">
                                <li class="flex items-center gap-2 rounded-xl bg-white px-3 py-2 ring-1 ring-slate-100">
                                    <a :href="trackUrl(o)" class="min-w-0 flex-1">
                                        <p class="truncate font-mono text-[12.5px] font-bold text-slate-900" x-text="o.invoice"></p>
                                        <p class="text-[10.5px] text-slate-500">
                                            <span x-text="fmtDate(o.date)"></span>
                                            <template x-if="o.total"><span> · <span x-text="@js($cur) + Math.round(Number(o.total)).toLocaleString()"></span></span></template>
                                        </p>
                                    </a>
                                    <a :href="trackUrl(o)" class="rounded-lg bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-blue-600">Track</a>
                                    <button type="button" @click="forget(o)" class="rounded-md p-0.5 text-slate-300 transition hover:text-rose-600" title="Remove from this device" aria-label="Remove">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </section>
                </template>

                @unless($hasResult)
                    <section class="rounded-2xl bg-slate-900 p-5 text-white">
                        @if($isMember)
                            <h2 class="text-[13px] font-bold">All your orders in one place</h2>
                            <p class="mt-1 text-[12.5px] leading-relaxed text-slate-300">See every order and delivery update in My Account.</p>
                            <a href="{{ route('website.account') }}#recent-orders" class="mt-3.5 inline-flex h-10 w-full items-center justify-center rounded-xl bg-white text-[13px] font-semibold text-slate-900 transition hover:bg-blue-50">Go to My Orders</a>
                        @else
                            <h2 class="text-[13px] font-bold">Ordered as a guest?</h2>
                            <p class="mt-1 text-[12.5px] leading-relaxed text-slate-300">Create an account with the <span class="font-semibold text-white">same mobile number</span> and your orders appear in My Orders.</p>
                            <button type="button" @click="openSignIn('register')" class="mt-3.5 inline-flex h-10 w-full items-center justify-center rounded-xl bg-white text-[13px] font-semibold text-slate-900 transition hover:bg-blue-50">Create account</button>
                        @endif
                    </section>
                @endunless

                <p class="px-1 text-[12px] text-slate-500">
                    Need help?
                    @if($supportPhone !== '')
                        Call <a href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}" class="font-semibold text-slate-700 hover:text-blue-600">{{ $supportPhone }}</a> or
                    @endif
                    <a href="{{ route('website.contact') }}" class="font-semibold text-blue-600 hover:text-blue-700">contact us</a>.
                </p>
            </aside>
        </div>
    </div>
</div>
@endsection
