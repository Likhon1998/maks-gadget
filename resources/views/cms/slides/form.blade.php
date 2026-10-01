<x-cms-layout
    title="{{ $slide->exists ? 'Edit poster' : 'New homepage poster' }}"
    subtitle="Full-design poster banners on the homepage. Upload the complete designed image — text/layout should be inside the poster art."
    previewUrl="{{ route('home') }}"
>
    <form method="POST"
          action="{{ $slide->exists ? route('cms.slides.update', $slide) : route('cms.slides.store') }}"
          enctype="multipart/form-data"
          class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4"
          x-data="{
              saving: false,
              picked: null,
              ratioOk: null,
              checkPoster(e) {
                  const input = e.target;
                  if (input.name !== 'image' || !input.files || !input.files[0]) return;
                  const url = URL.createObjectURL(input.files[0]);
                  const img = new Image();
                  img.onload = () => {
                      this.picked = img.naturalWidth + ' × ' + img.naturalHeight + ' px';
                      this.ratioOk = Math.abs(img.naturalWidth / img.naturalHeight - 21 / 9) < 0.03;
                      URL.revokeObjectURL(url);
                  };
                  img.src = url;
              }
          }"
          @change="checkPoster($event)"
          @submit="saving = true">
        @csrf
        @if($slide->exists) @method('PUT') @endif

        <div class="rounded-xl border-2 border-indigo-200 bg-indigo-50 p-4 text-sm text-slate-700 leading-relaxed">
            <p class="text-xs font-bold uppercase tracking-wide text-indigo-700">Poster size (homepage frame)</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">1680 × 720 px <span class="text-base font-bold text-indigo-700">(21:9)</span></p>
            <p class="mt-1 text-[13px] text-slate-600">The homepage frame is 21:9 on desktop, tablet and mobile. A 1680 × 720 poster fills it exactly on every screen, with no empty space and nothing cut off.</p>
            <ul class="mt-3 list-disc pl-5 space-y-1 text-[13px]">
                <li><strong>Same ratio also works:</strong> 1890 × 810, 1260 × 540</li>
                <li><strong>Other ratios are not cropped</strong>, so they show dark empty bands at the top/bottom or sides</li>
                <li><strong>Keep text large and centered:</strong> on phones the poster shows at about 360 × 155 px</li>
                <li><strong>Format:</strong> JPG or WebP, under ~500 KB</li>
            </ul>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="text-xs font-bold uppercase text-slate-500">Poster image — 1680 × 720 px {{ $slide->exists ? '' : '*' }}</label>
                <x-image-file-preview
                    name="image"
                    :existing="$slide->image_path ? public_storage_url($slide->image_path) : null"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    :required="! $slide->exists"
                    preview-class="aspect-[21/9] w-full rounded-xl object-contain border border-slate-200 bg-[#0b1220]"
                />
                <p class="mt-1 text-[11px] text-slate-400">Preview uses the same 21:9 frame as the homepage.</p>
                <p x-show="picked && ratioOk" x-cloak class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                    Selected image: <span x-text="picked"></span>. Correct 21:9 ratio, so it fills the frame with no empty space.
                </p>
                <p x-show="picked && ratioOk === false" x-cloak class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                    Selected image: <span x-text="picked"></span>. This isn't 21:9, so dark empty bands will show around it. Use 1680 × 720 px for a perfect fit.
                </p>
                @error('image') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="text-xs font-bold uppercase text-slate-500">Title (admin / alt text)</label>
                <input name="title" value="{{ old('title', $slide->title) }}" class="mt-1 w-full rounded-xl border-slate-200" required placeholder="Summer sale poster">
            </div>
            <div class="md:col-span-2">
                <label class="text-xs font-bold uppercase text-slate-500">Click URL (optional)</label>
                <input name="button_url" value="{{ old('button_url', $slide->button_url) }}" class="mt-1 w-full rounded-xl border-slate-200" placeholder="/shop or https://...">
                <p class="mt-1 text-[11px] text-slate-400">If set, the whole poster is clickable.</p>
            </div>
            <div>
                <label class="text-xs font-bold uppercase text-slate-500">Sort order</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $slide->sort_order ?? 0) }}" class="mt-1 w-full rounded-xl border-slate-200">
            </div>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 self-end pb-2">
                <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $slide->is_active ?? true))>
                Active on website
            </label>
        </div>

        {{-- Keep optional legacy fields hidden so existing rows stay valid --}}
        <input type="hidden" name="badge_text" value="{{ old('badge_text', $slide->badge_text) }}">
        <input type="hidden" name="description" value="{{ old('description', $slide->description) }}">
        <input type="hidden" name="button_text" value="{{ old('button_text', $slide->button_text ?: 'Shop Now') }}">
        <input type="hidden" name="learn_more_text" value="{{ old('learn_more_text', $slide->learn_more_text ?: 'Learn More') }}">
        <input type="hidden" name="learn_more_url" value="{{ old('learn_more_url', $slide->learn_more_url) }}">

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('cms.slides.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600">Cancel</a>
            <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-60" :disabled="saving">
                <span x-show="!saving">Save poster</span>
                <span x-show="saving" x-cloak>Saving…</span>
            </button>
        </div>
    </form>
</x-cms-layout>
