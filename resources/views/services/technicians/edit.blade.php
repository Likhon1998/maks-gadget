<x-app-layout>
    <div class="mx-auto max-w-lg px-3 pb-8 sm:px-0">
        <a href="{{ route('services.technicians.index') }}"
           class="mb-3 inline-flex h-8 items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800">
            ← Technicians
        </a>
        <h1 class="text-xl font-bold leading-tight text-slate-900">Edit {{ $technician->name }}</h1>
        <p class="mt-0.5 mb-3 text-xs text-slate-500">Update name, contact, or rate %</p>

        @if ($errors->any())
            <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                <ul class="list-disc space-y-0.5 pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('services.technicians.update', $technician) }}"
              class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            @csrf
            @method('PUT')
            @include('services.technicians._form', ['technician' => $technician])
            <div class="flex items-center gap-2 border-t border-slate-100 pt-3">
                <button class="inline-flex h-9 items-center justify-center rounded-lg bg-indigo-600 px-4 text-xs font-bold text-white hover:bg-indigo-700">
                    Update
                </button>
                <a href="{{ route('services.technicians.index') }}"
                   class="inline-flex h-9 items-center justify-center rounded-lg px-3 text-xs font-semibold text-slate-500 hover:text-slate-800">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
