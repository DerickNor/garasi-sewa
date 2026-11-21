<?php

namespace Tests\Feature;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Perpanjangan sewa oleh admin lewat HTTP (REQ-EXT-01..04, TC-066..TC-073).
 */
class RentalExtensionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Rental $rental;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 08:00');
        Storage::fake('public');

        $this->admin = User::factory()->admin()->create();
        $garage = Garage::factory()->ofType(GarageType::CAR)->used()->create(['code' => 'C901']);
        $this->rental = Rental::factory()->for($garage)->between('2026-11-18', '2026-11-24')->create();
    }

    /** TC-066: daftar hanya berisi sewa yang belum berakhir. */
    public function test_tc066_lists_only_rentals_that_have_not_ended(): void
    {
        $upcoming = Rental::factory()->for(Garage::factory()->create(['code' => 'C902']))->between('2026-12-01', '2026-12-05')->create();
        Rental::factory()->for(Garage::factory()->create(['code' => 'C903']))->between('2026-11-10', '2026-11-21')->create();

        $this->actingAs($this->admin)
            ->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertViewHas('rentals', fn ($rentals) => $rentals->pluck('id')->all() === [$this->rental->id, $upcoming->id])
            ->assertSee('C901')
            ->assertSee('C902')
            ->assertDontSee('C903')
            ->assertSee($this->rental->user->name);
    }

    public function test_extension_form_shows_rental_details(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.extensions.create', $this->rental))
            ->assertOk()
            ->assertSee('C901')
            ->assertSee('24 Nov 2026')
            ->assertSee('name="new_end_date"', false);
    }

    public function test_preview_returns_months_days_and_cost(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.extensions.preview', $this->rental), ['new_end_date' => '2026-12-27'])
            ->assertOk()
            ->assertJsonPath('months', 1)
            ->assertJsonPath('days', 3)
            ->assertJsonPath('cost', 2800000)
            ->assertJsonPath('last_day', '2026-12-26');

        $this->actingAs($this->admin)
            ->postJson(route('admin.extensions.preview', $this->rental), ['new_end_date' => '2026-11-24'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('new_end_date');
    }

    /** TC-067: perpanjangan valid tersimpan dan tanggal selesai berubah. */
    public function test_tc067_valid_extension_is_saved(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.extensions.store', $this->rental), [
                'new_end_date' => '2026-11-27',
                'payment_proof' => UploadedFile::fake()->image('bukti.png'),
            ])
            ->assertRedirect(route('admin.extensions.index'))
            ->assertSessionHas('status', fn (string $message) => str_contains($message, 'Rp300.000'));

        $extension = RentalExtension::sole();
        $this->assertEquals(300000, $extension->extra_cost);
        $this->assertSame('2026-11-27', $this->rental->refresh()->end_date->toDateString());
    }

    /** TC-068: tanggal baru harus setelah tanggal selesai lama. */
    public function test_tc068_new_end_date_must_be_after_old_end_date(): void
    {
        foreach (['2026-11-24', '2026-11-20', 'bukan-tanggal'] as $date) {
            $this->actingAs($this->admin)
                ->post(route('admin.extensions.store', $this->rental), [
                    'new_end_date' => $date,
                    'payment_proof' => UploadedFile::fake()->image('bukti.png'),
                ])
                ->assertSessionHasErrors('new_end_date');
        }

        $this->assertSame(0, RentalExtension::count());
    }

    /** TC-071: bukti pembayaran wajib berupa gambar. */
    public function test_tc071_payment_proof_is_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.extensions.store', $this->rental), ['new_end_date' => '2026-11-27'])
            ->assertSessionHasErrors('payment_proof');
        $this->actingAs($this->admin)
            ->post(route('admin.extensions.store', $this->rental), [
                'new_end_date' => '2026-11-27',
                'payment_proof' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('payment_proof');

        $this->assertSame(0, RentalExtension::count());
    }

    /** TC-072: sewa yang sudah berakhir tidak bisa dibuka form perpanjangannya maupun disimpan. */
    public function test_tc072_ended_rental_cannot_be_extended(): void
    {
        $ended = Rental::factory()->for(Garage::factory()->create(['code' => 'C904']))->between('2026-11-10', '2026-11-21')->create();

        $this->actingAs($this->admin)
            ->get(route('admin.extensions.create', $ended))
            ->assertRedirect(route('admin.extensions.index'))
            ->assertSessionHas('error');
        $this->actingAs($this->admin)
            ->post(route('admin.extensions.store', $ended), [
                'new_end_date' => '2026-11-25',
                'payment_proof' => UploadedFile::fake()->image('bukti.png'),
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, RentalExtension::count());
    }

    /** TC-073: perpanjangan yang menabrak sewa berikutnya ditolak dengan pesan. */
    public function test_tc073_overlapping_extension_is_rejected(): void
    {
        Rental::factory()->for($this->rental->garage)->between('2026-11-26', '2026-11-30')->create();

        $this->actingAs($this->admin)
            ->post(route('admin.extensions.store', $this->rental), [
                'new_end_date' => '2026-11-28',
                'payment_proof' => UploadedFile::fake()->image('bukti.png'),
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, RentalExtension::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_renter_cannot_extend_rentals(): void
    {
        $renter = $this->rental->user;

        $this->actingAs($renter)->get(route('admin.extensions.index'))->assertForbidden();
        $this->actingAs($renter)
            ->post(route('admin.extensions.store', $this->rental), [
                'new_end_date' => '2026-11-27',
                'payment_proof' => UploadedFile::fake()->image('bukti.png'),
            ])
            ->assertForbidden();

        $this->assertSame(0, RentalExtension::count());
    }
}
