@php
    $tk = fn ($n) => 'Tk '.number_format((float) $n, 2);
@endphp

<x-app-layout>
    <x-service-shell section="technicians">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-slate-500">Default rate % · override under Commission</p>
            <a href="{{ route('services.technicians.create') }}"
               class="inline-flex h-8 items-center justify-center rounded-lg bg-indigo-600 px-3.5 text-xs font-bold text-white hover:bg-indigo-700 shadow-sm">
                + Add technician
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800">{{ session('error') }}</div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50/80 text-[10px] uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-2 text-left font-semibold">Technician</th>
                            <th class="px-3 py-2 text-left font-semibold">Rate</th>
                            <th class="px-3 py-2 text-right font-semibold">Jobs</th>
                            <th class="px-3 py-2 text-right font-semibold">Earned</th>
                            <th class="px-3 py-2 text-right font-semibold">Paid</th>
                            <th class="px-3 py-2 text-right font-semibold">Baki</th>
                            <th class="px-3 py-2 text-right font-semibold">Advance</th>
                            <th class="px-3 py-2 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($technicians as $tech)
                            <tr class="{{ $tech->is_active ? 'hover:bg-slate-50/70' : 'opacity-55' }}">
                                <td class="px-3 py-2.5">
                                    <div class="font-semibold text-slate-900 leading-tight">{{ $tech->name }}</div>
                                    <div class="mt-0.5 text-[11px] text-slate-400">
                                        {{ $tech->phone ?: 'No phone' }}
                                        @unless($tech->is_active)
                                            · <span class="font-semibold text-rose-600">Inactive</span>
                                        @endunless
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 font-bold tabular-nums text-indigo-700">{{ number_format((float) $tech->commission_percent, 1) }}%</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ $tech->completed_jobs_count }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-slate-800">{{ $tk($tech->earned) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums text-emerald-700">{{ $tk($tech->paid) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-semibold {{ $tech->outstanding > 0 ? 'text-amber-700' : 'text-slate-300' }}">{{ $tk($tech->outstanding) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums font-semibold {{ $tech->advance > 0 ? 'text-teal-700' : 'text-slate-300' }}">{{ $tk($tech->advance) }}</td>
                                <td class="px-3 py-2.5 text-right">
                                    <div class="inline-flex items-center justify-end gap-1.5 whitespace-nowrap">
                                        @if($tech->outstanding > 0)
                                            <a href="{{ route('services.commissions.create', $tech) }}"
                                               class="inline-flex h-8 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-2.5 text-xs font-semibold text-amber-900 hover:bg-amber-100">
                                                Pay
                                            </a>
                                        @endif
                                        <a href="{{ route('services.technicians.edit', $tech) }}"
                                           class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('services.technicians.destroy', $tech) }}" class="inline"
                                              data-confirm="Remove or deactivate {{ $tech->name }}?"
                                              data-confirm-title="Remove technician?"
                                              data-confirm-ok="Confirm"
                                              data-confirm-tone="danger">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex h-8 items-center justify-center rounded-lg border border-rose-200 bg-white px-2.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-sm text-slate-500">
                                    No technicians yet.
                                    <a href="{{ route('services.technicians.create') }}" class="font-semibold text-indigo-600 hover:underline">Add one</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-service-shell>
</x-app-layout>
