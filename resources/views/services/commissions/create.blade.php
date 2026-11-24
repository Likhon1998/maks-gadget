@php
    $tk = fn ($n) => 'Tk '.number_format((float) $n, 2);
    $oldRange = old('pay_range', $range ?? 'all');
    $oldApply = old('apply_advance', $applyAdvanceDefault ?? false);
@endphp

<x-app-layout>
    <div class="mx-auto max-w-5xl pb-4"
         x-data="{
             amount: {{ old('amount', $suggested > 0 ? $suggested : ($outstanding > 0 ? $outstanding : 0)) }},
             dueAll: {{ $outstanding }},
             advanceOnHand: {{ $advance }},
             applyAdvance: {{ $oldApply ? 'true' : 'false' }},
             range: @js($oldRange),
             ranges: {
                 today: { earned: {{ $periodStats['today']['earned'] }}, suggested: {{ $periodStats['today']['suggested'] }}, label: 'Today' },
                 '7d': { earned: {{ $periodStats['7d']['earned'] }}, suggested: {{ $periodStats['7d']['suggested'] }}, label: '7 days' },
                 month: { earned: {{ $periodStats['month']['earned'] }}, suggested: {{ $periodStats['month']['suggested'] }}, label: 'Month' },
                 '30d': { earned: {{ $periodStats['30d']['earned'] }}, suggested: {{ $periodStats['30d']['suggested'] }}, label: '30 days' },
                      all: { earned: {{ $earned }}, suggested: {{ $outstanding }}, label: 'All time' }
             },
             setRange(key) {
                 this.range = key;
                 this.amount = this.cashSuggestedFor(key);
             },
             advanceCover() {
                 if (!this.applyAdvance) return 0;
                 return Math.min(this.advanceOnHand, this.dueAll);
             },
             dueAfterAdvance() {
                 return Math.max(0, Math.round((this.dueAll - this.advanceCover()) * 100) / 100);
             },
             cashSuggestedFor(key) {
                 const period = Number(this.ranges[key]?.suggested || 0);
                 const dueLeft = this.dueAfterAdvance();
                 return Math.max(0, Math.min(period || dueLeft, dueLeft));
             },
             refreshSuggested() {
                 this.amount = this.cashSuggestedFor(this.range);
             },
             towardDue() {
                 return Math.min(Number(this.amount) || 0, this.dueAfterAdvance());
             },
             newAdvance() {
                 return Math.max(0, Math.round(((Number(this.amount) || 0) - this.towardDue()) * 100) / 100);
             },
             remainingDue() {
                 return Math.max(0, Math.round((this.dueAfterAdvance() - this.towardDue()) * 100) / 100);
             },
             money(n) {
                 return 'Tk ' + Number(n || 0).toFixed(2);
             }
         }">
        <form method="POST" action="{{ route('services.commissions.store', $technician) }}" class="space-y-2.5">
            @csrf
            <input type="hidden" name="pay_range" :value="range">
            <input type="hidden" name="apply_advance" :value="applyAdvance ? 1 : 0">

            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                    <a href="{{ route('services.payments.index', ['range' => $oldRange]) }}"
                       class="inline-flex h-8 items-center text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                        ← Payments
                    </a>
                    <div class="hidden h-4 w-px bg-slate-200 sm:block"></div>
                    <div class="min-w-0">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-amber-700">Payout</span>
                        <h1 class="text-lg font-bold leading-none text-slate-900">{{ $technician->name }}</h1>
                    </div>
                </div>
                <button type="submit"
                        class="inline-flex h-9 items-center justify-center rounded-lg bg-slate-900 px-5 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
                    Record payment
                </button>
            </div>

            @if (session('error'))
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-800">{{ session('error') }}</div>
            @endif

            <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase text-slate-400">All earned</p>
                    <p class="mt-0.5 text-sm font-bold tabular-nums text-slate-900">{{ $tk($earned) }}</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                    <p class="text-[10px] font-bold uppercase text-amber-700">Tech baki</p>
                    <p class="mt-0.5 text-sm font-bold tabular-nums text-amber-950">{{ $tk($outstanding) }}</p>
                </div>
                <div class="rounded-lg border border-teal-200 bg-teal-50 px-3 py-2">
                    <p class="text-[10px] font-bold uppercase text-teal-700">Advance</p>
                    <p class="mt-0.5 text-sm font-bold tabular-nums text-teal-950">{{ $tk($advance) }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2">
                    <p class="text-[10px] font-bold uppercase text-emerald-700">Paid</p>
                    <p class="mt-0.5 text-sm font-bold tabular-nums text-emerald-950">{{ $tk($paid) }}</p>
                </div>
            </div>

            <div class="space-y-2.5 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
                    <div class="inline-flex flex-1 flex-wrap gap-0.5 rounded-lg border border-slate-200 bg-slate-50 p-0.5">
                        <template x-for="key in ['today','7d','month','30d','all']" :key="key">
                            <button type="button" @click="setRange(key)"
                                    class="inline-flex h-8 flex-1 items-center justify-center rounded-md px-2 text-xs font-semibold transition"
                                    :class="range === key ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white'"
                                    x-text="ranges[key].label"></button>
                        </template>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <div class="min-w-[120px] rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5">
                            <p class="text-[9px] font-bold uppercase text-slate-400">Period</p>
                            <p class="text-xs font-bold tabular-nums" x-text="money(ranges[range].earned)"></p>
                        </div>
                        <div class="min-w-[120px] rounded-lg border border-amber-100 bg-amber-50 px-2.5 py-1.5">
                            <p class="text-[9px] font-bold uppercase text-amber-700">Cash suggest</p>
                            <p class="text-xs font-bold tabular-nums text-amber-950" x-text="money(cashSuggestedFor(range))"></p>
                        </div>
                    </div>
                </div>

                <div x-show="advanceOnHand > 0.009" x-cloak
                     class="rounded-lg border border-teal-200 bg-teal-50/80 px-3 py-2.5">
                    <p class="text-xs font-bold text-teal-900">
                        Advance on hand: <span class="tabular-nums" x-text="money(advanceOnHand)"></span>
                    </p>
                    <p class="mt-0.5 text-[11px] text-teal-800">Shop owner decides: deduct from this due, or keep for later.</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <button type="button"
                                @click="applyAdvance = true; refreshSuggested()"
                                class="inline-flex h-8 items-center rounded-lg px-3 text-xs font-bold transition"
                                :class="applyAdvance ? 'bg-teal-700 text-white' : 'border border-teal-200 bg-white text-teal-800 hover:bg-teal-100'">
                            Deduct advance now
                        </button>
                        <button type="button"
                                @click="applyAdvance = false; refreshSuggested()"
                                class="inline-flex h-8 items-center rounded-lg px-3 text-xs font-bold transition"
                                :class="!applyAdvance ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'">
                            Keep advance
                        </button>
                    </div>
                    <p class="mt-1.5 text-[11px] font-semibold text-teal-900" x-show="applyAdvance">
                        Will cover <span class="tabular-nums" x-text="money(advanceCover())"></span> of due · cash needed
                        <span class="tabular-nums" x-text="money(dueAfterAdvance())"></span>
                    </p>
                    <p class="mt-1.5 text-[11px] font-semibold text-slate-700" x-show="!applyAdvance">
                        Advance stays · pay due in cash as usual
                    </p>
                </div>

                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Pay amount *</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] font-bold text-slate-400">Tk</span>
                            <input type="number" name="amount" step="0.01" min="0" required x-model.number="amount"
                                   class="h-9 w-full rounded-lg border-slate-200 pl-8 text-sm font-bold tabular-nums">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Commission % *</label>
                        <div class="relative">
                            <input type="number" name="commission_percent" step="0.01" min="0" max="100" required
                                   value="{{ old('commission_percent', number_format((float) $technician->commission_percent, 2, '.', '')) }}"
                                   class="h-9 w-full rounded-lg border-slate-200 pr-7 text-sm font-bold tabular-nums">
                            <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] font-bold text-slate-400">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Paid on</label>
                        <input type="date" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" required
                               class="h-9 w-full rounded-lg border-slate-200 text-xs">
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Month</label>
                        <input type="month" name="period_month" value="{{ old('period_month', now()->format('Y-m')) }}"
                               class="h-9 w-full rounded-lg border-slate-200 text-xs">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div class="rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1.5">
                        <p class="text-[9px] font-bold uppercase text-slate-400">Toward due</p>
                        <p class="text-xs font-bold tabular-nums text-slate-900" x-text="money(towardDue())"></p>
                    </div>
                    <div class="rounded-lg border border-amber-100 bg-amber-50 px-2.5 py-1.5">
                        <p class="text-[9px] font-bold uppercase text-amber-700">Still due</p>
                        <p class="text-xs font-bold tabular-nums text-amber-950" x-text="money(remainingDue())"></p>
                    </div>
                    <div class="rounded-lg border border-teal-100 bg-teal-50 px-2.5 py-1.5">
                        <p class="text-[9px] font-bold uppercase text-teal-700">New advance</p>
                        <p class="text-xs font-bold tabular-nums text-teal-950" x-text="money(newAdvance())"></p>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Note</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Cash / bKash…"
                               class="h-9 w-full rounded-lg border-slate-200 text-xs">
                        <input type="hidden" name="reference" value="{{ old('reference') }}">
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-1.5 pb-px">
                        <button type="button"
                                class="inline-flex h-9 items-center rounded-lg border border-amber-200 bg-amber-50 px-2.5 text-xs font-semibold text-amber-900 hover:bg-amber-100"
                                @click="refreshSuggested()">Suggested</button>
                        <button type="button"
                                class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                @click="setRange('all'); amount = dueAfterAdvance()">All due</button>
                        <button type="button"
                                class="inline-flex h-9 items-center rounded-lg border border-teal-200 bg-teal-50 px-2.5 text-xs font-semibold text-teal-900 hover:bg-teal-100"
                                @click="range = 'all'; amount = Math.round((dueAfterAdvance() + 500) * 100) / 100">+ Tk500</button>
                    </div>
                </div>

                <p class="text-[11px] text-slate-500">
                    Pay less → leftover stays as due · Pay more → extra becomes advance · Advance deduct only if you choose above.
                </p>
            </div>
        </form>
    </div>
</x-app-layout>
