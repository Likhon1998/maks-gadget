<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Category') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg border border-gray-100">
                <form action="{{ route('categories.update', $category) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="name" :value="__('Update Category Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $category->name)" required autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="description" :value="__('Cover tagline (homepage)')" />
                        <x-text-input id="description" name="description" type="text" class="mt-1 block w-full" :value="old('description', $category->description)" placeholder="e.g. Power in your pocket." />
                        <p class="mt-1 text-xs text-gray-500">Shown on the Shop by Category cards. Leave blank to use a default.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('description')" />
                    </div>

                    @include('categories.partials.cover-image-field', ['category' => $category])

                    @include('categories.partials.icon-picker', ['category' => $category])

                    @include('categories.partials.filter-options', ['filterConfig' => $filterConfig])

                    <div class="mt-6 flex items-center justify-end gap-4">
                        <a href="{{ route('categories.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                        <x-primary-button class="bg-indigo-600">{{ __('Update Category') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
