@php
    $category = $category ?? null;
@endphp

<div class="mt-4">
    <x-input-label for="image" :value="__('Cover image')" />
    <x-image-file-preview
        name="image"
        id="image"
        :existing="$category?->image_path ? public_storage_url($category->image_path) : null"
        accept=".png,.jpg,.jpeg,.webp,.gif,image/png,image/jpeg,image/webp,image/gif"
        input-class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700"
        preview-class="absolute inset-0 h-full w-full object-cover [object-position:center_40%]"
    >
        <x-slot:overlay class="w-56 aspect-[10/13] rounded-[22px] bg-slate-900 shadow-lg ring-1 ring-slate-200">
            <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent px-4 pb-4 pt-16"
                 x-data="{
                    n: document.getElementById('name')?.value || '',
                    t: document.getElementById('description')?.value || '',
                 }"
                 x-init="
                    document.getElementById('name')?.addEventListener('input', (e) => n = e.target.value);
                    document.getElementById('description')?.addEventListener('input', (e) => t = e.target.value);
                 ">
                <p class="text-lg font-bold leading-tight text-white" x-text="n || 'Category name'"></p>
                <p class="mt-1 text-xs leading-snug text-white/80" x-show="t" x-text="t"></p>
            </div>
        </x-slot:overlay>
    </x-image-file-preview>
    <p class="mt-1 text-xs text-gray-500">This is how the card looks on the homepage. Without a cover image, a product photo from this category is used.</p>
    <x-poster-size size="800 × 1040 px" ratio="portrait 10:13">Fills the tall "Shop by Category" card. The name and tagline cover the bottom third, so keep the subject in the upper middle.</x-poster-size>
    <x-input-error class="mt-2" :messages="$errors->get('image')" />
</div>
