<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TenantRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TenantRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TenantRegistrationService::class);
    }

    public function test_new_tenant_registration_creates_pending_penyewa(): void
    {
        $user = $this->service->register([
            'nik' => '3201012345678901',
            'nama_lengkap' => 'Budi Santoso',
            'nomor_telepon' => '081234567890',
            'email' => 'Budi@Example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $this->assertSame(User::ROLE_PENYEWA, $user->role);
        $this->assertSame(User::STATUS_PENDING, $user->verification_status);
        $this->assertNull($user->verified_at);
        $this->assertSame('budi@example.com', $user->email);
        $this->assertSame('3201012345678901', $user->nik);
        $this->assertTrue(Hash::check('secret1234', $user->password));
    }

    public function test_registration_validates_nik_digits_and_password_confirmation(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->register([
            'nik' => '123456789012345',
            'nama_lengkap' => 'Invalid NIK',
            'nomor_telepon' => '081234567890',
            'email' => 'invalid@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'mismatch',
        ]);
    }

    public function test_registration_rejects_existing_pending_approved_and_admin_credentials(): void
    {
        User::factory()->pending()->create([
            'email' => 'pending@example.com',
            'nik' => '1111111111111111',
        ]);

        User::factory()->approved()->create([
            'email' => 'approved@example.com',
            'nik' => '2222222222222222',
        ]);

        User::factory()->admin()->create([
            'email' => 'admin@garasi.test',
        ]);

        $cases = [
            ['email' => 'pending@example.com', 'nik' => '9999999999999991'],
            ['email' => 'fresh1@example.com', 'nik' => '1111111111111111'],
            ['email' => 'approved@example.com', 'nik' => '9999999999999992'],
            ['email' => 'fresh2@example.com', 'nik' => '2222222222222222'],
            ['email' => 'admin@garasi.test', 'nik' => '9999999999999993'],
        ];

        foreach ($cases as $case) {
            try {
                $this->service->register([
                    'nik' => $case['nik'],
                    'nama_lengkap' => 'Blocked User',
                    'nomor_telepon' => '081234567890',
                    'email' => $case['email'],
                    'password' => 'secret1234',
                    'password_confirmation' => 'secret1234',
                ]);
                $this->fail('Expected ValidationException for blocked credentials: '.json_encode($case));
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_re_registering_with_same_rejected_email_and_nik_overwrites_existing_row(): void
    {
        $rejected = User::factory()->rejected()->create([
            'email' => 'rejected@example.com',
            'nik' => '3333333333333333',
            'nama_lengkap' => 'Nama Lama',
            'nomor_telepon' => '081111111111',
        ]);

        $updated = $this->service->register([
            'nik' => '3333333333333333',
            'nama_lengkap' => 'Nama Baru',
            'nomor_telepon' => '082222222222',
            'email' => 'rejected@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $this->assertSame($rejected->id, $updated->id);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Nama Baru', $updated->nama_lengkap);
        $this->assertSame('082222222222', $updated->nomor_telepon);
        $this->assertSame(User::STATUS_PENDING, $updated->verification_status);
        $this->assertNull($updated->verified_at);
        $this->assertTrue(Hash::check('newpassword123', $updated->password));
    }

    public function test_re_registering_with_only_rejected_email_or_only_rejected_nik_overwrites_single_row(): void
    {
        $rejectedByEmail = User::factory()->rejected()->create([
            'email' => 'rej-email@example.com',
            'nik' => '4444444444444441',
        ]);

        $updatedEmailRow = $this->service->register([
            'nik' => '4444444444444499',
            'nama_lengkap' => 'Updated Via Email',
            'nomor_telepon' => '081200000001',
            'email' => 'rej-email@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertSame($rejectedByEmail->id, $updatedEmailRow->id);
        $this->assertSame('4444444444444499', $updatedEmailRow->nik);
        $this->assertSame(User::STATUS_PENDING, $updatedEmailRow->verification_status);

        $rejectedByNik = User::factory()->rejected()->create([
            'email' => 'old-nik@example.com',
            'nik' => '5555555555555551',
        ]);

        $updatedNikRow = $this->service->register([
            'nik' => '5555555555555551',
            'nama_lengkap' => 'Updated Via NIK',
            'nomor_telepon' => '081200000002',
            'email' => 'new-email@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertSame($rejectedByNik->id, $updatedNikRow->id);
        $this->assertSame('new-email@example.com', $updatedNikRow->email);
        $this->assertSame(User::STATUS_PENDING, $updatedNikRow->verification_status);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_cross_user_collision_guard_blocks_email_from_rejected_a_and_nik_from_rejected_b(): void
    {
        $rejectedA = User::factory()->rejected()->create([
            'email' => 'rejected-a@example.com',
            'nik' => '6666666666666661',
        ]);

        $rejectedB = User::factory()->rejected()->create([
            'email' => 'rejected-b@example.com',
            'nik' => '6666666666666662',
        ]);

        try {
            $this->service->register([
                'nik' => $rejectedB->nik,
                'nama_lengkap' => 'Cross Collision',
                'nomor_telepon' => '081299999999',
                'email' => $rejectedA->email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
            $this->fail('Cross-user collision between two rejected users must fail validation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }

        $this->assertSame(User::STATUS_REJECTED, $rejectedA->fresh()->verification_status);
        $this->assertSame(User::STATUS_REJECTED, $rejectedB->fresh()->verification_status);
    }
}
