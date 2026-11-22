<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\TenantRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_fresh_registration_creates_pending_penyewa_with_normalized_email_and_hashed_password(): void
    {
        // Arrange
        $payload = [
            'nik' => '3201012345678901',
            'nama_lengkap' => 'Budi Santoso',
            'nomor_telepon' => '081234567890',
            'email' => '  Budi.Santoso@Example.COM ',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ];

        // Act
        $user = $this->service->register($payload);

        // Assert
        $this->assertSame(User::ROLE_PENYEWA, $user->role);
        $this->assertSame(User::STATUS_PENDING, $user->verification_status);
        $this->assertNull($user->verified_at);
        $this->assertSame('budi.santoso@example.com', $user->email);
        $this->assertSame('3201012345678901', $user->nik);
        $this->assertTrue(Hash::check('secret1234', $user->password));
        $this->assertDatabaseCount('users', 1);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function blockedCredentialCollisionProvider(): array
    {
        return [
            'email_collides_with_pending_user' => ['pending', 'pending@example.com', '9999999999999991', 'email'],
            'nik_collides_with_pending_user' => ['pending', 'fresh-pending@example.com', '1111111111111111', 'nik'],
            'email_collides_with_approved_user' => ['approved', 'approved@example.com', '9999999999999992', 'email'],
            'nik_collides_with_approved_user' => ['approved', 'fresh-approved@example.com', '2222222222222222', 'nik'],
            'email_collides_with_admin_user' => ['admin', 'admin@garasi.test', '9999999999999993', 'email'],
        ];
    }

    #[DataProvider('blockedCredentialCollisionProvider')]
    public function test_registration_rejects_collisions_with_non_rejected_or_admin_accounts(
        string $existingState,
        string $submittedEmail,
        string $submittedNik,
        string $expectedErrorField
    ): void {
        // Arrange
        match ($existingState) {
            'pending' => User::factory()->pending()->create([
                'email' => 'pending@example.com',
                'nik' => '1111111111111111',
            ]),
            'approved' => User::factory()->approved()->create([
                'email' => 'approved@example.com',
                'nik' => '2222222222222222',
            ]),
            'admin' => User::factory()->admin()->create([
                'email' => 'admin@garasi.test',
                'nik' => null,
            ]),
        };

        // Act & Assert
        try {
            $this->service->register([
                'nik' => $submittedNik,
                'nama_lengkap' => 'Blocked Candidate',
                'nomor_telepon' => '081234567890',
                'email' => $submittedEmail,
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
            ]);
            $this->fail("Expected ValidationException on {$expectedErrorField}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($expectedErrorField, $exception->errors());
        }

        $this->assertDatabaseCount('users', 1);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rejectedOverwriteBranchProvider(): array
    {
        return [
            'both_email_and_nik_match_same_rejected_row' => ['rejected@example.com', '3333333333333333'],
            'only_email_matches_rejected_row_with_new_nik' => ['rejected@example.com', '3333333333339999'],
            'only_nik_matches_rejected_row_with_new_email' => ['new-email@example.com', '3333333333333333'],
        ];
    }

    #[DataProvider('rejectedOverwriteBranchProvider')]
    public function test_single_rejected_row_is_overwritten_cleanly_back_to_pending(
        string $submittedEmail,
        string $submittedNik
    ): void {
        // Arrange
        $rejected = User::factory()->rejected()->create([
            'email' => 'rejected@example.com',
            'nik' => '3333333333333333',
            'nama_lengkap' => 'Nama Lama',
            'nomor_telepon' => '081111111111',
        ]);

        // Act
        $overwritten = $this->service->register([
            'nik' => $submittedNik,
            'nama_lengkap' => 'Nama Baru',
            'nomor_telepon' => '082222222222',
            'email' => $submittedEmail,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        // Assert
        $this->assertSame($rejected->id, $overwritten->id);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($submittedEmail, $overwritten->email);
        $this->assertSame($submittedNik, $overwritten->nik);
        $this->assertSame('Nama Baru', $overwritten->nama_lengkap);
        $this->assertSame('082222222222', $overwritten->nomor_telepon);
        $this->assertSame(User::STATUS_PENDING, $overwritten->verification_status);
        $this->assertNull($overwritten->verified_at);
        $this->assertTrue(Hash::check('newpassword123', $overwritten->password));
    }

    public function test_cross_user_split_collision_between_two_rejected_rows_throws_validation_exception_without_sql_crash(): void
    {
        // Arrange
        $rejectedA = User::factory()->rejected()->create([
            'email' => 'rejected-a@example.com',
            'nik' => '6666666666666661',
        ]);
        $rejectedB = User::factory()->rejected()->create([
            'email' => 'rejected-b@example.com',
            'nik' => '6666666666666662',
        ]);

        // Act & Assert
        try {
            $this->service->register([
                'nik' => '6666666666666662',
                'nama_lengkap' => 'Cross Collision',
                'nomor_telepon' => '081299999999',
                'email' => 'rejected-a@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
            $this->fail('Expected ValidationException on cross-user rejected collision.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
            $this->assertArrayHasKey('nik', $exception->errors());
        }

        $this->assertDatabaseCount('users', 2);
        $this->assertSame(User::STATUS_REJECTED, $rejectedA->fresh()->verification_status);
        $this->assertSame(User::STATUS_REJECTED, $rejectedB->fresh()->verification_status);
    }

    public function test_invalid_nik_format_and_password_mismatch_throw_validation_exception(): void
    {
        // Arrange
        $payload = [
            'nik' => '32010123456789AB',
            'nama_lengkap' => 'Invalid Format',
            'nomor_telepon' => '081234567890',
            'email' => 'invalid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ];

        // Act & Assert
        try {
            $this->service->register($payload);
            $this->fail('Expected ValidationException for invalid NIK and password confirmation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('nik', $exception->errors());
            $this->assertArrayHasKey('password', $exception->errors());
        }

        $this->assertDatabaseCount('users', 0);
    }
}
