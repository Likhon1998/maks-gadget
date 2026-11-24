@php
    $money = fn ($n) => 'Tk '.number_format((float) $n, 2);
    $tab = request('tab', 'summary');
    if (! in_array($tab, ['summary', 'technician', 'service', 'ledger'], true)) {
        $tab = 'summary';
    }
@endphp

<x-app-layout>
    <x-service-shell section="report">
    <div class="space-y-3"
         x-data="{
            tab: @js($tab),
            setTab(name) {
                this.tab = name;
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', name);
                    window.history.replaceState({}, '', url);
                } catch (e) {}
            }
         }">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-slate-500">{{ $start->format('F Y') }} · billed · paid · due · commission</p>
            <form method="GET" class="inline-flex items-center" x-ref="monthForm">
                <input type="hidden" name="tab" :value="tab">
                <input type="month" name="month" value="{{ $month }}"
                       class="h-8 rounded-lg border-slate-200 text-xs"
                       @change="$refs.monthForm.submit()">
            </form>
        </div>

        <div class="flex w-full overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
            <div class="flex min-w-full gap-0.5 sm:w-full sm:min-w-0">
                <button type="button" @click="setTab('summary')"
                        class="inline-flex h-8 flex-1 items-center justify-center whitespace-nowrap rounded-lg px-3 text-xs font-semibold transition"
                        :class="tab === 'summary' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'">
                    Summary
                </button>
                <button type="button" @click="setTab('technician')"
                        class="inline-flex h-8 flex-1 items-center justify-center whitespace-nowrap rounded-lg px-3 text-xs font-semibold transition"
                        :class="tab === 'technician' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'">
                    By tech
                </button>
                <button type="button" @click="setTab('service')"
                        class="inline-flex h-8 flex-1 items-center justify-center whitespace-nowrap rounded-lg px-3 text-xs font-semibold transition"
                        :class="tab === 'service' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'">
                    By job type
                </button>
                <button type="button" @click="setTab('ledger')"
                        class="inline-flex h-8 flex-1 items-center justify-center whitespace-nowrap rounded-lg px-3 text-xs font-semibold transition"
                        :class="tab === 'ledger' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'">
                    All bills
                </button>
            </div>
        </div>

        {{-- Month summary --}}
        <div x-show="tab === 'summary'" x-cloak class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2.5">
                <h2 class="text-sm font-bold text-slate-900">Month summary</h2>
                <span class="text-[11px] font-semibold tabular-nums text-slate-400">{{ $totals['jobs'] }} bill(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-[12.5px]">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-right font-semibold">Billed</th>
                            <th class="px-3 py-2 text-right font-semibold">Collected</th>
                            <th class="px-3 py-2 text-right font-semibold">Customer due</th>
                            <th class="px-3 py-2 text-right font-semibold">Shop parts</th>
                            <th class="px-3 py-2 text-right font-semibold">Shop profit</th>
                            <th class="px-3 py-2 text-right font-semibold">Products</th>
                            <th class="px-3 py-2 text-right font-semibold">Service</th>
                            <th class="px-3 py-2 text-right font-semibold">Comm. earned</th>
                            <th class="px-3 py-2 text-right font-semibold">Comm. paid</th>
                            <th class="px-3 py-2 text-right font-semibold">Tech baki</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="font-semibold tabular-nums">
                            <td class="px-3 py-2 text-right text-slate-900">{{ $money($totals['billed']) }}</td>
                            <td class="px-3 py-2 text-right text-emerald-700">{{ $money($totals['paid']) }}</td>
                            <td class="px-3 py-2 text-right {{ $totals['customer_due'] > 0 ? 'text-amber-700' : 'text-slate-500' }}">{{ $money($totals['customer_due']) }}</td>
                            <td class="px-3 py-2 text-right text-slate-800">{{ $money($totals['shop_parts']) }}</td>
                            <td class="px-3 py-2 text-right text-emerald-700">{{ $money($totals['shop_parts_profit']) }}</td>
                            <td class="px-3 py-2 text-right text-slate-700">{{ $money($totals['outside']) }}</td>
                            <td class="px-3 py-2 text-right text-slate-800">{{ $money($totals['labor']) }}</td>
                            <td class="px-3 py-2 text-right text-amber-900">{{ $money($totals['commission_earned']) }}</td>
                            <td class="px-3 py-2 text-right text-slate-800">{{ $money($totals['commission_paid']) }}</td>
                            <td class="px-3 py-2 text-right {{ $totals['outstanding'] > 0 ? 'text-rose-700' : 'text-slate-500' }}">{{ $money($totals['outstanding']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Technician-wise --}}
        <div x-show="tab === 'technician'" x-cloak class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2.5">
                <h2 class="text-sm font-bold text-slate-900">Technician-wise</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-[12.5px]">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Technician</th>
                            <th class="px-3 py-2 text-right font-semibold">Jobs</th>
                            <th class="px-3 py-2 text-right font-semibold">Billed</th>
                            <th class="px-3 py-2 text-right font-semibold">Collected</th>
                            <th class="px-3 py-2 text-right font-semibold">Cust. due</th>
                            <th class="px-3 py-2 text-right font-semibold">Shop</th>
                            <th class="px-3 py-2 text-right font-semibold">Profit</th>
                            <th class="px-3 py-2 text-right font-semibold">Products</th>
                            <th class="px-3 py-2 text-right font-semibold">Service</th>
                            <th class="px-3 py-2 text-right font-semibold">Earned</th>
                            <th class="px-3 py-2 text-right font-semibold">Paid</th>
                            <th class="px-3 py-2 text-right font-semibold">Tech baki</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($byTechnician as $row)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-3 py-2 font-semibold text-slate-800 whitespace-nowrap">
                                    {{ $row['name'] }}
                                    <a href="{{ route('services.commissions.create', $row['technician_id']) }}"
                                       class="ml-1.5 inline-flex h-6 items-center rounded-md border border-amber-200 bg-amber-50 px-1.5 text-[10px] font-bold text-amber-900 hover:bg-amber-100">Pay</a>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ $row['jobs'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-900">{{ $money($row['billed']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-emerald-700">{{ $money($row['paid']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums {{ $row['customer_due'] > 0 ? 'text-amber-700 font-semibold' : 'text-slate-400' }}">{{ $money($row['customer_due']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ $money($row['shop_parts']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-emerald-700">{{ $money($row['shop_parts_profit']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ $money($row['outside']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-800">{{ $money($row['labor']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-amber-900">{{ $money($row['commission_earned']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ $money($row['commission_paid']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold {{ $row['commission_outstanding'] > 0 ? 'text-rose-700' : 'text-slate-400' }}">{{ $money($row['commission_outstanding']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="px-3 py-6 text-center text-xs text-slate-400">No service bills this month.</td></tr>
                        @endforelse
                    </tbody>
                    @if($byTechnician->isNotEmpty())
                        <tfoot class="bg-slate-50 border-t border-slate-200 text-[11px] font-bold tabular-nums">
                            <tr>
                                <td class="px-3 py-2 text-left text-slate-700">Total</td>
                                <td class="px-3 py-2 text-right">{{ $totals['jobs'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $money($totals['billed']) }}</td>
                                <td class="px-3 py-2 text-right text-emerald-700">{{ $money($totals['paid']) }}</td>
                                <td class="px-3 py-2 text-right text-amber-700">{{ $money($totals['customer_due']) }}</td>
                                <td class="px-3 py-2 text-right">{{ $money($totals['shop_parts']) }}</td>
                                <td class="px-3 py-2 text-right text-emerald-700">{{ $money($totals['shop_parts_profit']) }}</td>
                                <td class="px-3 py-2 text-right">{{ $money($totals['outside']) }}</td>
                                <td class="px-3 py-2 text-right">{{ $money($totals['labor']) }}</td>
                                <td class="px-3 py-2 text-right text-amber-900">{{ $money($totals['commission_earned']) }}</td>
                                <td class="px-3 py-2 text-right">{{ $money($totals['commission_paid']) }}</td>
                                <td class="px-3 py-2 text-right text-rose-700">{{ $money($totals['outstanding']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- Service-wise --}}
        <div x-show="tab === 'service'" x-cloak class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2.5">
                <h2 class="text-sm font-bold text-slate-900">Service-wise</h2>
                <span class="text-[11px] tabular-nums text-slate-400">{{ $byService->count() }} type(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-[12.5px]">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Job type</th>
                            <th class="px-3 py-2 text-right font-semibold">Jobs</th>
                            <th class="px-3 py-2 text-right font-semibold">Billed</th>
                            <th class="px-3 py-2 text-right font-semibold">Shop parts</th>
                            <th class="px-3 py-2 text-right font-semibold">Products</th>
                            <th class="px-3 py-2 text-right font-semibold">Service</th>
                            <th class="px-3 py-2 text-right font-semibold">Commission</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($byService as $row)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-3 py-2 text-slate-800 font-medium max-w-[320px] truncate" title="{{ $row['label'] }}">{{ \Illuminate\Support\Str::title($row['label']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ $row['jobs'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-900 font-semibold">{{ $money($row['billed']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-700">{{ $money($row['shop_parts']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-slate-600">{{ $money($row['outside']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $money($row['labor']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-amber-800">{{ $money($row['commission']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-6 text-center text-xs text-slate-400">No jobs to group.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Bill ledger --}}
        <div x-show="tab === 'ledger'" x-cloak class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2.5">
                <h2 class="text-sm font-bold text-slate-900">Bill ledger · {{ $start->format('M Y') }}</h2>
                <span class="text-[11px] tabular-nums text-slate-400">{{ $jobs->count() }} rows</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-[12px]">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-2.5 py-2 text-left font-semibold">Invoice</th>
                            <th class="px-2.5 py-2 text-left font-semibold">Date</th>
                            <th class="px-2.5 py-2 text-left font-semibold">Tech</th>
                            <th class="px-2.5 py-2 text-left font-semibold">Job</th>
                            <th class="px-2.5 py-2 text-right font-semibold">Shop</th>
                            <th class="px-2.5 py-2 text-right font-semibold">Out</th>
                            <th class="px-2.5 py-2 text-right font-semibold">Service</th>
                            <th class="px-2.5 py-2 text-right font-semibold">Total</th>
                            <th class="px-2.5 py-2 text-right font-semibold">Paid</th>
                            <th class="px-2.5 py-2 text-right font-semibold">Due</th>
                            <th class="px-2.5 py-2 text-right font-semibold">Comm</th>
                            <th class="px-2.5 py-2 text-right font-semibold">Tech baki</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($jobs as $job)
                            @php
                                $due = (float) ($job->credit_amount ?? 0);
                                $comm = (float) $job->commission_amount;
                                $commPaid = (float) ($job->commission_paid_amount ?? 0);
                                $techBaki = max(0, round($comm - $commPaid, 2));
                            @endphp
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-2.5 py-1.5">
                                    <a href="{{ route('services.jobs.receipt', $job) }}" target="_blank"
                                       class="font-mono font-semibold text-indigo-700 hover:underline">{{ $job->invoice_no }}</a>
                                </td>
                                <td class="px-2.5 py-1.5 text-slate-500 whitespace-nowrap">{{ optional($job->served_at)->format('M j, g:ia') }}</td>
                                <td class="px-2.5 py-1.5 text-slate-800 whitespace-nowrap">{{ $job->technician?->name }}</td>
                                <td class="px-2.5 py-1.5 text-slate-600 max-w-[180px] truncate" title="{{ $job->job_description }}">{{ $job->job_description }}</td>
                                <td class="px-2.5 py-1.5 text-right tabular-nums">{{ $money($job->shop_parts_amount) }}</td>
                                <td class="px-2.5 py-1.5 text-right tabular-nums text-slate-600">{{ $money($job->materials_cost) }}</td>
                                <td class="px-2.5 py-1.5 text-right tabular-nums">{{ $money($job->labor_cost) }}</td>
                                <td class="px-2.5 py-1.5 text-right tabular-nums font-semibold">{{ $money($job->total_amount) }}</td>
                                <td class="px-2.5 py-1.5 text-right tabular-nums text-emerald-700">{{ $money($job->paid_amount) }}</td>
                                <td class="px-2.5 py-1.5 text-right tabular-nums {{ $due > 0 ? 'text-amber-700 font-semibold' : 'text-slate-300' }}">{{ $money($due) }}</td>
                                <td class="px-2.5 py-1.5 text-right tabular-nums text-amber-900">{{ $money($comm) }}</td>
                                <td class="px-2.5 py-1.5 text-right tabular-nums {{ $techBaki > 0 ? 'text-rose-700 font-semibold' : 'text-slate-300' }}">{{ $money($techBaki) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="px-3 py-6 text-center text-xs text-slate-400">No completed service bills in this month.</td></tr>
                        @endforelse
                    </tbody>
                    @if($jobs->isNotEmpty())
                        <tfoot class="bg-slate-50 border-t border-slate-200 text-[11.5px] font-bold tabular-nums">
                            <tr>
                                <td class="px-2.5 py-2" colspan="4">Totals</td>
                                <td class="px-2.5 py-2 text-right">{{ $money($totals['shop_parts']) }}</td>
                                <td class="px-2.5 py-2 text-right">{{ $money($totals['outside']) }}</td>
                                <td class="px-2.5 py-2 text-right">{{ $money($totals['labor']) }}</td>
                                <td class="px-2.5 py-2 text-right">{{ $money($totals['billed']) }}</td>
                                <td class="px-2.5 py-2 text-right text-emerald-700">{{ $money($totals['paid']) }}</td>
                                <td class="px-2.5 py-2 text-right text-amber-700">{{ $money($totals['customer_due']) }}</td>
                                <td class="px-2.5 py-2 text-right text-amber-900">{{ $money($totals['commission_earned']) }}</td>
                                <td class="px-2.5 py-2 text-right text-rose-700">{{ $money($totals['commission_earned'] - $totals['commission_paid_on_jobs']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
    </x-service-shell>
</x-app-layout>
