<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Setiap hari pukul 00:05: garasi yang masa sewanya habis kembali berstatus tersedia (BR-06.3).
 * Jalankan penjadwal dengan `php artisan schedule:work` (lokal) atau cron `schedule:run` (server).
 */
Schedule::command('garages:release-expired')->dailyAt('00:05');
