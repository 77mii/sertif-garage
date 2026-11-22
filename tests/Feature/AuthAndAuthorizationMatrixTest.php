<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthAndAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Complete route-by-role authorization matrix covering Guest, Pending, Rejected, Approved, and Admin.
     *
     * @return array<string, array{string, string, string, int, string|null}>
     */
    public static function routeAuthorizationMatrixProvider(): array
    {
        return [
            // GET /
            'guest_home' => ['guest', 'GET', 'home', 302, 'login'],
            'pending_home' => ['pending', 'GET', 'home', 302, 'tenant.status'],
            'rejected_home' => ['rejected', 'GET', 'home', 302, 'tenant.status'],
            'approved_home' => ['approved', 'GET', 'home', 302, 'tenant.rentals.create'],
            'admin_home' => ['admin', 'GET', 'home', 302, 'admin.garages.index'],

            // GET /login
            'guest_login' => ['guest', 'GET', 'login', 200, null],
            'pending_login_redirects' => ['pending', 'GET', 'login', 302, null],
            'rejected_login_redirects' => ['rejected', 'GET', 'login', 302, null],
            'approved_login_redirects' => ['approved', 'GET', 'login', 302, null],
            'admin_login_redirects' => ['admin', 'GET', 'login', 302, null],

            // GET /register
            'guest_register' => ['guest', 'GET', 'register', 200, null],
            'pending_register_redirects' => ['pending', 'GET', 'register', 302, 'home'],
            'rejected_register_allowed' => ['rejected', 'GET', 'register', 200, null],
            'approved_register_redirects' => ['approved', 'GET', 'register', 302, 'home'],
            'admin_register_redirects' => ['admin', 'GET', 'register', 302, 'home'],

            // GET /status-akun
            'guest_status_banner' => ['guest', 'GET', 'tenant.status', 302, 'login'],
            'pending_status_banner' => ['pending', 'GET', 'tenant.status', 200, null],
            'rejected_status_banner' => ['rejected', 'GET', 'tenant.status', 200, null],
            'approved_status_banner' => ['approved', 'GET', 'tenant.status', 302, 'tenant.rentals.create'],
            'admin_status_banner' => ['admin', 'GET', 'tenant.status', 302, 'admin.garages.index'],

            // GET & POST /sewa/baru
            'guest_sewa_baru_get' => ['guest', 'GET', 'tenant.rentals.create', 302, 'login'],
            'pending_sewa_baru_get' => ['pending', 'GET', 'tenant.rentals.create', 302, 'tenant.status'],
            'rejected_sewa_baru_get' => ['rejected', 'GET', 'tenant.rentals.create', 302, 'tenant.status'],
            'approved_sewa_baru_get' => ['approved', 'GET', 'tenant.rentals.create', 200, null],
            'admin_sewa_baru_get' => ['admin', 'GET', 'tenant.rentals.create', 403, null],
            'guest_sewa_baru_post' => ['guest', 'POST', 'tenant.rentals.store', 302, 'login'],
            'pending_sewa_baru_post' => ['pending', 'POST', 'tenant.rentals.store', 302, 'tenant.status'],
            'rejected_sewa_baru_post' => ['rejected', 'POST', 'tenant.rentals.store', 302, 'tenant.status'],
            'admin_sewa_baru_post' => ['admin', 'POST', 'tenant.rentals.store', 403, null],

            // GET & POST /admin/garasi
            'guest_admin_garages_get' => ['guest', 'GET', 'admin.garages.index', 302, 'login'],
            'pending_admin_garages_get' => ['pending', 'GET', 'admin.garages.index', 403, null],
            'rejected_admin_garages_get' => ['rejected', 'GET', 'admin.garages.index', 403, null],
            'approved_admin_garages_get' => ['approved', 'GET', 'admin.garages.index', 403, null],
            'admin_admin_garages_get' => ['admin', 'GET', 'admin.garages.index', 200, null],
            'approved_admin_garages_post' => ['approved', 'POST', 'admin.garages.store', 403, null],

            // GET /admin/verifikasi + POST approve/reject
            'guest_admin_verifications' => ['guest', 'GET', 'admin.verifications.index', 302, 'login'],
            'pending_admin_verifications' => ['pending', 'GET', 'admin.verifications.index', 403, null],
            'rejected_admin_verifications' => ['rejected', 'GET', 'admin.verifications.index', 403, null],
            'approved_admin_verifications' => ['approved', 'GET', 'admin.verifications.index', 403, null],
            'admin_admin_verifications' => ['admin', 'GET', 'admin.verifications.index', 200, null],
            'approved_admin_approve_post' => ['approved', 'POST', 'admin.verifications.approve', 403, null],
            'approved_admin_reject_post' => ['approved', 'POST', 'admin.verifications.reject', 403, null],

            // GET /admin/perpanjang + show/store
            'guest_admin_extensions' => ['guest', 'GET', 'admin.extensions.index', 302, 'login'],
            'pending_admin_extensions' => ['pending', 'GET', 'admin.extensions.index', 403, null],
            'rejected_admin_extensions' => ['rejected', 'GET', 'admin.extensions.index', 403, null],
            'approved_admin_extensions' => ['approved', 'GET', 'admin.extensions.index', 403, null],
            'admin_admin_extensions' => ['admin', 'GET', 'admin.extensions.index', 200, null],
            'approved_admin_extension_show' => ['approved', 'GET', 'admin.extensions.show', 403, null],
            'approved_admin_extension_post' => ['approved', 'POST', 'admin.extensions.store', 403, null],
            'admin_admin_extension_show' => ['admin', 'GET', 'admin.extensions.show', 200, null],

            // GET /admin/laporan
            'guest_admin_reports' => ['guest', 'GET', 'admin.reports.index', 302, 'login'],
            'pending_admin_reports' => ['pending', 'GET', 'admin.reports.index', 403, null],
            'rejected_admin_reports' => ['rejected', 'GET', 'admin.reports.index', 403, null],
            'approved_admin_reports' => ['approved', 'GET', 'admin.reports.index', 403, null],
            'admin_admin_reports' => ['admin', 'GET', 'admin.reports.index', 200, null],
        ];
    }

    #[DataProvider('routeAuthorizationMatrixProvider')]
    public function test_route_authorization_matrix_enforces_exact_role_and_status_boundaries(
        string $actor,
        string $method,
        string $routeName,
        int $expectedStatus,
        ?string $expectedRedirectRoute
    ): void {
        // Arrange
        Carbon::setTestNow(Carbon::create(2026, 11, 21, 10, 0, 0, 'Asia/Jakarta'));

        $pendingTarget = User::factory()->pending()->create();
        $activeGarage = Garage::factory()->mobil()->used()->create();
        $activeRental = Rental::factory()->create([
            'garage_id' => $activeGarage->id,
            'start_date' => '2026-11-21',
            'end_date' => '2026-11-25',
        ]);

        $actorUser = match ($actor) {
            'guest' => null,
            'pending' => User::factory()->pending()->create(),
            'rejected' => User::factory()->rejected()->create(),
            'approved' => User::factory()->approved()->create(),
            'admin' => User::factory()->admin()->create(),
        };

        $url = match ($routeName) {
            'admin.verifications.approve', 'admin.verifications.reject' => route($routeName, $pendingTarget),
            'admin.extensions.show', 'admin.extensions.store' => route($routeName, $activeRental),
            default => route($routeName),
        };

        // Act
        $request = $actorUser !== null ? $this->actingAs($actorUser) : $this;
        $response = $method === 'POST' ? $request->post($url, []) : $request->get($url);

        // Assert
        $response->assertStatus($expectedStatus);

        if ($expectedRedirectRoute !== null) {
            $response->assertRedirect(route($expectedRedirectRoute));
        }
    }

    public function test_pending_and_rejected_tenants_can_login_and_see_appropriate_status_banners(): void
    {
        // Arrange
        $pending = User::factory()->pending()->create([
            'nama_lengkap' => 'Pending Tenant',
            'email' => 'pending-user@example.com',
            'password' => 'password123',
        ]);
        $rejected = User::factory()->rejected()->create([
            'nama_lengkap' => 'Rejected Tenant',
            'email' => 'rejected-user@example.com',
            'password' => 'password123',
        ]);

        // Act & Assert: pending login -> status banner
        $this->post(route('login.store'), [
            'email' => $pending->email,
            'password' => 'password123',
        ])->assertRedirect(route('tenant.status'));

        $this->get(route('tenant.status'))
            ->assertOk()
            ->assertSee('Menunggu Verifikasi Admin')
            ->assertSee('Pending Tenant');

        $this->post(route('logout'))->assertRedirect(route('login'));

        // Act & Assert: rejected login -> rejected banner with re-register link
        $this->post(route('login.store'), [
            'email' => $rejected->email,
            'password' => 'password123',
        ])->assertRedirect(route('tenant.status'));

        $this->get(route('tenant.status'))
            ->assertOk()
            ->assertSee('Pendaftaran Ditolak')
            ->assertSee('Rejected Tenant')
            ->assertSee(route('register'));
    }

    public function test_rejected_tenant_http_re_registration_overwrites_row_and_blocks_cross_user_collision(): void
    {
        // Arrange
        $rejectedA = User::factory()->rejected()->create([
            'email' => 'rejected-a@example.com',
            'nik' => '3201012345670001',
            'nama_lengkap' => 'Nama Lama A',
        ]);
        $rejectedB = User::factory()->rejected()->create([
            'email' => 'rejected-b@example.com',
            'nik' => '3201012345670002',
            'nama_lengkap' => 'Nama Lama B',
        ]);

        // Act & Assert: cross-user collision fails validation without SQL crash
        $this->post(route('register.store'), [
            'nik' => '3201012345670002',
            'nama_lengkap' => 'Collision Attempt',
            'nomor_telepon' => '081234567890',
            'email' => 'rejected-a@example.com',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertSessionHasErrors(['email', 'nik']);

        // Act & Assert: valid overwrite on Rejected A resets to pending
        $this->actingAs($rejectedA)
            ->post(route('register.store'), [
                'nik' => '3201012345670001',
                'nama_lengkap' => 'Nama Revisi A',
                'nomor_telepon' => '081299990000',
                'email' => 'rejected-a@example.com',
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ])
            ->assertRedirect(route('tenant.status'));

        $rejectedA->refresh();
        $this->assertDatabaseCount('users', 2);
        $this->assertSame('Nama Revisi A', $rejectedA->nama_lengkap);
        $this->assertSame(User::STATUS_PENDING, $rejectedA->verification_status);
        $this->assertNull($rejectedA->verified_at);
        $this->assertTrue(Hash::check('newsecret123', $rejectedA->password));
        $this->assertSame(User::STATUS_REJECTED, $rejectedB->fresh()->verification_status);
    }
}
