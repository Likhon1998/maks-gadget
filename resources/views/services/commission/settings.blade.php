@php
    $tk = fn ($n) => 'Tk '.number_format((float) $n, 2);
@endphp

<x-app-layout>
    <x-service-shell section="commission">
        <style>
            .svc-pct::-webkit-outer-spin-button,
            .svc-pct::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
            .svc-pct { -moz-appearance: textfield; appearance: textfield; }
        </style>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800">{{ session('error') }}</div>
        @endif

        <div class="grid gap-3 lg:grid-cols-2">
            <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Gross % (all techs)</h2>
                    <p class="mt-0.5 text-[11px] text-slate-500">One rate for every active technician</p>
                </div>
                <form method="POST" action="{{ route('services.commission.gross') }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <div class="inline-flex h-9 items-stretch overflow-hidden rounded-lg border border-slate-200 bg-white focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-100">
                        <input type="number" name="commission_percent" step="0.01" min="0" max="100" required
                               value="{{ old('commission_percent', $defaultPercent) }}"
                               class="svc-pct h-full w-20 border-0 bg-transparent px-3 text-sm font-bold tabular-nums text-slate-900 focus:ring-0">
                        <span class="inline-flex items-center border-l border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-500">%</span>
                    </div>
                    <label class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-600">
                        <input type="checkbox" name="include_inactive" value="1" class="rounded border-slate-300 text-teal-600">
                        Incl. inactive
                    </label>
                    <button type="submit"
                            class="inline-flex h-9 items-center justify-center rounded-lg bg-slate-900 px-3.5 text-xs font-bold text-white hover:bg-slate-800"
                            data-confirm="Apply this % to selected technicians?"
                            data-confirm-title="Apply gross rate?"
                            data-confirm-ok="Apply">
                        Apply all
                    </button>
                </form>
            </div>

            <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Individual %</h2>
                    <p class="mt-0.5 text-[11px] text-slate-500">Change one technician only</p>
                </div>
                <div class="max-h-60 space-y-1.5 overflow-y-auto pr-0.5">
                    @forelse($technicians as $tech)
                        <form method="POST" action="{{ route('services.commission.technician', $tech) }}"
                              class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50/60 px-2.5 py-1.5">
                            @csrf
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-xs font-semibold text-slate-900">{{ $tech->name }}</div>
                                <div class="text-[10px] text-slate-400">Due {{ $tk($tech->outstanding) }}</div>
                            </div>
                            <div class="inline-flex h-8 shrink-0 items-stretch overflow-hidden rounded-lg border border-slate-200 bg-white">
                                <input type="number" name="commission_percent" step="0.01" min="0" max="100" required
                                       value="{{ number_format((float) $tech->commission_percent, 2, '.', '') }}"
                                       class="svc-pct h-full w-[4.25rem] border-0 bg-transparent px-2 text-center text-xs font-bold tabular-nums text-slate-900 focus:ring-0">
                                <span class="inline-flex items-center border-l border-slate-200 bg-white px-2 text-[11px] font-bold text-slate-400">%</span>
                            </div>
                            <button type="submit"
                                    class="inline-flex h-8 shrink-0 items-center justify-center rounded-lg bg-teal-700 px-3 text-xs font-bold text-white hover:bg-teal-800">
                                Save
                            </button>
                        </form>
                    @empty
                        <p class="text-xs text-slate-500">No technicians.
                            <a href="{{ route('services.technicians.create') }}" class="font-semibold text-indigo-600 hover:underline">Add</a>
                        </p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-3 py-2.5">
                <h2 class="text-sm font-bold text-slate-900">Specific bill %</h2>
                <p class="mt-0.5 text-[11px] text-slate-400">Override one bill · due updates automatically</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[780px] text-sm">
                    <thead>
                        <tr class="bg-slate-50/80 text-[10px] uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-2 text-left font-semibold">Invoice</th>
                            <th class="px-3 py-2 text-left font-semibold">Technician</th>
                            <th class="px-3 py-2 text-right font-semibold">Service</th>
                            <th class="px-3 py-2 text-right font-semibold">Current</th>
                            <th class="w-52 px-3 py-2 text-right font-semibold">New %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentJobs as $job)
                            <tr class="{{ $job->isVoided() ? 'opacity-50' : 'hover:bg-slate-50/70' }}">
                                <td class="px-3 py-2.5">
                                    <div class="font-mono text-xs font-semibold text-indigo-700">{{ $job->invoice_no }}</div>
                                    <div class="mt-0.5 text-[11px] text-slate-400">{{ optional($job->served_at)->format('M j') }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-xs font-medium text-slate-800">{{ $job->technician?->name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-right text-xs tabular-nums">{{ $tk($job->labor_cost) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">
                                    <div class="text-xs font-semibold">{{ $tk($job->commission_amount) }}</div>
                                    <div class="text-[11px] text-slate-400">{{ number_format((float) $job->commission_percent, 1) }}%</div>
                                </td>
                                <td class="px-3 py-2.5">
                                    @unless($job->isVoided())
                                        <form method="POST" action="{{ route('services.commission.job', $job) }}"
                                              class="ml-auto flex w-full max-w-[13rem] flex-col items-end gap-1"
                                              x-data="{ pct: {{ number_format((float) $job->commission_percent, 2, '.', '') }}, labor: {{ (float) $job->labor_cost }} }">
                                            @csrf
                                            <div class="flex w-full items-center gap-2">
                                                <div class="inline-flex h-8 min-w-0 flex-1 items-stretch overflow-hidden rounded-lg border border-slate-200 bg-white focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-100">
                                                    <input type="number" name="commission_percent" step="0.01" min="0" max="100" required
                                                           x-model.number="pct"
                                                           class="svc-pct h-full w-full min-w-0 border-0 bg-transparent px-2.5 text-center text-xs font-bold tabular-nums text-slate-900 focus:ring-0">
                                                    <span class="inline-flex shrink-0 items-center border-l border-slate-200 bg-slate-50 px-2 text-[11px] font-bold text-slate-500">%</span>
                                                </div>
                                                <button type="submit"
                                                        class="inline-flex h-8 shrink-0 items-center justify-center rounded-lg bg-slate-900 px-3 text-xs font-bold text-white hover:bg-slate-800">
                                                    Update
                                                </button>
                                            </div>
                                            <p class="text-[10px] font-semibold tabular-nums text-amber-700">
                                                = Tk <span x-text="(Math.max(0, labor) * Math.max(0, Math.min(100, Number(pct) || 0)) / 100).toFixed(2)"></span>
                                            </p>
                                        </form>
                                    @else
                                        <div class="text-right text-[11px] font-semibold text-rose-600">Voided</div>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-sm text-slate-400">No bills yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-service-shell>
</x-app-layout>
