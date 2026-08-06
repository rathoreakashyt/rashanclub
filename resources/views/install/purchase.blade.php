@extends('install.layout')

@section('title', 'Verify Purchase')

@section('steps')
    @include('install.partials.steps', ['step' => 'purchase'])
@endsection

@section('content')
    <h2 class="text-lg font-semibold text-slate-800 mb-2">Verify your purchase</h2>
    <p class="text-slate-600 text-sm mb-6">Please provide your purchase information to verify your purchase.</p>

    <form action="{{ route('install.purchase.verify') }}" method="POST" class="space-y-5">
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
                    placeholder="{{ ($lpIsBd ?? false) ? __('Username') : __('CodeCanyon Username') }}"
                    class="w-full rounded-xl border {{ $errors->has('username') ? 'border-red-500' : 'border-slate-300' }} shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                >
                @error('username')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="purchase_code" class="block text-sm font-medium text-slate-700 mb-1">Purchase Code</label>
                <input
                    type="text"
                    name="purchase_code"
                    id="purchase_code"
                    value="{{ old('purchase_code') }}"
                    placeholder="Purchase code from your download (optional)"
                    class="w-full rounded-xl border {{ $errors->has('purchase_code') ? 'border-red-500' : 'border-slate-300' }} shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                >
                @error('purchase_code')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center space-x-2">
                <input type="checkbox" name="already_installed_get_update" id="already_installed_get_update" value="true" {{ old('already_installed_get_update') ? 'checked' : '' }}>
                <label for="already_installed_get_update">Already Installed Get Update</label>
            </div>
            <div id="update-alert" class="mt-3 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm" role="alert">
                <strong>Already Installed Get Update:</strong> This update process is designed exclusively for upgrading from the CodeIgniter version to the Laravel version. Using it for any other version updates is not supported.
            </div>
            @error('already_installed_get_update')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror

            <div class="flex justify-between pt-2">
                <a href="{{ route('install.environment') }}" class="inline-flex items-center px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-50">
                    ← Previous
                </a>
                <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Verify
                </button>
            </div>
        </div>
    </form>
@endsection
