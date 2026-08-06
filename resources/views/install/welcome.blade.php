@extends('install.layout')

@section('title', 'Welcome')

@section('steps')
    @include('install.partials.steps', ['step' => 'welcome'])
@endsection

@section('content')
    <h2 class="text-lg font-semibold text-slate-800 mb-2">Welcome</h2>
    <p class="text-slate-600 text-sm leading-relaxed mb-6">
        This wizard will guide you through the installation. Make sure you have your database credentials ready.
    </p>
    <div class="flex justify-end">
        <a href="{{ route('install.environment') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Next →
        </a>
    </div>
@endsection
