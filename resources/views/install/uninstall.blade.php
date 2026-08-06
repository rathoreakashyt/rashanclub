@extends('install.layout')

@section('title', 'Uninstall License')

@section('content')
    <h2 class="text-lg font-semibold text-slate-800 mb-2">Uninstall license</h2>
    <p class="text-slate-600 text-sm mb-6">Enter your purchase details to uninstall or transfer the license from this installation.</p>

    @if ($status === 'success')
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 text-emerald-800 text-sm border border-emerald-200">
            {{ $message }}
        </div>
    @endif
    @if ($status === 'error' && $message)
        <div class="mb-6 p-4 rounded-xl bg-red-50 text-red-800 text-sm border border-red-200">
            {{ $message }}
        </div>
    @endif

    <form action="{{ route('uninstall.submit') }}" method="POST" class="space-y-5">
        @csrf

        <div class="border border-slate-300 rounded-2xl p-4 bg-white space-y-4">
            <div>
                <label for="username" class="block text-sm font-medium text-slate-700 mb-1">{{ ($lpIsBd ?? false) ? __('Username') : __('Envato Username') }}</label>
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
                    placeholder="Purchase code from your download (optional)"
                    class="w-full rounded-xl border {{ $errors->has('purchase_code') ? 'border-red-500' : 'border-slate-300' }} shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                >
                @error('purchase_code')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <input type="hidden" name="action_type" value="uninstall">
            <input type="hidden" name="current_installation_url" value="{{ old('current_installation_url', rtrim(url('/'), '/') . '/') }}">

            <!-- <div class="border-t border-slate-200 pt-4 mt-4">
                <p class="text-slate-600 text-xs mb-3">Optional: for transfer only</p>
                <div class="space-y-3">
                    <div>
                        <label for="current_installation_url" class="block text-sm font-medium text-slate-700 mb-1">Current installation URL</label>
                        <input
                            type="url"
                            name="current_installation_url"
                            id="current_installation_url"
                            value="{{ old('current_installation_url', rtrim(url('/'), '/') . '/') }}"
                            placeholder="https://current-site.com/"
                            class="w-full rounded-xl border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                    </div>
                    <div>
                        <label for="new_installation_url" class="block text-sm font-medium text-slate-700 mb-1">New installation URL (transfer to)</label>
                        <input
                            type="url"
                            name="new_installation_url"
                            id="new_installation_url"
                            value="{{ old('new_installation_url') }}"
                            placeholder="https://new-site.com/"
                            class="w-full rounded-xl border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                    </div>
                    <div>
                        <label for="action_type" class="block text-sm font-medium text-slate-700 mb-1">Action</label>
                        <select name="action_type" id="action_type" class="w-full rounded-xl border border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="uninstall" {{ old('action_type', 'uninstall') === 'uninstall' ? 'selected' : '' }}>Uninstall only</option>
                            <option value="transfer" {{ old('action_type') === 'transfer' ? 'selected' : '' }}>Transfer to new URL</option>
                        </select>
                    </div>
                </div>
            </div> -->

            <input type="hidden" name="base_url_install" value="{{ rtrim(url('/'), '/') . '/' }}">
            <input type="hidden" name="owner" value="local">

            <div class="flex justify-end pt-2">
                <button type="submit" name="submit" value="1" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Uninstall license
                </button>
            </div>
        </div>
    </form>

    <p class="text-slate-500 text-xs mt-4">
        <a href="{{ url('/') }}" class="text-indigo-600 hover:underline">← Back to application</a>
    </p>
@endsection
