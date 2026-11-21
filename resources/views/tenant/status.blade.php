@extends('layouts.app')

@section('title', 'Status Akun Penyewa — Garasi Kendaraan')

@section('content')
    <div class="mx-auto max-w-2xl">
        @if ($user->isPending())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm sm:p-8">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-lg font-bold text-amber-800">
                        !
                    </div>
                    <div class="flex-1">
                        <span class="inline-flex rounded-full bg-amber-200/80 px-2.5 py-0.5 text-xs font-semibold text-amber-900">
                            Menunggu Verifikasi Admin
                        </span>
                        <h1 class="mt-2 text-xl font-bold text-amber-950">Akun Anda Sedang Diverifikasi</h1>
                        <p class="mt-2 text-sm leading-relaxed text-amber-900/90">
                            Halo <strong>{{ $user->nama_lengkap }}</strong>, pendaftaran akun penyewa Anda telah diterima dan saat ini sedang menunggu persetujuan dari Admin. Anda dapat menggunakan fitur <strong>Sewa Baru</strong> segera setelah akun disetujui.
                        </p>

                        <dl class="mt-5 grid gap-3 rounded-xl border border-amber-200/80 bg-white/80 p-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium text-slate-500">NIK</dt>
                                <dd class="mt-0.5 font-mono font-medium text-slate-900">{{ $user->nik }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500">Email</dt>
                                <dd class="mt-0.5 font-medium text-slate-900">{{ $user->email }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        @elseif ($user->isRejected())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 shadow-sm sm:p-8">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-lg font-bold text-rose-800">
                        ×
                    </div>
                    <div class="flex-1">
                        <span class="inline-flex rounded-full bg-rose-200/80 px-2.5 py-0.5 text-xs font-semibold text-rose-900">
                            Pendaftaran Ditolak
                        </span>
                        <h1 class="mt-2 text-xl font-bold text-rose-950">Verifikasi Akun Ditolak</h1>
                        <p class="mt-2 text-sm leading-relaxed text-rose-900/90">
                            Mohon maaf <strong>{{ $user->nama_lengkap }}</strong>, pendaftaran akun penyewa Anda ditolak oleh Admin. Anda dapat mengajukan pendaftaran ulang dengan memperbarui data diri Anda menggunakan tombol di bawah ini.
                        </p>

                        <dl class="mt-5 grid gap-3 rounded-xl border border-rose-200/80 bg-white/80 p-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium text-slate-500">NIK</dt>
                                <dd class="mt-0.5 font-mono font-medium text-slate-900">{{ $user->nik }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500">Email</dt>
                                <dd class="mt-0.5 font-medium text-slate-900">{{ $user->email }}</dd>
                            </div>
                        </dl>

                        <div class="mt-6">
                            <a href="{{ route('register') }}"
                               class="inline-flex items-center rounded-lg bg-rose-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-800">
                                Daftar Ulang Sekarang
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
