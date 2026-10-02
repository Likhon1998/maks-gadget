{{-- Expects the variables prepared in website/track-order.blade.php --}}
<section class="{{ $card }}">
    <div class="p-1.5">
        <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1" role="tablist">
            <button type="button" role="tab" @click="tab = 'id'" :aria-selected="tab === 'id'"
                    class="rounded-lg px-2 py-2 text-[12.5px] font-semibold transition"
                    :class="tab === 'id' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'">
                {{ $compact ? 'Track another' : 'I have my Order ID' }}
            </button>
            <button type="button" role="tab" @click="tab = 'find'" :aria-selected="tab === 'find'"
                    class="rounded-lg px-2 py-2 text-[12.5px] font-semibold transition"
                    :class="tab === 'find' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'">
                Forgot Order ID?
            </button>
        </div>
    </div>

    {{-- Order ID + mobile --}}
    <div x-show="tab === 'id'" class="{{ $compact ? 'px-4 pb-4 pt-2' : 'px-5 pb-5 pt-3 sm:px-6 sm:pb-6' }}">
        @unless($compact)
            <h2 class="text-base font-bold text-slate-900">Track with Order ID</h2>
            <p class="mt-0.5 text-[13px] text-slate-500">Your Order ID starts with <span class="font-mono font-semibold text-slate-700">WEB-</span> and was shown after checkout.</p>
        @endunless

        <form method="POST" action="{{ route('website.track.lookup') }}" class="{{ $compact ? '' : 'mt-4' }}">
            @csrf
            <div class="grid gap-3 {{ $compact ? '' : 'sm:grid-cols-2' }}">
                <div>
                    <label for="track-invoice-{{ $compact ? 'c' : 'm' }}" class="{{ $compact ? 'sr-only' : 'mb-1.5 block text-[12px] font-semibold text-slate-700' }}">Order ID</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $iconHash }}"/></svg>
                        <input id="track-invoice-{{ $compact ? 'c' : 'm' }}" name="invoice_no" value="{{ $compact ? '' : old('invoice_no', $invoiceNo ?? '') }}" required autocomplete="off"
                               class="{{ $inputClass }} font-mono uppercase placeholder:font-sans placeholder:normal-case" placeholder="WEB-1-2026-00001">
                    </div>
                    @error('invoice_no') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="track-phone-{{ $compact ? 'c' : 'm' }}" class="{{ $compact ? 'sr-only' : 'mb-1.5 block text-[12px] font-semibold text-slate-700' }}">Mobile number</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $iconPhone }}"/></svg>
                        <input id="track-phone-{{ $compact ? 'c' : 'm' }}" name="phone" type="tel" inputmode="tel" value="{{ old('phone', $phone ?? '') }}" required autocomplete="tel"
                               class="{{ $inputClass }}" placeholder="01XXXXXXXXX">
                    </div>
                    @error('phone') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @if($errorMessage && !$compact)
                <div class="mt-3 flex gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p><span class="font-semibold">{{ $errorMessage }}</span> Check the ID and use the mobile you ordered with, or try <button type="button" class="font-semibold underline" @click="tab = 'find'">Forgot Order ID</button>.</p>
                </div>
            @endif

            <button type="submit" class="{{ $compact ? 'mt-3' : 'mt-4' }} inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-blue-600 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200">
                Track order
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </button>
        </form>
    </div>

    {{-- Forgot Order ID: mobile only --}}
    <div x-show="tab === 'find'" x-cloak class="{{ $compact ? 'px-4 pb-4 pt-2' : 'px-5 pb-5 pt-3 sm:px-6 sm:pb-6' }}">
        @unless($compact)
            <h2 class="text-base font-bold text-slate-900">Find your orders</h2>
            <p class="mt-0.5 text-[13px] text-slate-500">Enter the mobile number you used at checkout — we'll list your orders.</p>
        @endunless

        <form method="POST" action="{{ route('website.track.find') }}" class="{{ $compact ? '' : 'mt-4' }}">
            @csrf
            <label for="find-phone-{{ $compact ? 'c' : 'm' }}" class="{{ $compact ? 'mb-2 block text-[12px] text-slate-500' : 'mb-1.5 block text-[12px] font-semibold text-slate-700' }}">{{ $compact ? 'Mobile number used at checkout' : 'Mobile number' }}</label>
            <div class="{{ $compact ? '' : 'flex flex-col gap-3 sm:flex-row' }}">
                <div class="relative flex-1">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $iconPhone }}"/></svg>
                    <input id="find-phone-{{ $compact ? 'c' : 'm' }}" name="find_phone" type="tel" inputmode="tel" value="{{ old('find_phone') }}" required autocomplete="tel"
                           class="{{ $inputClass }}" placeholder="01XXXXXXXXX">
                </div>
                <button type="submit" class="{{ $compact ? 'mt-4 w-full' : 'sm:w-44' }} inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-200">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Find my orders
                </button>
            </div>
            @error('find_phone') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </form>

        @if($findError)
            <div class="mt-3 flex gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[13px] text-rose-700">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p>{{ $findError }}</p>
            </div>
        @endif

        @if(!empty($foundOrders))
            <div class="mt-4">
                <p class="mb-2 text-[12px] font-bold text-slate-900">
                    {{ count($foundOrders) }} {{ \Illuminate\Support\Str::plural('order', count($foundOrders)) }} found
                    <span class="font-normal text-slate-500">· newest first</span>
                </p>
                <ul class="max-h-[216px] space-y-2 overflow-y-auto pr-0.5">
                    @foreach($foundOrders as $found)
                        <li>
                            <a href="{{ $found['url'] }}" class="group flex items-center gap-3 rounded-xl border border-slate-200 px-3.5 py-2.5 transition hover:border-blue-300 hover:bg-blue-50/30">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-mono text-[13px] font-bold text-slate-900">{{ $found['invoice'] }}</span>
                                    <span class="block text-[11.5px] text-slate-500">{{ $found['date'] }} · {{ $money($found['total']) }}</span>
                                </span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-700">{{ $found['status'] }}</span>
                                <svg class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>
