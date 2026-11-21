@extends('layouts.app')

@section('title', 'Pendaftaran Penyewa — Garasi Kendaraan')

@section('content')
    <div class="mx-auto max-w-lg">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="mb-6">
                <h1 class="text-xl font-bold tracking-tight text-slate-900">
                    {{ $rejectedUser ? 'Daftar Ulang Penyewa' : 'Pendaftaran Penyewa Baru' }}
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    Lengkapi data diri Anda. Akun penyewa akan diverifikasi oleh Admin sebelum dapat menyewa garasi.
                </p>
            </div>

            @if ($rejectedUser)
                <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-900">
                    Pendaftaran ulang akan memperbarui data akun Anda yang sebelumnya ditolak dan mengembalikan status menjadi <strong>Menunggu Verifikasi</strong>.
                </div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="nik" class="block text-sm font-medium text-slate-700">NIK (16 Digit Angka)</label>
                    <input id="nik"
                           name="nik"
                           type="text"
                           inputmode="numeric"
                           pattern="[0-9]{16}"
                           maxlength="16"
                           minlength="16"
                           value="{{ old('nik', $rejectedUser?->nik) }}"
                           required
                           placeholder="3201012345678901"
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 font-mono text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                </div>

                <div>
                    <label for="nama_lengkap" class="block text-sm font-medium text-slate-700">Nama Lengkap</label>
                    <input id="nama_lengkap"
                           name="nama_lengkap"
                           type="text"
                           value="{{ old('nama_lengkap', $rejectedUser?->nama_lengkap) }}"
                           required
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                </div>

                <div>
                    <label for="nomor_telepon" class="block text-sm font-medium text-slate-700">Nomor Telepon</label>
                    <input id="nomor_telepon"
                           name="nomor_telepon"
                           type="text"
                           value="{{ old('nomor_telepon', $rejectedUser?->nomor_telepon) }}"
                           required
                           placeholder="081234567890"
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email (Digunakan untuk Login)</label>
                    <input id="email"
                           name="email"
                           type="email"
                           value="{{ old('email', $rejectedUser?->email) }}"
                           required
                           autocomplete="email"
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700">Kata Sandi</label>
                        <input id="password"
                               name="password"
                               type="password"
                               minlength="8"
                               required
                               autocomplete="new-password"
                               class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Konfirmasi Kata Sandi</label>
                        <input id="password_confirmation"
                               name="password_confirmation"
                               type="password"
                               minlength="8"
                               required
                               autocomplete="new-password"
                               class="mt-1 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-xs focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                    </div>
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                    {{ $rejectedUser ? 'Kirim Pendaftaran Ulang' : 'Daftar Sekarang' }}
                </button>
            </form>

            @guest
                <div class="mt-6 border-t border-slate-200 pt-5 text-center text-sm text-slate-600">
                    Sudah memiliki akun?
                    <a href="{{ route('login') }}" class="font-semibold text-slate-900 underline underline-offset-4 hover:text-slate-700">
                        Masuk di sini
                    </a>
                </div>
            @endguest
        </div>
    </div>
@endsection
