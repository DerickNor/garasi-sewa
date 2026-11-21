<?php

namespace App\Console\Commands;

use App\Services\GarageService;
use Illuminate\Console\Command;

/**
 * Kembalikan status garasi `used` menjadi `available` bila semua sewanya sudah berakhir (BR-06.3, ASM-13).
 *
 * Dijadwalkan setiap hari di routes/console.php; bisa juga dijalankan manual.
 */
class ReleaseExpiredGarages extends Command
{
    /**
     * @var string
     */
    protected $signature = 'garages:release-expired';

    /**
     * @var string
     */
    protected $description = 'Ubah status garasi yang masa sewanya sudah habis menjadi tersedia';

    /**
     * Jalankan pelepasan garasi untuk tanggal hari ini.
     */
    public function handle(GarageService $garages): int
    {
        $released = $garages->releaseExpired(today());

        $this->info("{$released} garasi dikembalikan menjadi tersedia.");

        return self::SUCCESS;
    }
}
