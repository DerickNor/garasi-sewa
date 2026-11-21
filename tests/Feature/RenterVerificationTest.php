<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifikasi pendaftar baru oleh admin (REQ-VER-01, REQ-VER-02, BR-09, TC-044..TC-047).
 */
class RenterVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /** TC-044: daftar hanya berisi penyewa berstatus pending, lengkap dengan data KTP. */
    public function test_tc044_list_shows_only_pending_renters(): void
    {
        $pending = User::factory()->pending()->create(['name' => 'Calon Pending', 'nik' => '3578000000000001']);
        User::factory()->create(['name' => 'Sudah Disetujui']);
        User::factory()->rejected()->create(['name' => 'Sudah Ditolak']);
        User::factory()->admin()->create(['name' => 'Admin Lain']);

        $this->actingAs($this->admin)
            ->get(route('admin.renters.index'))
            ->assertOk()
            ->assertViewHas('renters', fn ($renters) => $renters->pluck('id')->all() === [$pending->id])
            ->assertSee('Calon Pending')
            ->assertSee($pending->nik)
            ->assertSee($pending->phone)
            ->assertSee($pending->email)
            ->assertDontSee('Sudah Disetujui')
            ->assertDontSee('Sudah Ditolak')
            ->assertDontSee('Admin Lain');
    }

    /** TC-045: terima mengubah status menjadi approved dan mengisi verified_at. */
    public function test_tc045_approve_sets_status_and_verified_at(): void
    {
        $renter = User::factory()->pending()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.renters.approve', $renter))
            ->assertRedirect(route('admin.renters.index'))
            ->assertSessionHas('status');

        $renter->refresh();
        $this->assertSame(VerificationStatus::APPROVED, $renter->verification_status);
        $this->assertNotNull($renter->verified_at);
        $this->assertTrue($renter->isApprovedRenter());
    }

    /** TC-046: tolak mengubah status menjadi rejected dan hilang dari daftar pending. */
    public function test_tc046_reject_sets_status_and_removes_from_list(): void
    {
        $renter = User::factory()->pending()->create(['name' => 'Akan Ditolak']);

        $this->actingAs($this->admin)
            ->patch(route('admin.renters.reject', $renter))
            ->assertRedirect(route('admin.renters.index'));

        $renter->refresh();
        $this->assertSame(VerificationStatus::REJECTED, $renter->verification_status);
        $this->assertNull($renter->verified_at);

        $this->actingAs($this->admin)
            ->get(route('admin.renters.index'))
            ->assertViewHas('renters', fn ($renters) => $renters->doesntContain('id', $renter->id));
    }

    /** REQ-VER-02.3: penyewa yang sudah diproses tidak bisa diproses ulang. */
    public function test_already_processed_renter_cannot_be_processed_again(): void
    {
        $approved = User::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.renters.reject', $approved))
            ->assertRedirect(route('admin.renters.index'))
            ->assertSessionHas('error');

        $this->assertSame(VerificationStatus::APPROVED, $approved->refresh()->verification_status);
    }

    /** Akun admin tidak bisa diverifikasi lewat fitur ini. */
    public function test_admin_account_cannot_be_processed(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.renters.reject', $otherAdmin))
            ->assertSessionHas('error');

        $this->assertSame(VerificationStatus::APPROVED, $otherAdmin->refresh()->verification_status);
    }

    /** REQ-AUTH-03.1: penyewa tidak bisa membuka atau memakai fitur verifikasi. */
    public function test_renter_cannot_access_verification(): void
    {
        $renter = User::factory()->create();
        $target = User::factory()->pending()->create();

        $this->actingAs($renter)->get(route('admin.renters.index'))->assertForbidden();
        $this->actingAs($renter)->patch(route('admin.renters.approve', $target))->assertForbidden();

        $this->assertSame(VerificationStatus::PENDING, $target->refresh()->verification_status);
    }

    /** TC-047 (sebagian): setelah disetujui, penyewa lolos middleware renter.approved. */
    public function test_tc047_only_approved_renter_can_use_rental_features(): void
    {
        $renter = User::factory()->pending()->create();
        $this->assertFalse($renter->isApprovedRenter());

        $this->actingAs($this->admin)->patch(route('admin.renters.approve', $renter));

        $this->assertTrue($renter->refresh()->isApprovedRenter());
    }
}
