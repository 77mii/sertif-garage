@extends('layouts.app')

@section('title', 'Verifikasi Pendaftar Baru — Admin')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Verifikasi Pendaftar Baru</h1>
            <p class="mt-1 text-sm text-slate-600">
                Tinjau pendaftaran akun penyewa baru yang berstatus menunggu verifikasi.
            </p>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">Daftar Pendaftar Menunggu Verifikasi ({{ $pendingUsers->count() }})</h2>
            </div>

            @if ($pendingUsers->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-500">
                    Tidak ada pendaftar baru yang menunggu verifikasi saat ini.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-6 py-3">NIK</th>
                                <th class="px-6 py-3">Nama Lengkap</th>
                                <th class="px-6 py-3">Nomor Telepon</th>
                                <th class="px-6 py-3">Email</th>
                                <th class="px-6 py-3">Tanggal Daftar</th>
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($pendingUsers as $pendingUser)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="whitespace-nowrap px-6 py-3.5 font-mono text-slate-900">
                                        {{ $pendingUser->nik }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 font-medium text-slate-900">
                                        {{ $pendingUser->nama_lengkap }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-slate-700">
                                        {{ $pendingUser->nomor_telepon }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-slate-700">
                                        {{ $pendingUser->email }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-slate-600">
                                        {{ $pendingUser->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <form method="POST" action="{{ route('admin.verifications.approve', $pendingUser) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                                    Terima
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.verifications.reject', $pendingUser) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-rose-700">
                                                    Tolak
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
