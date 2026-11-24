@php
    $tk = fn ($n) => 'Tk '.number_format((float) $n, 2);
    $rangeLinks = [
        'today' => 'Today',
        '7d' => '7 days',
        'month' => 'Month',
        '30d' => '30 days',
        'all' => 'All due',
    ];
@endphp

<x-app-layout>
    <x-service-shell section="payments">
        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold text-slate-500 shrink-0">Pay for</span>
            <div class="inline-flex flex-wrap gap-0.5 rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                @foreach($rangeLinks as $key => $label)
                    <a href="{{ route('services.payments.index', ['range' => $key]) }}"
                       @class([
                           'inline-flex h-7 items-center justify-center rounded-lg px-2.5 text-xs font-semibold transition',
                           'bg-slate-900 text-white shadow-sm' => ($range ?? 'all') === $key,
                           'text-slate-600 hover:bg-slate-50' => ($range ?? 'all') !== $key,
                       ])>{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-2">
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2.5">
                <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-700">{{ $rangeLabel ?? 'Period' }}</p>
                <p class="mt-1 text-base font-bold tabular-nums leading-none text-indigo-950">{{ $tk($totals['period_earned'] ?? 0) }}</p>
                <p class="mt-1 text-[10px] font-semibold text-indigo-600">Suggest {{ $tk($totals['period_suggested'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Today</p>
                <p class="mt-1 text-base font-bold tabular-nums leading-none text-slate-900">{{ $tk($totals['earned_today']) }}</p>
                <p class="mt-1 text-[10px] text-slate-400">{{ $totals['jobs_today'] }} bill(s)</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5">
                <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Paid</p>
                <p class="mt-1 text-base font-bold tabular-nums leading-none text-emerald-950">{{ $tk($totals['paid']) }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5">
                <p class="text-[10px] font-bold uppercase tracking-wide text-amber-700">Tech baki</p>
                <p class="mt-1 text-base font-bold tabular-nums leading-none text-amber-950">{{ $tk($totals['outstanding']) }}</p>
            </div>
            <div class="rounded-xl border border-teal-200 bg-teal-50 px-3 py-2.5 col-span-2 lg:col-span-1">
                <p class="text-[10px] font-bold uppercase tracking-wide text-teal-700">Advance</p>
                <p class="mt-1 text-base font-bold tabular-nums leading-none text-teal-950">{{ $tk($totals['advance']) }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2.5">
                <h2 class="text-sm font-bold text-slate-900">Pay technicians</h2>
                <p class="text-[11px] text-slate-400">{{ $rangeLabel ?? 'All due' }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-sm">
                    <thead>
                        <tr class="bg-slate-50/80 text-[10px] uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-2 text-left font-semibold">Technician</th>
                            <th class="px-3 py-2 text-right font-semibold">Period</th>
                            <th class="px-3 py-2 text-right font-semibold">Suggested</th>
                            <th class="px-3 py-2 text-right font-semibold">All earned</th>
                            <th class="px-3 py-2 text-right font-semibold">Due</th>
                            <th class="px-3 py-2 text-right font-semibold">Advance</th>
                            <th class="px-3 py-2 text-right font-semibold w-32">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($technicians as $tech)
                            @php
                                $canPayPeriod = ($tech->period_suggested ?? 0) > 0;
                                $canPayDue = ($tech->outstanding ?? 0) > 0;
                            @endphp
                            <tr class="{{ $tech->is_active ? 'hover:bg-slate-50/70' : 'opacity-55' }}">
                                <td class="px-3 py-2.5">
                                    <div class="text-sm font-semibold text-slate-900 leading-tight">{{ $tech->name }}</div>
                                    <div class="mt-0.5 text-[11px] text-slate-400">
                                        Today {{ $tk($tech->earned_today) }} · {{ $tech->jobs_today }} job(s)
                                        @unless($tech->is_active) · Inactive @endunless
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-indigo-800">{{ $tk($tech->period_earned) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-bold {{ $canPayPeriod ? 'text-amber-700' : 'text-slate-300' }}">{{ $tk($tech->period_suggested ?? 0) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ $tk($tech->earned) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-bold {{ $canPayDue ? 'text-amber-700' : 'text-slate-300' }}">{{ $tk($tech->outstanding) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-bold {{ $tech->advance > 0 ? 'text-teal-700' : 'text-slate-300' }}">{{ $tk($tech->advance) }}</td>
                                <td class="px-3 py-2.5 text-right">
                                    @if($canPayPeriod || $canPayDue)
                                        <a href="{{ route('services.commissions.create', ['technician' => $tech, 'range' => $range ?? 'all']) }}"
                                           class="inline-flex h-8 items-center justify-center whitespace-nowrap rounded-lg bg-amber-600 px-3.5 text-xs font-bold leading-none text-white shadow-sm hover:bg-amber-700">
                                            {{ $canPayPeriod ? 'Pay period' : 'Pay due' }}
                                        </a>
                                    @else
                                        <span class="text-xs font-semibold text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-8 text-center text-sm text-slate-500">
                                    No technicians yet.
                                    <a href="{{ route('services.technicians.create') }}" class="font-semibold text-indigo-600 hover:underline">Add one</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($technicians->isNotEmpty())
                        <tfoot>
                            <tr class="border-t border-slate-200 bg-slate-50 text-xs font-bold tabular-nums">
                                <td class="px-3 py-2.5 text-slate-700">Total</td>
                                <td class="px-3 py-2.5 text-right text-indigo-800">{{ $tk($totals['period_earned'] ?? 0) }}</td>
                                <td class="px-3 py-2.5 text-right text-amber-700">{{ $tk($totals['period_suggested'] ?? 0) }}</td>
                                <td class="px-3 py-2.5 text-right text-slate-800">{{ $tk($totals['earned']) }}</td>
                                <td class="px-3 py-2.5 text-right text-amber-700">{{ $tk($totals['outstanding']) }}</td>
                                <td class="px-3 py-2.5 text-right text-teal-700">{{ $tk($totals['advance']) }}</td>
                                <td class="px-3 py-2.5"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2.5 text-sm font-bold text-slate-900">Recent payouts</div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead>
                        <tr class="bg-slate-50/80 text-[10px] uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-2 text-left font-semibold">Date</th>
                            <th class="px-3 py-2 text-left font-semibold">Technician</th>
                            <th class="px-3 py-2 text-right font-semibold">Amount</th>
                            <th class="px-3 py-2 text-left font-semibold">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentPayments as $p)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-3 py-2.5 text-xs text-slate-600 whitespace-nowrap">{{ optional($p->paid_on)->format('M j, Y') }}</td>
                                <td class="px-3 py-2.5 font-semibold text-slate-800">{{ $p->technician?->name }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-bold text-emerald-700">{{ $tk($p->amount) }}</td>
                                <td class="px-3 py-2.5 text-xs text-slate-500 truncate max-w-sm">{{ $p->notes ?: ($p->reference ?: '—') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-8 text-center text-sm text-slate-400">No payouts yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-service-shell>
</x-app-layout>
