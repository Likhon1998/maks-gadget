@props(['size', 'ratio' => null])

<p {{ $attributes->merge(['class' => 'mt-1.5 rounded-lg border border-indigo-100 bg-indigo-50 px-2.5 py-1.5 text-[11px] leading-snug text-slate-600']) }}>
    <span class="font-bold text-indigo-900">Size: {{ $size }}</span>
    @if($ratio)
        <span class="font-semibold text-indigo-600">({{ $ratio }})</span>
    @endif
    @if(trim((string) $slot) !== '')
        <span class="block mt-0.5">{{ $slot }}</span>
    @endif
</p>
