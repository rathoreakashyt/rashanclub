@extends('install.layout')

@section('title', 'Environment')

@section('steps')
    @include('install.partials.steps', ['step' => 'environment'])
@endsection

@section('content')
    <h2 class="text-lg font-semibold text-slate-800 mb-2">Server Environment Checklist</h2>
    <p class="text-slate-600 text-sm mb-6">We need the following to be satisfied before continuing.</p>

    <ul class="space-y-3">
        @foreach ($checks as $check)
            <li class="flex items-center gap-3 p-3 rounded-xl {{ $check['passed'] ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800' }}">
                @if ($check['passed'])
                    <svg class="w-5 h-5 shrink-0 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                @else
                    <svg class="w-5 h-5 shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                @endif
                <span class="text-sm font-medium">{{ $check['label'] }}</span>
                <span class="text-xs opacity-90 ml-auto">{{ $check['message'] }}</span>
            </li>
        @endforeach
    </ul>

    <div class="flex justify-between mt-8">
        <a href="{{ route('install.welcome') }}" class="inline-flex items-center px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-50">
            ← Previous
        </a>
        @if ($passed)
            <a href="{{ route('install.purchase') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Next →
            </a>
        @else
            <span class="inline-flex items-center px-4 py-2 rounded-xl bg-slate-200 text-slate-500 text-sm cursor-not-allowed">
                Fix errors above to continue
            </span>
        @endif
    </div>
@endsection
