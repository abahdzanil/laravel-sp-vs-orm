# Fase 2 — Monitoring & Benchmark (Panduan Step by Step)

Tujuan: mengukur waktu eksekusi CRUD (create, read, update, delete) di App ORM vs
App SP, pada 3 level volume data (**5.000 / 15.000 / 25.000** baris), masing-masing
**30 repetisi** per operasi, hasil diekspor ke CSV untuk dianalisis statistik.

> Prinsip: pengukuran dilakukan **langsung di layer akses data** (Eloquent call /
> SP call), bukan lewat HTTP — supaya tidak ada noise dari network/routing/middleware.
> Load testing HTTP (opsional, buat bahas skalabilitas) dibahas terpisah di akhir.

---

## 0. Struktur folder hasil

Di kedua project, buat folder:

```bash
mkdir -p storage/app/benchmark
```

Isi `.gitignore` App ORM & App SP, tambahkan baris:
```
/storage/app/benchmark/*.csv
```
(hasil CSV nggak perlu di-commit ke git, tapi disalin manual ke `docs/results/` nanti
buat dilampirkan ke paper.)

---

## 1. Command Reseed per Level Volume (App ORM)

```bash
php artisan make:command ReseedVolume
```

`app/Console/Commands/ReseedVolume.php`

```php
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
```

Jalankan untuk tiap level, lalu export data-only (tanpa `CREATE TABLE`, biar skema
tetap ikut migration App SP sendiri saat diimpor):

```bash
php artisan reseed:volume 5000
mysqldump -u root --no-create-info db_orm_bench products employees > seed-data-5000.sql

php artisan reseed:volume 15000
mysqldump -u root --no-create-info db_orm_bench products employees > seed-data-15000.sql

php artisan reseed:volume 25000
mysqldump -u root --no-create-info db_orm_bench products employees > seed-data-25000.sql
```

Simpan ketiga file `.sql` ini di root repo (sejajar `laravel-orm/`, `laravel-sp/`),
dipakai bareng oleh App SP di langkah berikutnya.

---

## 2. Import ke App SP per Level

Sebelum tiap sesi benchmark di App SP, kosongkan & impor ulang datanya:

```bash
mysql -u root -e "SET FOREIGN_KEY_CHECKS=0; TRUNCATE products; TRUNCATE employees; SET FOREIGN_KEY_CHECKS=1;" db_sp_bench
mysql -u root db_sp_bench < seed-data-5000.sql
```

(ganti nama file sesuai level yang mau diuji: `seed-data-15000.sql`, `seed-data-25000.sql`)

---

## 3. Benchmark Command — App ORM

```bash
php artisan make:command BenchmarkCrud
```

`app/Console/Commands/BenchmarkCrud.php`

```php
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
```

---

## 4. Benchmark Command — App SP

```bash
php artisan make:command BenchmarkCrud
```

`app/Console/Commands/BenchmarkCrud.php`

```php
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
```

---

## 5. Runbook Eksekusi (urutan lengkap)

Jalankan berurutan, satu level volume tuntas dulu (reseed → benchmark ORM → reseed
SP → benchmark SP) sebelum pindah ke level berikutnya, supaya tidak ada volume data
yang tercampur:

```bash
# ===== LEVEL 5.000 =====
cd laravel-orm
php artisan reseed:volume 5000
mysqldump -u root --no-create-info db_orm_bench products employees > ../seed-data-5000.sql
php artisan benchmark:crud --volume=5000 --reps=30

cd ../laravel-sp
mysql -u root -e "SET FOREIGN_KEY_CHECKS=0; TRUNCATE products; TRUNCATE employees; SET FOREIGN_KEY_CHECKS=1;" db_sp_bench
mysql -u root db_sp_bench < ../seed-data-5000.sql
php artisan benchmark:crud --volume=5000 --reps=30

# ===== LEVEL 15.000 ===== (ulangi pola yang sama, ganti 5000 -> 15000)
# ===== LEVEL 25.000 ===== (ulangi pola yang sama, ganti 5000 -> 25000)
```

Setelah semua level selesai, kumpulkan hasil:

```bash
mkdir -p ../docs/results
cp laravel-orm/storage/app/benchmark/*.csv ../docs/results/
cp laravel-sp/storage/app/benchmark/*.csv ../docs/results/
```

Total file CSV yang dihasilkan: 2 app x 2 entitas x 4 operasi x 3 level = **48 file**,
masing-masing berisi 30 baris waktu eksekusi (ms).

---

## 6. (Opsional) EXPLAIN ANALYZE — pembanding query plan

Buat tambahan bukti kualitatif di BAB V (bukan wajib, tapi memperkuat pembahasan),
bandingkan query plan Eloquent vs SP untuk operasi yang sama:

```sql
-- App ORM punya query plan setara dengan:
EXPLAIN ANALYZE SELECT * FROM products ORDER BY id DESC LIMIT 20 OFFSET 0;

-- App SP:
EXPLAIN ANALYZE CALL sp_product_list(20, 0);
```

Jalankan di kedua database (`db_orm_bench`, `db_sp_bench`) pada level volume 25.000
(kondisi terberat), catat "Actual Time" dan strategi index yang dipakai MySQL.

---

## 7. Langkah selanjutnya (Fase 3 — Journaling)

Setelah 48 file CSV terkumpul, langkah berikutnya:
1. Hitung rata-rata, standar deviasi, min/max per kombinasi (operasi x level x app)
2. Uji signifikansi statistik (mis. Mann-Whitney U test atau independent t-test,
   tergantung distribusi data — dicek dulu normalitasnya)
3. Visualisasi (bar chart per operasi, atau line chart tren terhadap volume data)
4. Susun draf BAB IV (hasil) dan BAB V (pembahasan), termasuk bagian temuan asimetri
   query round-trip yang sudah didiskusikan sebelumnya

Ini akan dibahas di dokumen terpisah setelah data benchmark selesai dikumpulkan.