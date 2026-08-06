@extends('install.layout')

@section('title', __('Upgrade License'))

@section('content')
    <h2 class="text-lg font-semibold text-slate-800 mb-2">{{ __('Upgrade License') }}</h2>
    <p class="text-slate-600 text-sm mb-6">{{ __('Enter your purchase and upgrade code to upgrade your package.') }}</p>

    @if ($status === 'success' && $message)
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm border border-emerald-200">
            {{ $message }}
        </div>
    @endif
    @if ($status === 'error' && $message)
        <div class="mb-6 p-4 rounded-xl bg-red-50 text-red-800 text-sm border border-red-200">
            {{ $message }}
        </div>
    @endif

    <form action="{{ route('update.upgrade.submit') }}" method="POST" class="space-y-5">
        @csrf

        <div class="border border-slate-300 rounded-2xl p-4 bg-white space-y-4">
            <div>
                <label for="username" class="block text-sm font-medium text-slate-700 mb-1">{{ ($lpIsBd ?? false) ? __('Username') : __('CodeCanyon Username') }}</label>
                <input
                    type="text"
                    name="username"
                    id="username"
                    value="{{ old('username') }}"
                    required
                    maxlength="50"
                    class="w-full rounded-xl border {{ $errors->has('username') ? 'border-red-500' : 'border-slate-300' }} shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                >
                @error('username')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="purchase_code" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Purchase Code') }}</label>
                <input
                    type="text"
                    name="purchase_code"
                    id="purchase_code"
                    value="{{ old('purchase_code') }}"
                    maxlength="100"
                    placeholder="{{ __('Purchase code (optional)') }}"
                    class="w-full rounded-xl border {{ $errors->has('purchase_code') ? 'border-red-500' : 'border-slate-300' }} shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                >
                @error('purchase_code')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="upgrade_code" class="block text-sm font-medium text-slate-700 mb-1">{{ __('Upgrade Code') }}</label>
                <input
                    type="text"
                    name="upgrade_code"
                    id="upgrade_code"
                    value="{{ old('upgrade_code') }}"
                    required
                    maxlength="100"
                    placeholder="{{ __('Upgrade code from your purchase') }}"
                    class="w-full rounded-xl border {{ $errors->has('upgrade_code') ? 'border-red-500' : 'border-slate-300' }} shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                >
                @error('upgrade_code')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" name="submit" value="1" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    {{ __('Upgrade License') }}
                </button>
            </div>
        </div>
    </form>

    <p class="text-slate-500 text-xs mt-4">
        <a href="{{ url('/') }}" class="text-indigo-600 hover:underline">← {{ __('Back to application') }}</a>
    </p>
@endsection
