{{-- Interactive Feature / Specification table editor (Alpine) --}}
@php
    $specInitial = old('specifications');
    if (is_string($specInitial)) {
        $decoded = json_decode($specInitial, true);
        $specInitial = is_array($decoded) ? $decoded : null;
    }
    if (! is_array($specInitial)) {
        $specInitial = isset($product) && $product
            ? $product->specificationEditorState()
            : \App\Support\ProductSpecTable::emptyEditor();
    }
    $specInitial = \App\Support\ProductSpecTable::normalize($specInitial)
        ?? \App\Support\ProductSpecTable::emptyEditor();
    // Keep at least one blank row in the editor for convenience
    if (count($specInitial['rows']) === 0) {
        $specInitial['rows'] = [array_fill(0, count($specInitial['headers']), '')];
    }
@endphp

<div
    class="spec-editor"
    x-data="{
        headers: @js($specInitial['headers']),
        rows: @js($specInitial['rows']),
        maxCols: {{ \App\Support\ProductSpecTable::MAX_COLUMNS }},
        maxRows: {{ \App\Support\ProductSpecTable::MAX_ROWS }},
        payload() {
            return JSON.stringify({ headers: this.headers, rows: this.rows });
        },
        addRow() {
            if (this.rows.length >= this.maxRows) return;
            this.rows.push(this.headers.map(() => ''));
        },
        removeRow(i) {
            if (this.rows.length <= 1) {
                this.rows[0] = this.headers.map(() => '');
                return;
            }
            this.rows.splice(i, 1);
        },
        addCol() {
            if (this.headers.length >= this.maxCols) return;
            const n = this.headers.length + 1;
            this.headers.push(n === 3 ? 'Details' : ('Column ' + n));
            this.rows = this.rows.map((row) => [...row, '']);
        },
        removeCol(ci) {
            if (this.headers.length <= 2) return;
            this.headers.splice(ci, 1);
            this.rows = this.rows.map((row) => {
                const next = [...row];
                next.splice(ci, 1);
                return next;
            });
        }
    }"
>
    <input type="hidden" name="specifications" :value="payload()">

    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
        <div>
            <p class="text-xs font-semibold text-slate-700">Specifications table</p>
            <p class="text-[11px] text-slate-500 mt-0.5">
                Add rows and columns for this product/variant. Each variant can have different specs.
                Empty rows are ignored on the website.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="addRow()"
                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                + Add row
            </button>
            <button type="button" @click="addCol()" :disabled="headers.length >= maxCols"
                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40">
                + Add column
            </button>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm border-collapse">
            <thead>
                <tr class="bg-slate-50">
                    <template x-for="(header, ci) in headers" :key="'h'+ci">
                        <th class="border-b border-slate-200 p-2 align-top min-w-[140px]">
                            <div class="flex items-start gap-1">
                                <input type="text" x-model="headers[ci]"
                                       class="w-full rounded-md border-slate-200 text-xs font-semibold text-slate-700"
                                       :placeholder="'Column ' + (ci + 1)">
                                <button type="button" @click="removeCol(ci)" x-show="headers.length > 2"
                                        class="shrink-0 rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                        title="Remove column">×</button>
                            </div>
                        </th>
                    </template>
                    <th class="border-b border-slate-200 p-2 w-10"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row, ri) in rows" :key="'r'+ri">
                    <tr class="odd:bg-white even:bg-slate-50/60">
                        <template x-for="(cell, ci) in row" :key="'c'+ri+'-'+ci">
                            <td class="border-b border-slate-100 p-2 align-top">
                                <input type="text" x-model="rows[ri][ci]"
                                       class="w-full rounded-md border-slate-200 text-sm text-slate-800"
                                       :placeholder="ci === 0 ? 'e.g. Battery' : 'e.g. 4000 mAh'">
                            </td>
                        </template>
                        <td class="border-b border-slate-100 p-2 text-center">
                            <button type="button" @click="removeRow(ri)"
                                    class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                    title="Remove row">×</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <p class="mt-1.5 text-[11px] text-slate-400" x-text="rows.length + ' row(s) · ' + headers.length + ' column(s)'"></p>
</div>
