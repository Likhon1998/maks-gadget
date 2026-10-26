@extends('website.layout')

@section('title', 'Change Password — ' . ($settings->store_name ?? 'Store'))

@section('content')
<div class="ap-page" x-data="{ showCurrent: false, showNew: false, showConfirm: false }">
    <nav class="ap-breadcrumb">
        <a href="{{ route('home') }}">Home</a>
        <span>›</span>
        <a href="{{ route('website.account') }}">My Account</a>
        <span>›</span>
        <span class="ap-breadcrumb-current">Change Password</span>
    </nav>

    <div class="ap-head">
        <h1>Change Password</h1>
        <p>Use your current password, then choose a new one. Next sign-in will use the new password.</p>
    </div>

    @if(session('password_success'))
        <div class="ap-alert ap-alert--ok">
            {{ session('password_success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="ap-alert ap-alert--err">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="ap-layout">
        <div class="ap-sidebar-col">
            @include('website.partials.account-sidebar', ['activeMenu' => 'password', 'customer' => $customer, 'activeOrder' => $activeOrder])
        </div>

        <div class="ap-main">
            <form method="POST" action="{{ route('website.account.password.update') }}" class="ap-card ap-form">
                @csrf
                @method('PUT')

                <div class="ap-form-section">
                    <h2>Password</h2>
                    <div>
                        <label for="current_password">Current password</label>
                        <div class="ap-pass">
                            <input id="current_password" name="current_password" :type="showCurrent ? 'text' : 'password'" autocomplete="current-password" required>
                            <button type="button" @click="showCurrent = !showCurrent" aria-label="Show current password">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                        @error('current_password')<p class="ap-field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password">New password</label>
                        <div class="ap-pass">
                            <input id="password" name="password" :type="showNew ? 'text' : 'password'" autocomplete="new-password" required minlength="8">
                            <button type="button" @click="showNew = !showNew" aria-label="Show new password">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                        @error('password')<p class="ap-field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password_confirmation">Confirm new password</label>
                        <div class="ap-pass">
                            <input id="password_confirmation" name="password_confirmation" :type="showConfirm ? 'text' : 'password'" autocomplete="new-password" required minlength="8">
                            <button type="button" @click="showConfirm = !showConfirm" aria-label="Show password confirmation">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                    </div>
                    <p class="ap-hint">Use at least 8 characters. Your current password is required before it can be changed.</p>
                </div>

                <div class="ap-actions">
                    <button type="submit" class="gaget-btn-primary ap-btn">Update password</button>
                    <a href="{{ route('website.account') }}" class="gaget-btn-outline ap-btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
