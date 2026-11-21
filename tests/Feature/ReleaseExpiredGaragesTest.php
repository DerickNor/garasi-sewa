<?php

namespace Tests\Feature;

use App\Enums\GarageStatus;
use App\Models\Garage;
use App\Models\Rental;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Perintah terjadwal pelepasan garasi yang masa sewanya habis (BR-06.3, ASM-13).
 */
class ReleaseExpiredGaragesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_releases_garages_whose_rentals_have_ended(): void
    {
        Carbon::setTestNow('2026-11-21 00:05');
        $ended = Garage::factory()->used()->create(['code' => 'C901']);
        $active = Garage::factory()->used()->create(['code' => 'C902']);
        Rental::factory()->for($ended)->between('2026-11-15', '2026-11-21')->create();
        Rental::factory()->for($active)->between('2026-11-20', '2026-11-25')->create();

        $this->artisan('garages:release-expired')
            ->expectsOutputToContain('1 garasi')
            ->assertSuccessful();

        $this->assertSame(GarageStatus::AVAILABLE, $ended->refresh()->status);
        $this->assertSame(GarageStatus::USED, $active->refresh()->status);
    }

    public function test_command_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event) => str_contains((string) $event->command, 'garages:release-expired'));

        $this->assertCount(1, $events);
        $this->assertSame('5 0 * * *', $events->first()->expression);
    }
}
