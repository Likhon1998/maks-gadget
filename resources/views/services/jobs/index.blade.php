@php
    $tk = fn ($n) => 'Tk '.number_format((float) $n, 2);
@endphp

<x-app-layout>
    <x-service-shell section="bills">
        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">{{ session('success') }}</div>
        @endif

        <form method="GET" class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Invoice, customer, job…"
                   class="h-8 min-w-[160px] flex-1 rounded-lg border-slate-200 text-xs">
            <select name="technician_id" class="h-8 rounded-lg border-slate-200 text-xs">
                <option value="">All technicians</option>
                @foreach($technicians as $t)
                    <option value="{{ $t->id }}" @selected((string) request('technician_id') === (string) $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
            <select name="status" class="h-8 rounded-lg border-slate-200 text-xs">
                <option value="">All statuses</option>
                <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                <option value="voided" @selected(request('status') === 'voided')>Voided</option>
            </select>
            <input type="date" name="start_date" value="{{ request('start_date') }}" class="h-8 rounded-lg border-slate-200 text-xs">
            <input type="date" name="end_date" value="{{ request('end_date') }}" class="h-8 rounded-lg border-slate-200 text-xs">
            <button class="inline-flex h-8 items-center justify-center rounded-lg bg-slate-900 px-3.5 text-xs font-bold text-white hover:bg-slate-800">
                Filter
            </button>
            @if(request()->hasAny(['q','technician_id','status','start_date','end_date']))
                <a href="{{ route('services.jobs.index') }}"
                   class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50/80 text-[10px] uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-2 text-left font-semibold">Invoice</th>
                            <th class="px-3 py-2 text-left font-semibold">Technician</th>
                            <th class="px-3 py-2 text-left font-semibold">Job</th>
                            <th class="px-3 py-2 text-right font-semibold">Total</th>
                            <th class="px-3 py-2 text-right font-semibold">Paid / Due</th>
                            <th class="px-3 py-2 text-right font-semibold">Commission</th>
                            <th class="px-3 py-2 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($jobs as $job)
                            <tr class="{{ $job->isVoided() ? 'bg-rose-50/40' : 'hover:bg-slate-50/70' }}">
                                <td class="px-3 py-2.5">
                                    <div class="font-mono text-xs font-semibold {{ $job->isVoided() ? 'line-through text-rose-500' : 'text-indigo-700' }}">{{ $job->invoice_no }}</div>
                                    <div class="mt-0.5 text-[11px] text-slate-400">{{ optional($job->served_at)->format('M j, g:i A') }}</div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="font-medium text-slate-800 leading-tight">{{ $job->technician?->name }}</div>
                                    <div class="mt-0.5 text-[11px] text-slate-400">{{ $job->customer_name ?: 'Walk-in' }}</div>
                                </td>
                                <td class="px-3 py-2.5 max-w-[240px]">
                                    <div class="truncate text-xs text-slate-700" title="{{ $job->job_description }}">{{ $job->job_description ?: '—' }}</div>
                                    <div class="mt-0.5 text-[11px] text-slate-400">
                                        Shop {{ $tk($job->shop_parts_amount) }}
                                        · Products {{ $tk($job->materials_cost) }}
                                        · Service {{ $tk($job->labor_cost) }}
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ $tk($job->total_amount) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">
                                    <div class="font-semibold">{{ $tk($job->paid_amount) }}</div>
                                    @if((float) ($job->credit_amount ?? 0) > 0)
                                        <div class="text-[11px] font-semibold text-amber-700">Due {{ $tk($job->credit_amount) }}</div>
                                    @else
                                        <div class="text-[11px] text-emerald-700">Settled</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-amber-800">
                                    {{ $tk($job->commission_amount) }}
                                    @php $tb = max(0, round((float) $job->commission_amount - (float) ($job->commission_paid_amount ?? 0), 2)); @endphp
                                    @if($tb > 0)
                                        <div class="text-[11px] font-semibold text-amber-700">Baki {{ $tk($tb) }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <div class="inline-flex items-center justify-end gap-1.5 whitespace-nowrap">
                                        <a href="{{ route('services.jobs.receipt', $job) }}" target="_blank"
                                           class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Receipt
                                        </a>
                                        @unless($job->isVoided())
                                            <form method="POST" action="{{ route('services.jobs.void', $job) }}" class="inline"
                                                  data-confirm="Void {{ $job->invoice_no }}?"
                                                  data-confirm-title="Void service bill?"
                                                  data-confirm-ok="Void"
                                                  data-confirm-tone="danger">
                                                @csrf
                                                <button type="submit"
                                                        class="inline-flex h-8 items-center justify-center rounded-lg border border-rose-200 bg-white px-2.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                                    Void
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-8 text-center text-sm text-slate-500">No service bills found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($jobs->hasPages())
                <div class="border-t border-slate-100 px-3 py-2">{{ $jobs->links() }}</div>
            @endif
        </div>
    </x-service-shell>
</x-app-layout>
