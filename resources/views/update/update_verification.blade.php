@extends('install.layout')

@section('title', __('Update Verification'))

@section('content')
    <h2 class="text-lg font-semibold text-slate-800 mb-2">{{ __('Update Verification') }}</h2>
    <p class="text-slate-600 text-sm mb-6">{{ __('Enter your purchase details to check for updates.') }}</p>

    <form action="{{ route('update.verify') }}" method="POST" class="space-y-5">
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
                    placeholder="{{ ($lpIsBd ?? false) ? __('Username') : __('CodeCanyon Username') }}"
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
                    placeholder="{{ __('Purchase code from your download (optional)') }}"
                    class="w-full rounded-xl border {{ $errors->has('purchase_code') ? 'border-red-500' : 'border-slate-300' }} shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                >
                @error('purchase_code')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-between pt-2">
                <a href="{{ url('/') }}" class="inline-flex items-center px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-50">
                    ← {{ __('Back to application') }}
                </a>
                <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    {{ __('Verify') }}
                </button>
            </div>
        </div>
    </form>

    @if($lpIsBd ?? false)
    <p class="text-slate-500 text-xs mt-4">
        <a href="{{ route('update.upgrade-license') }}" class="text-indigo-600 hover:underline">{{ __('Upgrade License') }}</a>
    </p>
    @endif
@endsection
