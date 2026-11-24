@props(['section' => 'payments'])

@php
    $tabs = [
        'payments' => ['label' => 'Payments', 'route' => 'services.payments.index'],
        'technicians' => ['label' => 'Technicians', 'route' => 'services.technicians.index'],
        'bills' => ['label' => 'Bills', 'route' => 'services.jobs.index'],
        'report' => ['label' => 'Report', 'route' => 'reports.service_monthly'],
        'commission' => ['label' => 'Commission', 'route' => 'services.commission.settings'],
    ];
@endphp

<div class="min-w-0 space-y-3 pb-6">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
        <div class="min-w-0">
            <h1 class="text-xl font-bold tracking-tight text-slate-900 leading-tight">Service</h1>
            <p class="mt-0.5 text-xs text-slate-500">Pay · technicians · bills · report · rates</p>
        </div>
        <a href="{{ route('pos.index') }}"
           onclick="return window.launchPosTerminal ? window.launchPosTerminal(this.href) : true"
           class="inline-flex h-8 items-center justify-center rounded-lg bg-slate-900 px-3.5 text-xs font-bold text-white hover:bg-slate-800 shrink-0">
            Open POS
        </a>
    </div>

    <nav class="flex w-full overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
        <div class="flex min-w-full sm:min-w-0 sm:w-full gap-0.5">
            @foreach($tabs as $key => $tab)
                <a href="{{ route($tab['route']) }}"
                   @class([
                       'inline-flex h-8 flex-1 items-center justify-center whitespace-nowrap rounded-lg px-3 text-xs font-semibold transition',
                       'bg-slate-900 text-white shadow-sm' => $section === $key,
                       'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => $section !== $key,
                   ])>
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    </nav>

    {{ $slot }}
</div>
