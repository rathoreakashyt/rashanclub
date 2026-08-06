@extends('install.layout')

@section('title', __('Check for Update'))

@section('content')
    <h2 class="text-lg font-semibold text-slate-800 mb-2">{{ __('Software Update') }}</h2>

    <div class="rounded-2xl border p-4 mb-6 text-sm" style="border-color: {{ $color }}; background: {{ $color }}18;">
        <p class="font-medium" style="color: {{ $color }};">{{ $message }}</p>
        @if($whatsNew)
            <div class="mt-3 pt-3 border-t text-slate-600" style="border-color: {{ $color }}40;">
                <p class="font-medium text-slate-700 mb-1">{{ __('What\'s new') }}:</p>
                <div class="whitespace-pre-wrap">{{ $whatsNew }}</div>
            </div>
        @endif
    </div>

    @if($updateUrl)
        {{-- Step 1: Download only — shown first --}}
        <div id="step-download" class="mb-4">
            <form id="update-form" action="{{ $updateUrl }}" method="POST" class="inline">
                @csrf
                <button type="submit" id="btn-update" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    {{ __('Download Update') }}
                </button>
            </form>
            <p class="text-slate-500 text-xs mt-2">{{ __('Do not close this page during download.') }}</p>
        </div>
        {{-- Step 2: Install only — shown after download completes --}}
        <div id="step-install" class="mb-4" style="display: none;">
            <p class="text-slate-600 text-sm mb-2">{{ __('Downloaded successfully.') }} {{ __('Click the button below to install.') }}</p>
            <form id="install-form" action="{{ route('update.install') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="install_token" id="install-token-input" value="">
                <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                    {{ __('Install Update') }}
                </button>
            </form>
        </div>
    @endif

    <p class="text-slate-500 text-xs mt-6">
        <a href="{{ url('/') }}" class="text-indigo-600 hover:underline">← {{ __('Back to application') }}</a>
        &nbsp;|&nbsp;
        <a href="{{ route('update.verification') }}" class="text-indigo-600 hover:underline">{{ __('Verify again') }}</a>
        @if($lpIsBd ?? false)
        &nbsp;|&nbsp;
        <a href="{{ route('update.upgrade-license') }}" class="text-indigo-600 hover:underline">{{ __('Upgrade License') }}</a>
        @endif
    </p>

    @if($updateUrl)
    <script>
        document.getElementById('update-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('btn-update');
            btn.disabled = true;
            btn.textContent = '{{ __('Downloading...') }}';
            var form = this;
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.status === 'success') {
                    document.getElementById('step-download').style.display = 'none';
                    document.getElementById('step-install').style.display = 'block';
                    if (data.install_token) {
                        document.getElementById('install-token-input').value = data.install_token;
                    }
                } else {
                    btn.disabled = false;
                    btn.textContent = '{{ __('Download Update') }}';
                    alert(data.message || '{{ __('Download failed.') }}');
                }
            }).catch(function() {
                btn.disabled = false;
                btn.textContent = '{{ __('Download Update') }}';
                alert('{{ __('Download failed.') }}');
            });
        });
    </script>
    @endif
@endsection
