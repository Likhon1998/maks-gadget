{{-- Per-unit IMEI entry. $units = Alpine expression for the units array, $nameAttr = name attribute for the hidden field. --}}
<div class="rounded-lg border border-slate-200 bg-white overflow-hidden">
    <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-slate-100 bg-slate-50/70">
        <p class="text-[11px] font-semibold text-slate-600">
            IMEI per phone
            <span class="font-normal text-slate-400" x-text="'· ' + imeiFilled({{ $units }}) + ' of ' + {{ $units }}.length + ' unit(s) entered'"></span>
        </p>
        <button type="button" @click="addImeiUnit({{ $units }})"
                class="text-[11px] font-semibold text-orange-700 hover:text-orange-800">+ Add unit</button>
    </div>
    <div class="grid gap-2 px-3 pt-2 text-[10px] font-semibold text-slate-400 uppercase tracking-wide"
         :class="dualImei ? 'grid-cols-[34px_1fr_1fr_22px]' : 'grid-cols-[34px_1fr_22px]'"
         x-show="{{ $units }}.length">
        <span>Unit</span>
        <span>IMEI 1</span>
        <span x-show="dualImei">IMEI 2</span>
        <span></span>
    </div>
    <div class="max-h-72 overflow-y-auto px-3 py-2 space-y-1.5" data-imei-list>
        <template x-for="(unit, u) in ({{ $units }} || [])" :key="unit._k">
            <div class="grid items-center gap-2"
                 :class="dualImei ? 'grid-cols-[34px_1fr_1fr_22px]' : 'grid-cols-[34px_1fr_22px]'">
                <span class="text-[11px] text-slate-400 tabular-nums" x-text="'#' + (u + 1)"></span>
                <input type="text" x-model.trim="unit.a" data-imei-input inputmode="numeric" maxlength="20" autocomplete="off"
                       @keydown.enter.prevent="focusNextImei($event)"
                       @paste="pasteImeis({{ $units }}, u, 'a', $event)"
                       :class="imeiInputClass(unit.a)"
                       class="block w-full rounded-md text-[13px] py-1.5 font-mono focus:ring-orange-500 focus:border-orange-500"
                       placeholder="IMEI 1">
                <input type="text" x-show="dualImei" x-model.trim="unit.b" data-imei-input inputmode="numeric" maxlength="20" autocomplete="off"
                       @keydown.enter.prevent="focusNextImei($event)"
                       @paste="pasteImeis({{ $units }}, u, 'b', $event)"
                       :class="imeiInputClass(unit.b)"
                       class="block w-full rounded-md text-[13px] py-1.5 font-mono focus:ring-orange-500 focus:border-orange-500"
                       placeholder="IMEI 2">
                <button type="button" @click="removeImeiUnit({{ $units }}, u)" title="Remove unit"
                        class="text-slate-300 hover:text-red-500 text-lg leading-none">&times;</button>
            </div>
        </template>
        <p x-show="!{{ $units }}.length" class="text-[11px] text-slate-400 py-1">Set the quantity or click “+ Add unit”.</p>
    </div>
    <p class="px-3 pb-2 text-[10px] text-slate-400">
        Scan or type each phone's IMEI; Enter jumps to the next box. You can paste a column from Excel.
        @if(empty($isEdit)) Opening stock becomes the number of phones with an IMEI. @endif
    </p>
    <input type="hidden" {!! $nameAttr !!} :value="serializeImeis({{ $units }})">
</div>
