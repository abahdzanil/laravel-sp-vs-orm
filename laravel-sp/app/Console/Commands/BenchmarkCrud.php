<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BenchmarkCrud extends Command
{
    protected $signature = 'benchmark:crud {--volume=5000} {--reps=30}';
    protected $description = 'Benchmark waktu eksekusi CRUD via Stored Procedure (App SP)';

    public function handle(): void
    {
        $volume = (int) $this->option('volume');
        $reps = (int) $this->option('reps');

        $this->benchmarkProduct($volume, $reps);
        $this->benchmarkEmployee($volume, $reps);
    }

    private function benchmarkProduct(int $volume, int $reps): void
    {
        $results = ['create' => [], 'read' => [], 'update' => [], 'delete' => []];

        DB::statement('CALL sp_product_create(?, ?, ?, ?, ?)', [
            'Benchmark Product', 'BENCH-BUFFER-product', 'Benchmark', 99000, 10,
        ]);
        $bufferId = DB::select("SELECT id FROM products WHERE sku = 'BENCH-BUFFER-product'")[0]->id;

        // ===== CREATE =====
        for ($i = 0; $i < $reps; $i++) {
            $sku = 'BENCH-product-' . uniqid();

            $start = microtime(true);
            DB::statement('CALL sp_product_create(?, ?, ?, ?, ?)', [
                'Benchmark Product', $sku, 'Benchmark', 99000, 10,
            ]);
            $results['create'][] = (microtime(true) - $start) * 1000;

            DB::statement('DELETE FROM products WHERE sku = ?', [$sku]);
        }

        // ===== READ =====
        for ($i = 0; $i < $reps; $i++) {
            $start = microtime(true);
            DB::select('CALL sp_product_list(?, ?)', [20, 0]);
            $results['read'][] = (microtime(true) - $start) * 1000;
        }

        // ===== UPDATE =====
        for ($i = 0; $i < $reps; $i++) {
            $start = microtime(true);
            DB::statement('CALL sp_product_update(?, ?, ?, ?, ?, ?)', [
                $bufferId, 'Benchmark Update ' . $i, 'BENCH-BUFFER-product', 'Benchmark', 99000, 10,
            ]);
            $results['update'][] = (microtime(true) - $start) * 1000;
        }

        // ===== DELETE =====
        for ($i = 0; $i < $reps; $i++) {
            $sku = 'BENCH-DEL-product-' . uniqid();
            DB::statement('CALL sp_product_create(?, ?, ?, ?, ?)', [
                'Benchmark Product', $sku, 'Benchmark', 99000, 10,
            ]);
            $id = DB::select('SELECT id FROM products WHERE sku = ?', [$sku])[0]->id;

            $start = microtime(true);
            DB::statement('CALL sp_product_delete(?)', [$id]);
            $results['delete'][] = (microtime(true) - $start) * 1000;
        }

        DB::statement('CALL sp_product_delete(?)', [$bufferId]);

        $this->exportCsv('product', $volume, $results);
        $this->info("Benchmark product (volume={$volume}) selesai.");
    }

    private function benchmarkEmployee(int $volume, int $reps): void
    {
        $results = ['create' => [], 'read' => [], 'update' => [], 'delete' => []];

        DB::statement('CALL sp_employee_create(?, ?, ?, ?, ?, ?)', [
            'Benchmark Employee', 'bench-buffer-employee@example.com', 'Staff', 'Benchmark', 5000000, now()->format('Y-m-d'),
        ]);
        $bufferId = DB::select("SELECT id FROM employees WHERE email = 'bench-buffer-employee@example.com'")[0]->id;

        // ===== CREATE =====
        for ($i = 0; $i < $reps; $i++) {
            $email = 'bench-employee-' . uniqid() . '@example.com';

            $start = microtime(true);
            DB::statement('CALL sp_employee_create(?, ?, ?, ?, ?, ?)', [
                'Benchmark Employee', $email, 'Staff', 'Benchmark', 5000000, now()->format('Y-m-d'),
            ]);
            $results['create'][] = (microtime(true) - $start) * 1000;

            DB::statement('DELETE FROM employees WHERE email = ?', [$email]);
        }

        // ===== READ =====
        for ($i = 0; $i < $reps; $i++) {
            $start = microtime(true);
            DB::select('CALL sp_employee_list(?, ?)', [20, 0]);
            $results['read'][] = (microtime(true) - $start) * 1000;
        }

        // ===== UPDATE =====
        for ($i = 0; $i < $reps; $i++) {
            $start = microtime(true);
            DB::statement('CALL sp_employee_update(?, ?, ?, ?, ?, ?, ?)', [
                $bufferId, 'Benchmark Update ' . $i, 'bench-buffer-employee@example.com', 'Staff', 'Benchmark', 5000000, now()->format('Y-m-d'),
            ]);
            $results['update'][] = (microtime(true) - $start) * 1000;
        }

        // ===== DELETE =====
        for ($i = 0; $i < $reps; $i++) {
            $email = 'bench-del-employee-' . uniqid() . '@example.com';
            DB::statement('CALL sp_employee_create(?, ?, ?, ?, ?, ?)', [
                'Benchmark Employee', $email, 'Staff', 'Benchmark', 5000000, now()->format('Y-m-d'),
            ]);
            $id = DB::select('SELECT id FROM employees WHERE email = ?', [$email])[0]->id;

            $start = microtime(true);
            DB::statement('CALL sp_employee_delete(?)', [$id]);
            $results['delete'][] = (microtime(true) - $start) * 1000;
        }

        DB::statement('CALL sp_employee_delete(?)', [$bufferId]);

        $this->exportCsv('employee', $volume, $results);
        $this->info("Benchmark employee (volume={$volume}) selesai.");
    }

    private function exportCsv(string $label, int $volume, array $results): void
    {
        foreach ($results as $operation => $times) {
            $path = storage_path("app/benchmark/sp-{$label}-{$operation}-{$volume}.csv");
            $fh = fopen($path, 'w');
            fputcsv($fh, ['run_number', 'execution_time_ms']);
            foreach ($times as $i => $ms) {
                fputcsv($fh, [$i + 1, round($ms, 4)]);
            }
            fclose($fh);
        }
    }
}
