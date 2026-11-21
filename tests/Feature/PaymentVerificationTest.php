<?php

namespace Tests\Feature;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\PaymentStatus;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Verifikasi bukti pembayaran sewa baru oleh admin (REQ-PAY-01, REQ-PAY-02, BR-12).
 */
class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 08:00');
        $this->admin = User::factory()->admin()->create();
    }

    private function pendingRental(string $code = 'C901'): Rental
    {
        $garage = Garage::factory()->ofType(GarageType::CAR)->used()->create(['code' => $code]);

        return Rental::factory()->pendingPayment()->for($garage)->create(['payment_proof_path' => "proofs/{$code}.png"]);
    }

    /** TC-089: daftar hanya berisi sewa yang menunggu verifikasi, lengkap dengan bukti pembayaran. */
    public function test_tc089_list_shows_only_pending_payments(): void
    {
        $pending = $this->pendingRental('C901');
        Rental::factory()->for(Garage::factory()->create(['code' => 'C902']))->create();
        Rental::factory()->rejectedPayment()->for(Garage::factory()->create(['code' => 'C903']))->create();

        $this->actingAs($this->admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertViewHas('rentals', fn ($rentals) => $rentals->pluck('id')->all() === [$pending->id])
            ->assertSee('C901')
            ->assertSee($pending->user->name)
            ->assertSee('Rp300.000')
            ->assertSee('proofs/C901.png')
            ->assertDontSee('C902')
            ->assertDontSee('C903');
    }

    public function test_approve_payment(): void
    {
        $rental = $this->pendingRental();

        $this->actingAs($this->admin)
            ->patch(route('admin.payments.approve', $rental))
            ->assertRedirect(route('admin.payments.index'))
            ->assertSessionHas('status');

        $rental->refresh();
        $this->assertSame(PaymentStatus::APPROVED, $rental->payment_status);
        $this->assertSame($this->admin->id, $rental->payment_verified_by);
    }

    public function test_reject_payment_releases_garage(): void
    {
        $rental = $this->pendingRental();

        $this->actingAs($this->admin)
            ->patch(route('admin.payments.reject', $rental))
            ->assertRedirect(route('admin.payments.index'))
            ->assertSessionHas('status');

        $this->assertSame(PaymentStatus::REJECTED, $rental->refresh()->payment_status);
        $this->assertSame(GarageStatus::AVAILABLE, $rental->garage->refresh()->status);
    }

    /** TC-084: sewa yang sudah diproses tidak berubah dan admin mendapat pesan. */
    public function test_processed_payment_shows_error(): void
    {
        $rental = Rental::factory()->for(Garage::factory())->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.payments.reject', $rental))
            ->assertRedirect(route('admin.payments.index'))
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatus::APPROVED, $rental->refresh()->payment_status);
    }

    /** TC-089: hanya admin yang boleh memverifikasi pembayaran. */
    public function test_renter_cannot_verify_payments(): void
    {
        $rental = $this->pendingRental();
        $renter = $rental->user;

        $this->actingAs($renter)->get(route('admin.payments.index'))->assertForbidden();
        $this->actingAs($renter)->patch(route('admin.payments.approve', $rental))->assertForbidden();
        $this->assertSame(PaymentStatus::PENDING, $rental->refresh()->payment_status);
    }

    public function test_admin_dashboard_shows_pending_payment_count(): void
    {
        $this->pendingRental('C901');
        $this->pendingRental('C902');

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('pendingPayments', 2);
    }
}
