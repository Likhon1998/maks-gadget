@php $t = $technician ?? null; @endphp
<div class="grid gap-3 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Full name *</label>
        <input type="text" name="name" value="{{ old('name', $t->name ?? '') }}" required
               class="h-9 w-full rounded-lg border-slate-200 text-sm font-semibold">
    </div>
    <div>
        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Phone</label>
        <input type="text" name="phone" value="{{ old('phone', $t->phone ?? '') }}"
               class="h-9 w-full rounded-lg border-slate-200 text-sm">
    </div>
    <div>
        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Email</label>
        <input type="email" name="email" value="{{ old('email', $t->email ?? '') }}"
               class="h-9 w-full rounded-lg border-slate-200 text-sm">
    </div>
    <div>
        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Rate % *</label>
        <input type="number" name="commission_percent" step="0.01" min="0" max="100"
               value="{{ old('commission_percent', $t->commission_percent ?? 10) }}" required
               class="h-9 w-full rounded-lg border-slate-200 text-sm font-bold">
        <p class="mt-1 text-[11px] text-slate-400">% of service only (not shop stock)</p>
    </div>
    <div class="flex items-end">
        <label class="mb-1 inline-flex h-9 items-center gap-2 text-xs font-semibold text-slate-700">
            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                   @checked(old('is_active', $t->is_active ?? true))>
            Active in POS
        </label>
    </div>
    <div class="sm:col-span-2">
        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Notes</label>
        <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes', $t->notes ?? '') }}</textarea>
    </div>
</div>
