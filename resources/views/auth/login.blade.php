@extends('layouts.app')

@section('title', 'Masuk — Garasi Kendaraan')

@section('content')
    <div class="mx-auto max-w-md">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-6">
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Masuk ke Sistem</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Gunakan email dan kata sandi akun Admin atau Penyewa Anda.
                </p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                    <input id="email"
                           name="email"
                           type="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           autocomplete="email"
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Kata Sandi</label>
                    <input id="password"
                           name="password"
                           type="password"
                           required
                           autocomplete="current-password"
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                </div>

                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                        <span>Ingat saya</span>
                    </label>
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Masuk
                </button>
            </form>

            <div class="mt-6 border-t border-slate-200 pt-5 text-center text-sm text-slate-600">
                Belum punya akun penyewa?
                <a href="{{ route('register') }}" class="font-semibold text-slate-900 underline underline-offset-4 hover:text-slate-700">
                    Daftar di sini
                </a>
            </div>
        </div>
    </div>
@endsection
