@extends('install.layout')

@section('title', 'Database')

@section('steps')
    @include('install.partials.steps', ['step' => 'database'])
@endsection

@section('content')
    <h2 class="text-lg font-semibold text-slate-800 mb-2">Database Configuration</h2>
    <p class="text-slate-600 text-sm mb-6">Enter your MySQL credentials. Click On <strong> Next </strong> to complete the <strong> Installation </strong>.</p>

    <form action="{{ route('install.database.store') }}" method="POST" class="space-y-5">
        @csrf

        <div class="border border-slate-300 rounded-2xl p-4 bg-white">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="db_host" class="block text-sm font-medium text-slate-700 mb-1">
                        Database Host
                    </label>
                    <input
                        type="text"
                        name="db_host"
                        id="db_host"
                        value="{{ old('db_host', $db_host) }}"
                        class="w-full rounded-xl border {{ $errors->has('db_host') ? 'border-red-500' : 'border-slate-300' }} shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-1"
                    >
                    @error('db_host')
                        <p class="text-red-600 text-sm">{{ $message }}</p>
                    @enderror
                    <div class="mb-4"></div>
                </div>

                <div>
                    <label for="db_port" class="block text-sm font-medium text-slate-700 mb-1">
                        Port
                    </label>
                    <input
                        type="text"
                        name="db_port"
                        id="db_port"
                        value="{{ old('db_port', $db_port) }}"
                        class="w-full rounded-xl border {{ $errors->has('db_port') ? 'border-red-500' : 'border-slate-300' }} shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-1"
                    >
                    @error('db_port')
                        <p class="text-red-600 text-sm">{{ $message }}</p>
                    @enderror
                    <div class="mb-4"></div>
                </div>
            </div>

            <div class="mb-4">
                <label for="db_username" class="block text-sm font-medium text-slate-700 mb-1">Database Username</label>
                <input type="text" name="db_username" id="db_username" value="{{ old('db_username', $db_username) }}"
                    class="w-full rounded-xl border {{ $errors->has('db_username') ? 'border-red-500' : 'border-slate-300' }} shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-1">
                @error('db_username')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="db_password" class="block text-sm font-medium text-slate-700 mb-1">Database Password</label>
                <div class="relative">
                    <input type="password" name="db_password" id="db_password" value="{{ old('db_password', $db_password) }}"
                        class="w-full rounded-xl border {{ $errors->has('db_password') ? 'border-red-500' : 'border-slate-300' }} shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-1 pr-10">
                    <button type="button" onclick="toggleDbPasswordVisibility()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700 focus:outline-none" tabindex="-1" aria-label="Toggle password visibility">
                        <svg id="db_password_eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <svg id="db_password_eye_slash" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878a4.5 4.5 0 106.262 6.262M4.031 11.117A8.959 8.959 0 003 12c0 4.478 2.943 8.268 7 9.543 2.17-1.02 4.007-2.98 5.49-4.788m12.156-12.157A8.959 8.959 0 0021 12c0 4.478-2.943 8.268-7 9.543a9.97 9.97 0 01-2.49-.314"/>
                        </svg>
                    </button>
                </div>
                @error('db_password')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="db_name" class="block text-sm font-medium text-slate-700 mb-1">Database Name</label>
                <input type="text" name="db_name" id="db_name" value="{{ old('db_name', $db_name) }}"
                    class="w-full rounded-xl border {{ $errors->has('db_name') ? 'border-red-500' : 'border-slate-300' }} shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm mb-1">
                @error('db_name')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div>

            <!-- <div class="flex items-center mb-6">
                <input
                    type="checkbox"
                    name="create_db"
                    id="create_db"
                    value="1"
                    {{ old('create_db', $create_db) ? 'checked' : '' }}
                    class="form-checkbox h-5 w-5 text-indigo-600 border-2 border-slate-300 rounded focus:ring-indigo-500 transition"
                >
                <label for="create_db" class="ml-3 text-sm font-medium text-slate-700 select-none cursor-pointer">
                    Create database if it does not exist
                </label>
                @error('create_db')
                    <p class="ml-3 text-red-600 text-sm">{{ $message }}</p>
                @enderror
            </div> -->

            <div class="flex justify-between pt-2">
                <a href="{{ route('install.purchase') }}" class="inline-flex items-center px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-50">
                    ← Previous
                </a>
                <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Next →
                </button>
            </div>
        </div>

    </form>

    <script>
        function toggleDbPasswordVisibility() {
            const input = document.getElementById('db_password');
            const eye = document.getElementById('db_password_eye');
            const eyeSlash = document.getElementById('db_password_eye_slash');
            if (input.type === 'password') {
                input.type = 'text';
                eye.classList.add('hidden');
                eyeSlash.classList.remove('hidden');
            } else {
                input.type = 'password';
                eye.classList.remove('hidden');
                eyeSlash.classList.add('hidden');
            }
        }
    </script>
@endsection
