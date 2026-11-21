<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sistem Penyewaan Garasi Kendaraan')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    @php
        $currentUser = auth()->user();
        $todayWib = \Carbon\Carbon::today('Asia/Jakarta');
    @endphp

    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-sm font-bold tracking-tight text-white">
                        GK
                    </span>
                    <div>
                        <p class="text-sm font-semibold leading-tight text-slate-900">Garasi Kendaraan</p>
                        <p class="text-xs text-slate-500">{{ $todayWib->format('d M Y') }} (WIB)</p>
                    </div>
                </a>
            </div>

            <nav class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                @guest
                    <a href="{{ route('login') }}"
                       class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('login') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}"
                       class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('register') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                        Daftar Penyewa
                    </a>
                @else
                    @if ($currentUser->isAdmin())
                        <a href="{{ route('admin.garages.index') }}"
                           class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.garages.*') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            Buat Garasi Baru
                        </a>
                        <a href="{{ route('admin.verifications.index') }}"
                           class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.verifications.*') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            Verifikasi Pendaftar Baru
                        </a>
                        <a href="{{ route('admin.extensions.index') }}"
                           class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.extensions.*') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            Perpanjang Status Garasi
                        </a>
                        <a href="{{ route('admin.reports.index') }}"
                           class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('admin.reports.*') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            Laporan Sewa Garasi
                        </a>
                    @elseif ($currentUser->isApproved())
                        <a href="{{ route('tenant.rentals.create') }}"
                           class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('tenant.rentals.*') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            Sewa Baru
                        </a>
                    @else
                        <a href="{{ route('tenant.status') }}"
                           class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('tenant.status') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            Status Akun
                        </a>
                    @endif

                    <div class="ml-1 flex items-center gap-3 border-l border-slate-200 pl-3">
                        <div class="hidden text-right sm:block">
                            <p class="text-xs font-semibold text-slate-800">{{ $currentUser->nama_lengkap }}</p>
                            <p class="text-[11px] uppercase tracking-wider text-slate-500">
                                {{ $currentUser->isAdmin() ? 'Admin' : 'Penyewa' }}
                            </p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                                Keluar
                            </button>
                        </form>
                    </div>
                @endguest
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
                <p class="font-semibold">Terdapat kesalahan pada data yang dikirim:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
