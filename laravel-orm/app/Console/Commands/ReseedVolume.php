<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReseedVolume extends Command
{
    protected $signature = 'reseed:volume {volume}';
    protected $description = 'Truncate & reseed products/employees ke jumlah baris tertentu (deterministik)';

    public function handle(): void
    {
        $volume = (int) $this->argument('volume');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Product::truncate();
        Employee::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        fake()->seed(1000 + $volume); // seed deterministik per level, beda tiap volume tapi reproducible
        Product::factory()->count($volume)->create();

        fake()->seed(2000 + $volume);
        Employee::factory()->count($volume)->create();

        $this->info("Reseed selesai: {$volume} products, {$volume} employees.");
    }
}
