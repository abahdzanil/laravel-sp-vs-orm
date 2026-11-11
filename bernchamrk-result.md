# Hasil Benchmarking — App ORM vs App SP

**Tanggal**: 2026-09-06
**Konteks**: Benchmark performa dijalankan setelah parity kedua aplikasi diverifikasi penuh (lihat `PARITY-CHECK-REPORT.md` dan `binding-query-report.md`) dan setelah automated test suite (PHPUnit, 24 test/app, 48 total) hijau di kedua sisi.

---

## 1. Persiapan Sebelum Benchmark

Sebelum benchmarking dijalankan, tiga hal berikut diselesaikan dulu supaya angka performa yang diukur murni mencerminkan perbedaan layer akses data (Eloquent vs Stored Procedure), bukan tercemar oleh bug/ketidaksesuaian lain:

1. **Bug timezone diperbaiki.** Semua stored procedure App SP yang menulis `created_at`/`updated_at` diubah dari `NOW()` (waktu lokal server MySQL) menjadi `UTC_TIMESTAMP()`, supaya konsisten dengan App ORM yang memakai UTC lewat Laravel. Migration stored procedure juga dibuat idempoten (`DROP PROCEDURE IF EXISTS` sebelum tiap `CREATE`) supaya bisa di-*fresh*-migrate berulang kali tanpa intervensi manual.
2. **Automated test ditulis & lolos.** 24 PHPUnit feature test per app (ProductController, EmployeeController, AuthController — CRUD, validasi wajib, validasi duplikat SKU/email, guest-redirect) — total 48 test, semuanya **PASS**. App SP diuji terhadap database MySQL khusus (`db_sp_bench_test`, karena stored procedure tidak bisa jalan di SQLite in-memory bawaan Laravel testing), App ORM tetap pakai SQLite in-memory standar.
3. **Verifikasi data pasca-test**: setelah seluruh proses di atas, `CHECKSUM TABLE products`/`employees` di kedua database tetap identik (produk: `2658939626`, karyawan: `1400780273`), memastikan tidak ada data produksi yang tercemar oleh proses setup test.

---

## 2. Metodologi Benchmark

| Aspek | Detail |
|---|---|
| **Metode** | Sequential timing script (bukan load test/concurrency) — request dikirim satu per satu, ditunggu selesai, baru request berikutnya dikirim |
| **Tool** | Script PHP custom (`scripts/benchmark.php`) memakai `curl` dengan satu handle yang dipakai ulang (in-memory cookie engine, tanpa I/O file) supaya overhead pengukuran seminimal mungkin, waktu diukur pakai `hrtime(true)` (presisi nanodetik) di sekeliling tiap `curl_exec()` |
| **Warm-up** | 10 request pertama tiap operasi dibuang (tidak dihitung), untuk melewati efek "cold start" (bootstrap framework pertama kali, koneksi awal, dsb) |
| **Sampel terukur** | 100 request per operasi, per app |
| **Operasi yang diukur** | `index` (list+pagination), `create`, `edit` (find), `update`, `destroy` — masing-masing untuk **Product** dan **Employee** → total 10 metrik per app, 1.100 request per app (termasuk warm-up) |
| **Server** | `php artisan serve` (built-in PHP dev server, single-threaded) — App ORM di port 8010, App SP di port 8011, keduanya di mesin yang sama (localhost, tanpa network latency) |
| **Database** | XAMPP MariaDB 10.4.32, lokal, database produksi (`db_orm_bench`/`db_sp_bench`, 1000 produk + 1000 karyawan) |
| **Autentikasi** | Login sungguhan lewat `/login` (akun `parity.tester@example.com`, sama di kedua app) sebelum benchmark dimulai, session cookie dipakai ulang untuk semua request berikutnya |
| **Isolasi data** | Semua baris yang dibuat untuk keperluan benchmark (`create`/`update`/`destroy`) diberi tag unik (`BENCH-<runid>-...`) dan dibersihkan otomatis di akhir skrip. Baris untuk operasi `destroy` di-seed langsung lewat SQL (bukan lewat `create` App, supaya tidak mencampur pengukuran) sebelum dihapus lewat endpoint HTTP asli |
| **Verifikasi pasca-run** | `CHECKSUM TABLE` dicek ulang setelah tiap run penuh — checksum `products`/`employees` di kedua database identik dengan sebelum benchmark, artinya tidak ada residu data benchmark yang tertinggal |

---

## 3. Hasil Mentah

### App ORM (Eloquent) — port 8010

| Operasi | Mean (ms) | Median (ms) | Std Dev | Min | Max | P95 |
|---|---|---|---|---|---|---|
| Product Index | 141.851 | 138.766 | 8.456 | 133.625 | 169.184 | 162.233 |
| Employee Index | 140.949 | 136.763 | 8.487 | 133.920 | 161.961 | 159.318 |
| Product Create | 147.839 | 141.788 | 15.369 | 138.476 | 266.069 | 164.234 |
| Employee Create | 148.789 | 145.589 | 8.338 | 140.657 | 180.447 | 167.237 |
| Product Edit (find) | 138.438 | 135.301 | 7.280 | 130.039 | 157.955 | 151.606 |
| Employee Edit (find) | 143.515 | 142.920 | 10.691 | 130.568 | 195.678 | 160.199 |
| Product Update | 147.713 | 143.264 | 15.908 | 139.066 | 284.736 | 165.015 |
| Employee Update | 152.618 | 145.172 | 16.659 | 141.230 | 281.182 | 169.149 |
| Product Destroy | 137.746 | 133.149 | 8.513 | 128.928 | 159.426 | 152.341 |
| Employee Destroy | 139.788 | 135.115 | 9.120 | 129.668 | 163.993 | 154.238 |

### App SP (Stored Procedure) — port 8011

| Operasi | Mean (ms) | Median (ms) | Std Dev | Min | Max | P95 |
|---|---|---|---|---|---|---|
| Product Index | 137.297 | 132.651 | 10.232 | 128.122 | 198.274 | 153.020 |
| Employee Index | 135.620 | 131.049 | 8.705 | 127.637 | 160.429 | 152.976 |
| Product Create | 137.686 | 133.738 | 8.128 | 128.474 | 156.758 | 151.822 |
| Employee Create | 139.639 | 134.341 | 8.667 | 130.483 | 158.621 | 152.479 |
| Product Edit (find) | 129.220 | 125.346 | 7.531 | 121.728 | 148.016 | 143.452 |
| Employee Edit (find) | 129.454 | 124.794 | 8.537 | 121.732 | 150.514 | 146.242 |
| Product Update | 135.015 | 130.500 | 8.918 | 127.718 | 164.727 | 152.027 |
| Employee Update | 138.632 | 133.984 | 8.154 | 130.203 | 157.176 | 153.219 |
| Product Destroy | 126.438 | 122.295 | 8.233 | 119.963 | 148.936 | 144.553 |
| Employee Destroy | 126.715 | 122.868 | 7.628 | 119.475 | 150.171 | 141.524 |

---

## 4. Perbandingan Langsung

| Operasi | ORM Mean (ms) | SP Mean (ms) | Selisih (ms) | Selisih (%) | Lebih Cepat |
|---|---|---|---|---|---|
| Product Index | 141.851 | 137.297 | 4.554 | 3.21% | 🟢 SP |
| Employee Index | 140.949 | 135.620 | 5.329 | 3.78% | 🟢 SP |
| Product Create | 147.839 | 137.686 | 10.153 | 6.87% | 🟢 SP |
| Employee Create | 148.789 | 139.639 | 9.150 | 6.15% | 🟢 SP |
| Product Edit (find) | 138.438 | 129.220 | 9.218 | 6.66% | 🟢 SP |
| Employee Edit (find) | 143.515 | 129.454 | 14.061 | 9.80% | 🟢 SP |
| Product Update | 147.713 | 135.015 | 12.698 | 8.60% | 🟢 SP |
| Employee Update | 152.618 | 138.632 | 13.986 | 9.16% | 🟢 SP |
| Product Destroy | 137.746 | 126.438 | 11.308 | 8.21% | 🟢 SP |
| Employee Destroy | 139.788 | 126.715 | 13.073 | 9.35% | 🟢 SP |
| **RATA-RATA (10 operasi)** | **143.925** | **133.572** | **10.353** | **7.19%** | 🟢 **SP** |

**App SP lebih cepat di seluruh 10 operasi yang diukur, tanpa kecuali** — rentang keunggulan 3.2% (operasi `index`, paling ringan selisihnya) sampai 9.8% (operasi `edit`/`find` karyawan, selisih terbesar). Rata-rata keseluruhan, App SP **~7.2% lebih cepat** dibanding App ORM pada beban kerja dan lingkungan ini.

Pola yang terlihat: selisih paling kecil ada di operasi **index** (yang di App ORM juga sudah dioptimalkan lewat `paginate()`, dan di App SP lewat 2 `CALL` — list+count), sedangkan selisih paling besar ada di **edit/find** dan **update** — kemungkinan karena route-model-binding App ORM (implicit `SELECT` sebelum controller method jalan, didokumentasikan di `PARITY-CHECK-REPORT.md` Bagian 7) menambah satu round-trip database yang tidak dimiliki App SP.

---

## 5. Keterbatasan & Catatan Metodologi (penting untuk konteks jurnal)

Supaya angka di atas tidak disalahartikan, beberapa keterbatasan berikut perlu disebutkan eksplisit:

1. **`php artisan serve` bukan server produksi.** Ini adalah built-in PHP dev server, single-threaded, dengan overhead bootstrap framework yang relatif besar per request (~120-140ms baseline terlihat konsisten di HAMPIR semua operasi kedua app, jauh lebih besar dari overhead query DB itu sendiri yang biasanya sub-milidetik untuk skala data ini). Artinya sebagian besar waktu yang terukur adalah overhead Laravel + PHP dev server, bukan murni waktu query. Perbedaan 7% yang terukur adalah perbedaan **relatif** yang nyata dan konsisten, tapi belum tentu representatif untuk lingkungan produksi (Nginx+PHP-FPM dengan worker pool, OPcache, connection pooling, dsb) di mana overhead bootstrap per-request jauh lebih kecil dan proporsi waktu query terhadap total waktu bisa jadi lebih besar (yang berpotensi memperbesar ATAU memperkecil persentase selisihnya — perlu diuji ulang di lingkungan seperti itu untuk klaim yang lebih kuat).
2. **Sequential, bukan concurrent.** Benchmark ini mengukur latensi satu request pada satu waktu (metodologi yang dipilih secara sadar untuk fokus ke latensi per-operasi), bukan throughput di bawah beban banyak pengguna bersamaan. Karakteristik performa ORM vs SP di bawah concurrency tinggi (mis. lock contention, connection pool, N+1 query yang lebih terasa saat load tinggi) **tidak tercakup** di sini dan bisa jadi berbeda signifikan dari hasil sequential ini.
3. **Skala data tetap (1000 baris).** Semua pengukuran dilakukan pada tabel `products`/`employees` yang masing-masing berisi tepat 1000 baris. Perbedaan performa ORM vs SP bisa saja melebar (SP makin unggul) atau menyempit pada skala data yang jauh lebih besar atau lebih kecil — tidak diuji di sini.
4. **Variabilitas (std dev) cukup besar relatif terhadap selisih rata-rata.** Std dev per operasi (~7-17ms) sebanding besarnya dengan selisih rata-rata antar app (~4-14ms) untuk beberapa operasi (terutama `product_update`/`employee_update` di App ORM yang punya std dev tertinggi, 15-17ms, kemungkinan dipengaruhi oleh overhead unique-check validasi + route-model-binding yang sama-sama query DB). Meskipun begitu, **konsistensi arah** (App SP lebih cepat di 10 dari 10 operasi, tanpa pengecualian) adalah sinyal yang kuat bahwa perbedaan ini sistematis, bukan kebetulan acak — kalau murni noise/acak, peluang 10 dari 10 operasi searah hanya sekitar 1/1024 (~0.1%). Untuk klaim statistik yang lebih ketat di jurnal, disarankan uji signifikansi formal (mis. paired t-test per operasi menggunakan data mentah 100 sampel, bukan cuma mean) — data mentah per-request belum disimpan di run ini, hanya ringkasan statistiknya.
5. **Satu mesin, satu waktu percobaan.** Benchmark dijalankan sekali (100 sampel per operasi) pada satu mesin Windows dengan XAMPP MariaDB, bukan rata-rata dari beberapa sesi terpisah di waktu berbeda. Noise sistem (proses lain yang berjalan bersamaan, thermal throttling, dsb) berpotensi memengaruhi angka absolut meski kecil kemungkinannya memengaruhi arah kesimpulan mengingat konsistensinya di semua operasi.

---

## Ringkasan

- **App SP (Stored Procedure) secara konsisten lebih cepat ~7.2% rata-rata** dibanding **App ORM (Eloquent)** di seluruh 10 operasi CRUD yang diuji (Product & Employee: index, create, edit, update, destroy), pada lingkungan pengujian lokal (`php artisan serve` + MariaDB, sequential request, 100 sampel/operasi setelah warm-up).
- Keunggulan SP paling kecil di operasi **index/list** (~3-4%) dan paling besar di operasi **edit/update** (~9-10%), pola yang konsisten dengan temuan arsitektural di `PARITY-CHECK-REPORT.md` (App ORM menjalankan query implisit tambahan lewat route-model-binding untuk `edit`/`update`/`destroy`).
- Hasil ini adalah **latensi sekuensial di lingkungan development lokal**, bukan throughput produksi di bawah beban banyak pengguna — cocok untuk membandingkan overhead per-operasi antara kedua pendekatan akses data, tapi generalisasinya ke skenario produksi/concurrent perlu pengujian tambahan (lihat Bagian 5).
- Semua data produksi (`products`, `employees`, `users`) diverifikasi tetap identik dan tidak berubah/tercemar sebelum maupun sesudah seluruh proses benchmarking (checksum cocok persis).
