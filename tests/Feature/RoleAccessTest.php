<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pembatasan akses per role dan login (REQ-AUTH-02, REQ-AUTH-03, REQ-VER-03, TC-039..TC-043).
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /** TC-039: penyewa membuka halaman admin ditolak 403. */
    public function test_tc039_renter_cannot_open_admin_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    /** TC-040: admin membuka halaman penyewa ditolak 403. */
    public function test_tc040_admin_cannot_open_renter_pages(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('renter.dashboard'))
            ->assertForbidden();
    }

    /** TC-041: pengunjung yang belum login diarahkan ke halaman login. */
    public function test_tc041_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('renter.dashboard'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /** TC-042: login admin dan penyewa diarahkan ke dashboard masing-masing. */
    public function test_tc042_login_redirects_to_dashboard_per_role(): void
    {
        $cases = [
            [User::factory()->admin()->create(), 'admin.dashboard'],
            [User::factory()->create(), 'renter.dashboard'],
            [User::factory()->pending()->create(), 'renter.dashboard'],
        ];

        foreach ($cases as [$user, $expectedRoute]) {
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route('dashboard'));
            $this->get(route('dashboard'))->assertRedirect(route($expectedRoute));
            $this->get(route($expectedRoute))->assertOk();

            $this->post('/logout');
        }
    }

    /** TC-043: password salah gagal dengan pesan umum (tidak menyebut field mana yang salah). */
    public function test_tc043_wrong_password_shows_generic_message(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'salah-sekali'])
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);
        $this->post('/login', ['email' => 'tidak-ada@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();
    }

    /** REQ-VER-03: penyewa pending/rejected tidak bisa membuka fitur sewa, penyewa approved bisa. */
    public function test_only_approved_renter_passes_renter_approved_middleware(): void
    {
        Route::middleware(['web', 'auth', 'role:penyewa', 'renter.approved'])
            ->get('/_test/approved-only', fn () => 'ok');

        foreach ([User::factory()->pending()->create(), User::factory()->rejected()->create()] as $renter) {
            $this->actingAs($renter)
                ->get('/_test/approved-only')
                ->assertRedirect(route('renter.dashboard'))
                ->assertSessionHas('error');
        }

        $this->actingAs(User::factory()->create())
            ->get('/_test/approved-only')
            ->assertOk()
            ->assertSee('ok');
    }

    /** REQ-VER-03: dashboard penyewa menampilkan status verifikasi. */
    public function test_renter_dashboard_shows_verification_status(): void
    {
        $this->actingAs(User::factory()->pending()->create())
            ->get(route('renter.dashboard'))
            ->assertSee('menunggu verifikasi admin');

        $this->actingAs(User::factory()->rejected()->create())
            ->get(route('renter.dashboard'))
            ->assertSee('ditolak');
    }

    public function test_logout_ends_session(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
