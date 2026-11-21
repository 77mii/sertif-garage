<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function index(): View
    {
        $pendingUsers = User::query()
            ->penyewa()
            ->pending()
            ->orderBy('created_at')
            ->get();

        return view('admin.verifications.index', [
            'pendingUsers' => $pendingUsers,
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        if (! $user->isPenyewa() || ! $user->isPending()) {
            return redirect()
                ->route('admin.verifications.index')
                ->with('error', 'Akun tersebut tidak berada dalam status menunggu verifikasi.');
        }

        $user->update([
            'verification_status' => User::STATUS_APPROVED,
            'verified_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return redirect()
            ->route('admin.verifications.index')
            ->with('success', "Pendaftar {$user->nama_lengkap} berhasil disetujui.");
    }

    public function reject(User $user): RedirectResponse
    {
        if (! $user->isPenyewa() || ! $user->isPending()) {
            return redirect()
                ->route('admin.verifications.index')
                ->with('error', 'Akun tersebut tidak berada dalam status menunggu verifikasi.');
        }

        $user->update([
            'verification_status' => User::STATUS_REJECTED,
            'verified_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return redirect()
            ->route('admin.verifications.index')
            ->with('success', "Pendaftar {$user->nama_lengkap} telah ditolak.");
    }
}
