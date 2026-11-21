<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TenantRegistrationService
{
    /**
     * Register a new tenant or overwrite a single existing rejected tenant account.
     *
     * @param  array{
     *     nik?: mixed,
     *     nama_lengkap?: mixed,
     *     nomor_telepon?: mixed,
     *     email?: mixed,
     *     password?: mixed,
     *     password_confirmation?: mixed
     * }  $data
     *
     * @throws ValidationException
     */
    public function register(array $data): User
    {
        $normalized = [
            'nik' => isset($data['nik']) ? trim((string) $data['nik']) : null,
            'nama_lengkap' => isset($data['nama_lengkap']) ? trim((string) $data['nama_lengkap']) : null,
            'nomor_telepon' => isset($data['nomor_telepon']) ? trim((string) $data['nomor_telepon']) : null,
            'email' => isset($data['email']) ? strtolower(trim((string) $data['email'])) : null,
            'password' => $data['password'] ?? null,
            'password_confirmation' => $data['password_confirmation'] ?? null,
        ];

        $validated = Validator::make(
            $normalized,
            [
                'nik' => ['required', 'string', 'digits:16'],
                'nama_lengkap' => ['required', 'string', 'max:255'],
                'nomor_telepon' => ['required', 'string', 'max:30'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ],
            [
                'nik.digits' => 'NIK harus terdiri dari tepat 16 digit angka.',
            ]
        )->validate();

        return DB::transaction(function () use ($validated): User {
            $emailUser = User::query()
                ->where('email', $validated['email'])
                ->lockForUpdate()
                ->first();

            $nikUser = User::query()
                ->where('nik', $validated['nik'])
                ->lockForUpdate()
                ->first();

            $errors = [];

            if ($emailUser !== null && ($emailUser->isAdmin() || ! $emailUser->isRejected())) {
                $errors['email'] = 'Email sudah terdaftar dan tidak dapat digunakan untuk mendaftar.';
            }

            if ($nikUser !== null && ($nikUser->isAdmin() || ! $nikUser->isRejected())) {
                $errors['nik'] = 'NIK sudah terdaftar dan tidak dapat digunakan untuk mendaftar.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($emailUser !== null && $nikUser !== null && $emailUser->id !== $nikUser->id) {
                throw ValidationException::withMessages([
                    'email' => 'Email dan NIK terdaftar pada dua akun ditolak yang berbeda.',
                    'nik' => 'Email dan NIK terdaftar pada dua akun ditolak yang berbeda.',
                ]);
            }

            $rejectedUser = $emailUser ?? $nikUser;

            if ($rejectedUser !== null) {
                $rejectedUser->fill([
                    'role' => User::ROLE_PENYEWA,
                    'nik' => $validated['nik'],
                    'nama_lengkap' => $validated['nama_lengkap'],
                    'nomor_telepon' => $validated['nomor_telepon'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'verification_status' => User::STATUS_PENDING,
                    'verified_at' => null,
                ]);
                $rejectedUser->save();

                return $rejectedUser->refresh();
            }

            return User::query()->create([
                'role' => User::ROLE_PENYEWA,
                'nik' => $validated['nik'],
                'nama_lengkap' => $validated['nama_lengkap'],
                'nomor_telepon' => $validated['nomor_telepon'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'verification_status' => User::STATUS_PENDING,
                'verified_at' => null,
            ]);
        });
    }
}
