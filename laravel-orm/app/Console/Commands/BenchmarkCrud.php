<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Employee;
use Illuminate\Console\Command;

class BenchmarkCrud extends Command
{
    protected $signature = 'benchmark:crud {--volume=5000} {--reps=30}';
    protected $description = 'Benchmark waktu eksekusi CRUD via Eloquent (App ORM)';

    public function handle(): void
    {
        $volume = (int) $this->option('volume');
        $reps = (int) $this->option('reps');

        $this->benchmarkEntity('product', Product::class, $volume, $reps, [
            'name' => 'Benchmark Product',
            'sku' => null,
            'category' => 'Benchmark',
            'price' => 99000,
            'stock' => 10,
        ]);

        $this->benchmarkEntity('employee', Employee::class, $volume, $reps, [
            'name' => 'Benchmark Employee',
            'email' => null,
            'position' => 'Staff',
            'department' => 'Benchmark',
            'salary' => 5000000,
            'hire_date' => now()->format('Y-m-d'),
        ]);
    }

    private function benchmarkEntity(string $label, string $modelClass, int $volume, int $reps, array $template): void
    {
        $results = ['create' => [], 'read' => [], 'update' => [], 'delete' => []];

        $bufferData = $template;
        $bufferData[$label === 'product' ? 'sku' : 'email'] = 'BENCH-BUFFER-' . $label;
        $buffer = $modelClass::create($bufferData);

        // ===== CREATE =====
        for ($i = 0; $i < $reps; $i++) {
            $data = $template;
            $data[$label === 'product' ? 'sku' : 'email'] = 'BENCH-' . $label . '-' . uniqid();

            $start = microtime(true);
            $row = $modelClass::create($data);
            $results['create'][] = (microtime(true) - $start) * 1000;

            $row->delete();
        }

        // ===== READ =====
        for ($i = 0; $i < $reps; $i++) {
            $start = microtime(true);
            $modelClass::orderByDesc('id')->paginate(20);
            $results['read'][] = (microtime(true) - $start) * 1000;
        }

        // ===== UPDATE =====
        for ($i = 0; $i < $reps; $i++) {
            $start = microtime(true);
            $buffer->update(['name' => 'Benchmark Update ' . $i]);
            $results['update'][] = (microtime(true) - $start) * 1000;
        }

        // ===== DELETE =====
        for ($i = 0; $i < $reps; $i++) {
            $data = $template;
            $data[$label === 'product' ? 'sku' : 'email'] = 'BENCH-DEL-' . $label . '-' . uniqid();
            $row = $modelClass::create($data);

            $start = microtime(true);
            $row->delete();
            $results['delete'][] = (microtime(true) - $start) * 1000;
        }

        $buffer->delete();

        $this->exportCsv($label, $volume, $results);
        $this->info("Benchmark {$label} (volume={$volume}) selesai.");
    }

    private function exportCsv(string $label, int $volume, array $results): void
    {
        foreach ($results as $operation => $times) {
            $path = storage_path("app/benchmark/orm-{$label}-{$operation}-{$volume}.csv");
            $fh = fopen($path, 'w');
            fputcsv($fh, ['run_number', 'execution_time_ms']);
            foreach ($times as $i => $ms) {
                fputcsv($fh, [$i + 1, round($ms, 4)]);
            }
            fclose($fh);
        }
    }
}
