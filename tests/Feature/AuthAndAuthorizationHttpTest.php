<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndAuthorizationHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_tenant_can_register_and_login_to_status_banner_but_is_blocked_from_sewa_baru_and_admin(): void
    {
        $this->post(route('register.store'), [
            'nik' => '3201012345678901',
            'nama_lengkap' => 'Budi Santoso',
            'nomor_telepon' => '081234567890',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $tenant = User::query()->where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame(User::STATUS_PENDING, $tenant->verification_status);

        $this->post(route('login.store'), [
            'email' => 'budi@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('tenant.status'));

        $this->get(route('tenant.status'))
            ->assertOk()
            ->assertSee('Menunggu Verifikasi Admin')
            ->assertSee('Budi Santoso');

        $this->get(route('tenant.rentals.create'))
            ->assertRedirect(route('tenant.status'));

        $this->get(route('admin.garages.index'))
            ->assertForbidden();

        $this->get(route('admin.verifications.index'))
            ->assertForbidden();

        $this->get(route('admin.extensions.index'))
            ->assertForbidden();

        $this->get(route('admin.reports.index'))
            ->assertForbidden();
    }

    public function test_admin_can_reject_and_approve_pending_tenants_and_rejected_tenant_can_re_register(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->pending()->create([
            'nik' => '3201012345678902',
            'nama_lengkap' => 'Sari Wulandari',
            'email' => 'sari@example.com',
            'password' => 'password123',
        ]);

        // Admin sees pending user and rejects them
        $this->actingAs($admin)
            ->get(route('admin.verifications.index'))
            ->assertOk()
            ->assertSee('Sari Wulandari')
            ->assertSee('3201012345678902');

        $this->actingAs($admin)
            ->post(route('admin.verifications.reject', $pending))
            ->assertRedirect(route('admin.verifications.index'));

        $this->assertSame(User::STATUS_REJECTED, $pending->fresh()->verification_status);

        // Logout admin, rejected tenant logs in and sees rejected banner + re-register link
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => 'sari@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('tenant.status'));

        $this->get(route('tenant.status'))
            ->assertOk()
            ->assertSee('Pendaftaran Ditolak')
            ->assertSee(route('register'));

        $this->get(route('tenant.rentals.create'))
            ->assertRedirect(route('tenant.status'));

        // Re-registering overwrites existing rejected row back to pending
        $this->post(route('register.store'), [
            'nik' => '3201012345678902',
            'nama_lengkap' => 'Sari Wulandari Revisi',
            'nomor_telepon' => '081299998888',
            'email' => 'sari@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('tenant.status'));

        $pending->refresh();
        $this->assertDatabaseCount('users', 2);
        $this->assertSame(User::STATUS_PENDING, $pending->verification_status);
        $this->assertSame('Sari Wulandari Revisi', $pending->nama_lengkap);
        $this->assertNull($pending->verified_at);
        $this->assertTrue(Hash::check('newpassword123', $pending->password));

        // Admin approves the re-registered tenant
        $this->actingAs($admin)
            ->post(route('admin.verifications.approve', $pending))
            ->assertRedirect(route('admin.verifications.index'));

        $this->assertSame(User::STATUS_APPROVED, $pending->fresh()->verification_status);
    }

    public function test_http_registration_blocks_conflicting_pending_approved_and_cross_rejected_credentials(): void
    {
        User::factory()->approved()->create([
            'email' => 'approved@example.com',
            'nik' => '1111111111111111',
        ]);

        $rejectedA = User::factory()->rejected()->create([
            'email' => 'rejected-a@example.com',
            'nik' => '2222222222222221',
        ]);

        $rejectedB = User::factory()->rejected()->create([
            'email' => 'rejected-b@example.com',
            'nik' => '2222222222222222',
        ]);

        // Conflict with approved account
        $this->post(route('register.store'), [
            'nik' => '9999999999999991',
            'nama_lengkap' => 'Duplicate Email',
            'nomor_telepon' => '081234567890',
            'email' => 'approved@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        // Cross-user collision between Rejected A email & Rejected B nik
        $this->post(route('register.store'), [
            'nik' => $rejectedB->nik,
            'nama_lengkap' => 'Cross Collision',
            'nomor_telepon' => '081234567890',
            'email' => $rejectedA->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['email', 'nik']);
    }

    public function test_role_boundaries_for_approved_tenant_and_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $approvedTenant = User::factory()->approved()->create();
        $garage = Garage::factory()->mobil()->create();

        // Approved tenant can access Sewa Baru, redirected away from status banner, forbidden from /admin/*
        $this->actingAs($approvedTenant)
            ->get(route('tenant.rentals.create'))
            ->assertOk()
            ->assertSee('Sewa Baru');

        $this->actingAs($approvedTenant)
            ->get(route('tenant.status'))
            ->assertRedirect(route('tenant.rentals.create'));

        $this->actingAs($approvedTenant)
            ->get(route('admin.garages.index'))
            ->assertForbidden();

        // Admin can access admin pages, forbidden from tenant booking (GET & POST)
        $this->actingAs($admin)
            ->get(route('admin.garages.index'))
            ->assertOk()
            ->assertSee('Buat Garasi Baru');

        $this->actingAs($admin)
            ->get(route('tenant.rentals.create'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('tenant.rentals.store'), [
                'garage_id' => $garage->id,
                'garage_type' => Garage::TYPE_MOBIL,
                'vehicle_type' => 'mobil',
                'license_plate' => 'B 1234 CD',
                'vehicle_brand_type' => 'Toyota Avanza',
                'rental_type' => 'harian',
                'jumlah_hari' => 1,
            ])
            ->assertForbidden();
    }
}
