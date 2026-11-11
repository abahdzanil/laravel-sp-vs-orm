# Fase 2 — Monitoring & Benchmark: Laporan Selesai

**Tanggal**: 2026-09-06
**Acuan**: `phase2-monitoring-benchmark.md`
**Status**: ✅ **SELESAI** — 48/48 file CSV berhasil dihasilkan, database dikembalikan ke kondisi semula.

---

## 1. Apa yang Dikerjakan

Mengikuti panduan `phase2-monitoring-benchmark.md` bagian 1–5 (bagian 6, EXPLAIN ANALYZE, sengaja di-skip dulu sesuai arahan):

1. **`app/Console/Commands/ReseedVolume.php`** (App ORM) — truncate + reseed `products`/`employees` ke volume tertentu, deterministik lewat `fake()->seed(...)`.
2. **`app/Console/Commands/BenchmarkCrud.php`** (App ORM) — timing `create`/`read`/`update`/`delete` langsung lewat Eloquent (`Product::create()`, `->paginate()`, dst), 30 repetisi per operasi, export ke CSV.
3. **`app/Console/Commands/BenchmarkCrud.php`** (App SP) — timing `create`/`read`/`update`/`delete` langsung lewat `DB::statement('CALL sp_xxx(...)')`, 30 repetisi per operasi, export ke CSV.
4. Folder `storage/app/benchmark/` dibuat di kedua project, `.gitignore` ditambahkan baris `/storage/app/benchmark/*.csv`.

Kedua command sudah diverifikasi ter-auto-discover oleh Artisan (`php artisan list` menampilkan `reseed:volume` dan `benchmark:crud` di kedua project) sebelum dieksekusi.

---

## 2. Eksekusi Runbook

Dijalankan berurutan, satu level volume tuntas dulu sebelum lanjut ke level berikutnya, persis seperti runbook di panduan:

| Level | Reseed ORM | Dump data-only | Benchmark ORM | Truncate+Import SP | Benchmark SP |
|---|---|---|---|---|---|
| 5.000 | ✅ 5000 products, 5000 employees | ✅ `seed-data-5000.sql` | ✅ | ✅ 5000/5000 baris masuk | ✅ |
| 15.000 | ✅ 15000/15000 | ✅ `seed-data-15000.sql` | ✅ | ✅ 15000/15000 baris masuk | ✅ |
| 25.000 | ✅ 25000/25000 | ✅ `seed-data-25000.sql` | ✅ | ✅ 25000/25000 baris masuk | ✅ |

Ketiga file dump (`seed-data-5000.sql`, `seed-data-15000.sql`, `seed-data-25000.sql`) disimpan di root repo, sejajar `laravel-orm/`/`laravel-sp/`, sesuai instruksi panduan — bisa dipakai ulang untuk re-run tanpa perlu reseed dari Eloquent lagi.

---

## 3. Output: 48 File CSV

Semua file dikumpulkan di **`result/`** (root repo):

```
result/
├── orm-product-{create,read,update,delete}-{5000,15000,25000}.csv   (12 file)
├── orm-employee-{create,read,update,delete}-{5000,15000,25000}.csv  (12 file)
├── sp-product-{create,read,update,delete}-{5000,15000,25000}.csv    (12 file)
└── sp-employee-{create,read,update,delete}-{5000,15000,25000}.csv   (12 file)
```

Total: **48 file**, masing-masing 30 baris (`run_number,execution_time_ms`) — persis sesuai target di panduan (2 app × 2 entitas × 4 operasi × 3 level).

---

## 4. Restore Database ke Kondisi Semula

Setelah level 25.000 selesai dan seluruh CSV terkumpul, kedua database di-truncate lalu diimpor ulang dari `seed-data.sql` asli (1000 baris, bukan file volume 5000/15000/25000), sesuai keputusan yang disepakati sebelum eksekusi dimulai.

| Cek | `db_orm_bench` | `db_sp_bench` | Status |
|---|---|---|---|
| Jumlah `products` | 1000 | 1000 | ✅ sama |
| Jumlah `employees` | 1000 | 1000 | ✅ sama |
| Jumlah `users` | 1 | 1 | ✅ sama |
| `CHECKSUM TABLE products` | `2658939626` | `2658939626` | ✅ **identik**, cocok dengan nilai di `binding-query-report.md` |
| `CHECKSUM TABLE employees` | `1400780273` | `1400780273` | ✅ **identik**, cocok dengan nilai di `binding-query-report.md` |
| `AUTO_INCREMENT products`/`employees` | 1001 / 1001 | 1001 / 1001 | ✅ sama |
| Tipe kolom `price`/`stock` | `decimal(12,2)` / `int(11)` | `decimal(12,2)` / `int(11)` | ✅ masih benar, tidak ter-revert |

Database sekarang berada tepat di kondisi yang sama seperti sebelum Fase 2 dimulai — aman dipakai lagi untuk kebutuhan lain.

---

## 5. Sanity-Check Data (bukan analisis statistik final — itu Fase 3)

Ringkasan cepat (mean, median, std dev dalam milidetik) untuk melihat pola kasar sebelum masuk ke uji statistik formal di Fase 3:

### Volume 5.000

| Entitas | Operasi | ORM mean | ORM median | ORM sd | SP mean | SP median | SP sd |
|---|---|---|---|---|---|---|---|
| product | create | 11.510 | 3.132 | 32.076 | 1.321 | 1.258 | 0.180 |
| product | read | 1.623 | 1.326 | 1.044 | 0.265 | 0.252 | 0.046 |
| product | update | 3.709 | 3.040 | 3.354 | 5.435 | 1.643 | 8.619 |
| product | delete | 3.210 | 3.889 | 1.875 | 2.420 | 1.501 | 2.559 |
| employee | create | 4.930 | 4.259 | 1.835 | 1.393 | 1.270 | 0.253 |
| employee | read | 1.712 | 1.321 | 1.917 | 0.291 | 0.289 | 0.043 |
| employee | update | 4.602 | 4.321 | 0.916 | 1.455 | 1.491 | 0.212 |
| employee | delete | 5.994 | 1.631 | 12.642 | 1.207 | 1.197 | 0.080 |

### Volume 15.000

| Entitas | Operasi | ORM mean | ORM median | ORM sd | SP mean | SP median | SP sd |
|---|---|---|---|---|---|---|---|
| product | create | 1.804 | 1.757 | 0.319 | 2.097 | 1.822 | 0.573 |
| product | read | 3.314 | 2.700 | 3.097 | 0.258 | 0.250 | 0.030 |
| product | update | 1.763 | 1.707 | 0.230 | 1.645 | 1.561 | 0.182 |
| product | delete | 1.484 | 1.244 | 0.508 | 1.426 | 1.316 | 0.315 |
| employee | create | 1.584 | 1.369 | 0.726 | 1.254 | 1.227 | 0.124 |
| employee | read | 3.117 | 2.666 | 0.962 | 0.281 | 0.259 | 0.045 |
| employee | update | 1.488 | 1.387 | 0.202 | 1.368 | 1.224 | 0.448 |
| employee | delete | 1.293 | 1.241 | 0.181 | 1.266 | 1.204 | 0.167 |

### Volume 25.000

| Entitas | Operasi | ORM mean | ORM median | ORM sd | SP mean | SP median | SP sd |
|---|---|---|---|---|---|---|---|
| product | create | 14.929 | 5.098 | 49.580 | 1.597 | 1.519 | 0.225 |
| product | read | 5.369 | 4.153 | 6.256 | 0.274 | 0.250 | 0.053 |
| product | update | 3.004 | 1.879 | 2.234 | 1.545 | 1.521 | 0.248 |
| product | delete | 2.522 | 1.824 | 1.247 | 1.334 | 1.251 | 0.177 |
| employee | create | 3.198 | 3.170 | 1.617 | 1.294 | 1.261 | 0.161 |
| employee | read | 5.364 | 4.066 | 6.379 | 0.269 | 0.255 | 0.048 |
| employee | update | 6.956 | 2.605 | 11.858 | 6.532 | 1.238 | 13.486 |
| employee | delete | 1.702 | 1.711 | 0.238 | 2.262 | 1.674 | 1.545 |

### Observasi awal (perlu dikonfirmasi lewat uji statistik formal di Fase 3, bukan kesimpulan final)

1. **`read` paling konsisten unggul jelas untuk SP** di semua level, dan skalanya makin lebar seiring volume naik: ORM `read` naik dari ~1,6ms (5k) → ~3,3ms (15k) → ~5,4ms (25k), sementara SP tetap datar di ~0,26-0,29ms. Pola ini sejalan dengan dugaan sebelumnya: `Product::paginate()` menjalankan `SELECT COUNT(*)` tambahan yang scaling dengan ukuran tabel, sedangkan `sp_product_list` tidak menghitung total sama sekali.
2. **`create`/`update`/`delete` jauh lebih *mixed*** — tidak ada pemenang konsisten seperti pada benchmark HTTP sebelumnya (`bernchamrk-result.md`). Kadang ORM menang (mis. `product_create` di volume 15.000), kadang SP menang telak (mis. `product_create` di volume 5.000 dan 25.000).
3. **Rata-rata (mean) sering menyesatkan di sini** — beberapa metrik punya std dev jauh lebih besar dari median (mis. `product_create` ORM volume 25.000: mean 14,9ms tapi median cuma 5,1ms, sd 49,6ms), tandanya ada outlier ekstrem di beberapa run (kemungkinan cold-start PHP/OPcache per proses `artisan`, GC pause, atau noise sistem lain), bukan distribusi normal. **Median kemungkinan ukuran tendensi sentral yang lebih representatif** dibanding mean untuk data ini — ini alasan kuat kenapa Fase 3 perlu cek normalitas dulu sebelum pilih uji signifikansi (t-test butuh data mendekati normal; kalau tidak, Mann-Whitney U lebih tepat, seperti sudah disebut di panduan bagian 7).

---

## 6. Berkas Terkait

- Command: `laravel-orm/app/Console/Commands/ReseedVolume.php`, `laravel-orm/app/Console/Commands/BenchmarkCrud.php`, `laravel-sp/app/Console/Commands/BenchmarkCrud.php`
- Data dump volume: `seed-data-5000.sql`, `seed-data-15000.sql`, `seed-data-25000.sql` (root repo)
- **48 CSV hasil benchmark**: `result/*.csv`

## 7. Langkah Selanjutnya

Sesuai panduan bagian 7 (Fase 3 — Journaling): hitung statistik deskriptif lengkap, uji normalitas lalu pilih uji signifikansi yang sesuai (t-test atau Mann-Whitney U), visualisasi (bar/line chart per operasi terhadap volume), dan susun draf BAB IV/V. Data mentah 30 sampel per kombinasi sudah tersedia utuh di `result/*.csv` — siap dipakai kapan pun mau lanjut ke tahap itu.
