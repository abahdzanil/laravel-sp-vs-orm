# Laporan Perbaikan Parity — App ORM vs App SP

**Tanggal**: 2026-08-13
**Konteks**: Tindak lanjut dari `PARITY-CHECK-REPORT.md`. Laporan ini mendokumentasikan perbaikan 4 poin yang diminta, hasil verifikasi ulang, dan satu temuan baru yang muncul selama proses (di luar 4 poin, dilaporkan tapi **belum diperbaiki** — lihat Catatan Tambahan).

> **Penting**: Route model binding dan struktur query di App ORM (`laravel-orm`) **tidak diubah sama sekali** — asimetri jumlah query yang didokumentasikan di `PARITY-CHECK-REPORT.md` Bagian 7 tetap dipertahankan sebagai temuan riset, sesuai instruksi.

---

## 1. Validasi UNIQUE di App SP — ✅ SELESAI

### Perubahan kode

Karena arsitektur App SP mewajibkan **semua akses data lewat stored procedure** (bukan raw query ke tabel), pengecekan duplikat diimplementasikan sebagai **2 stored procedure baru**, bukan `DB::select` langsung ke tabel — supaya tetap konsisten dengan pola yang sudah ada (`sp_user_find_by_email` di `AuthController@register`) dan tidak melanggar batasan arsitektur yang jadi inti studi ini:

```sql
CREATE PROCEDURE sp_product_sku_exists(IN p_sku VARCHAR(50), IN p_exclude_id BIGINT)
BEGIN
    SELECT COUNT(*) AS total FROM products WHERE sku = p_sku AND id != p_exclude_id;
END

CREATE PROCEDURE sp_employee_email_exists(IN p_email VARCHAR(150), IN p_exclude_id BIGINT)
BEGIN
    SELECT COUNT(*) AS total FROM employees WHERE email = p_email AND id != p_exclude_id;
END
```
(ditambahkan ke `database/migrations/2026_08_09_163651_create_stored_procedures.php`, `p_exclude_id = 0` dipakai saat `store()` karena id belum ada).

**`ProductController@store`/`@update`** dan **`EmployeeController@store`/`@update`** sekarang memanggil SP existence-check ini sebelum `CALL sp_product_create/update` atau `sp_employee_create/update`. Kalau ditemukan duplikat:
```php
return back()->withErrors(['sku' => 'SKU sudah digunakan.'])->withInput();
// atau
return back()->withErrors(['email' => 'Email sudah digunakan.'])->withInput();
```
`->withInput()` ditambahkan supaya perilakunya konsisten dengan App ORM (Laravel otomatis melakukan ini saat `$request->validate()` gagal — form tidak kehilangan isian yang sudah diketik user).

### Verifikasi fungsional (lewat HTTP sungguhan, bukan cuma baca kode)

Kedua app dijalankan via `php artisan serve`, lalu diuji dengan `curl` (termasuk handling CSRF token & session cookie) — jadi ini benar-benar menguji jalur `/products` dan `/employees` seperti pengguna asli, bukan simulasi:

| Skenario | Hasil |
|---|---|
| Submit SKU yang sudah ada (`SKU-000XT`) ke `POST /products` | 302 redirect balik ke form, muncul **"SKU sudah digunakan."**, **0 baris baru** masuk ke tabel `products` |
| Submit email yang sudah ada (`aarmstrong@example.org`) ke `POST /employees` | 302 redirect balik ke form, muncul **"Email sudah digunakan."**, **0 baris baru** masuk ke tabel `employees` |
| Submit SKU/data baru yang valid (`PARITY-TEST-001`) | 302 redirect ke listing, baris berhasil masuk dengan `price`/`stock` tersimpan sebagai numerik (`125000.50`, `42`) — dihapus lagi setelah verifikasi supaya data seed tidak berubah |

✅ Sebelum perbaikan, submit duplikat akan menghasilkan `QueryException` tak tertangkap (HTTP 500). Sekarang perilakunya setara dengan App ORM: redirect balik dengan pesan error yang rapi, form tidak insert apa pun.

### Dampak terhadap jumlah query (relevan untuk Bagian 7 laporan sebelumnya)

`store`/`update` di App SP sekarang memanggil **2 SP** (1 existence-check + 1 create/update), bukan 1 lagi. Ini **sengaja** dan justru membuat jumlah query per operasi **lebih setara** dengan App ORM, yang juga menjalankan 2 query untuk `store`/`update` (1 unique-check SELECT dari validator + 1 INSERT/UPDATE). Catatan asimetri lain (route-model-binding implisit di App ORM untuk `edit`/`update`/`destroy`) **tidak disentuh**, sesuai instruksi.

---

## 2. Validasi Email di `AuthController@register` (App SP) — ✅ SELESAI

**Sebelum**: `'email' => 'required|string|max:150'`
**Sesudah**: `'email' => 'required|email|max:150'`

### Verifikasi fungsional
Submit `email=bukan-email-valid` ke `POST /register` → ditolak dengan pesan **"The email field must be a valid email address."** (pesan default Laravel, sama seperti yang akan muncul di App ORM untuk rule `email` yang sama). Sebelum perbaikan, string ini akan lolos validasi.

---

## 3. Tipe Kolom `products.price` & `products.stock` — ✅ SELESAI

### Langkah yang dilakukan

1. **Migration App ORM diperbaiki** (`create_products_table.php`):
   ```diff
   - $table->string('price', 12, 2);
   - $table->string('stock');
   + $table->decimal('price', 12, 2);
   + $table->integer('stock');
   ```
2. **`php artisan migrate:fresh --seed`** dijalankan di `laravel-orm` — seluruh tabel di `db_orm_bench` dibuat ulang dari migration (skema sekarang benar), lalu diisi ulang oleh `ProductSeeder`/`EmployeeSeeder` (keduanya pakai `fake()->seed(...)` deterministik, jadi 1000 baris tetap reproducible).
3. **Export data-only** dari `db_orm_bench` (bukan full dump) — sengaja pakai `mysqldump --no-create-info` supaya file `seed-data.sql` yang baru **hanya berisi `INSERT`**, tidak ada `CREATE TABLE`. Ini penting: dengan begitu, saat diimpor ke `db_sp_bench`, skema tabel **wajib** datang dari migration App SP sendiri (yang memang sudah benar sejak awal), bukan ikut-ikutan schema dari file dump. File baru ini menimpa `laravel-orm/seed-data.sql` **dan** `laravel-sp/seed-data.sql` (disamakan lagi, MD5 identik) supaya konvensi "kedua app pakai 1 file seed yang sama" dari laporan sebelumnya tetap terjaga.
4. **Tabel `products`/`employees` di `db_sp_bench` di-drop lalu dibuat ulang** lewat migration App SP sendiri (bukan lewat dump) — memastikan skema `decimal(12,2)`/`integer` yang sudah benar di migration App SP itulah yang benar-benar terpakai.
5. Migration stored procedure di App SP juga di-*reinstall* (drop 14 SP lama → install 16 SP: 14 lama + 2 SP baru dari poin 1).
6. Data hasil export diimpor ke `db_sp_bench` via `mysql ... < seed-data.sql`.
7. `AUTO_INCREMENT` counter disamakan lagi ke `1001` di kedua sisi setelah proses uji-coba insert/delete manual saat verifikasi (supaya benar-benar identik sampai ke metadata tabel, bukan cuma isi datanya).

### Hasil verifikasi

| Cek | `db_orm_bench` | `db_sp_bench` | Status |
|---|---|---|---|
| `products.price` | `decimal(12,2)` | `decimal(12,2)` | ✅ identik & benar |
| `products.stock` | `int(11)` | `int(11)` | ✅ identik & benar |
| Jumlah baris `products` | 1000 | 1000 | ✅ sama |
| Jumlah baris `employees` | 1000 | 1000 | ✅ sama |
| `CHECKSUM TABLE products` | `2658939626` | `2658939626` | ✅ **identik** |
| `CHECKSUM TABLE employees` | `1400780273` | `1400780273` | ✅ **identik** |
| `AUTO_INCREMENT products` | 1001 | 1001 | ✅ sama |
| `AUTO_INCREMENT employees` | 1001 | 1001 | ✅ sama |
| Index (`PRIMARY`, `UNIQUE(sku)`, `UNIQUE(email)`) | utuh | utuh | ✅ sama |

Sekarang `ORDER BY price`/`ORDER BY stock` akan terurut numerik yang benar di kedua sisi, bukan leksikografis seperti sebelumnya.

---

## 4. Sinkronisasi Tabel `users` — ✅ SELESAI

Karena `migrate:fresh` di poin 3 ikut menghapus akun manual lama (`Adzanil`) di `db_orm_bench`, tabel `users` di App ORM otomatis kosong lagi setelahnya — jadi supaya kedua app benar-benar punya kondisi awal yang setara, **1 akun test yang sama didaftarkan lewat form `/register` sungguhan di kedua app** (bukan insert manual ke DB):

- Nama: `Parity Tester`
- Email: `parity.tester@example.com`
- Password: `password123`

Registrasi dilakukan via `curl` yang mengambil CSRF token dari halaman `/register` masing-masing app lalu submit form — jadi benar-benar lewat jalur `AuthController@register` di kedua sisi, termasuk `Hash::make()` dan (untuk App SP) `CALL sp_user_create`.

| Kolom | `db_orm_bench.users` | `db_sp_bench.users` |
|---|---|---|
| `id` | 1 | 1 |
| `name` | Parity Tester | Parity Tester |
| `email` | parity.tester@example.com | parity.tester@example.com |
| jumlah baris | 1 | 1 |

✅ Nama & email identik di kedua sisi, keduanya bisa dipakai untuk login dan uji dashboard end-to-end.

**Catatan**: `CHECKSUM TABLE users` **tidak** identik antara kedua database (`1758065221` vs `3319053809`) — ini **wajar dan diharapkan**, bukan bug: kolom `password` berisi hash bcrypt yang memang menyertakan salt acak setiap kali `Hash::make()` dipanggil, jadi dua kali registrasi terpisah (meski passwordnya sama persis) akan selalu menghasilkan hash string yang berbeda. `created_at`/`updated_at` juga berbeda beberapa detik karena kedua registrasi dieksekusi sebagai dua HTTP request terpisah. Yang penting — dan sudah diverifikasi — `name` dan `email` (satu-satunya kolom yang relevan untuk uji kesetaraan) identik.

---

## Ringkasan Verifikasi Ulang

| Poin | Status |
|---|---|
| 1. Unique validation SKU (Product) & Email (Employee) di App SP | ✅ Diperbaiki & diuji fungsional lewat HTTP — duplikat ditolak dengan pesan rapi, 0 baris ter-insert |
| 2. Validasi format email di `AuthController@register` App SP | ✅ Diperbaiki & diuji — email tidak valid ditolak dengan pesan default Laravel |
| 3. Tipe kolom `price`/`stock` jadi `decimal(12,2)`/`int` di kedua DB | ✅ Diperbaiki di kedua sisi, checksum `products`+`employees` **identik** setelah re-seed |
| 4. Tabel `users` disinkronkan (1 akun sama di kedua app) | ✅ Selesai, nama & email identik, terdaftar lewat form `/register` sungguhan |

**Status akhir: 🟢 Semua 4 poin selesai dan terverifikasi.** Checksum `products`/`employees` identik sempurna antar database, seluruh 16 stored procedure (14 asli + 2 baru) terpasang, dan tidak ada regresi pada alur create/edit/delete normal (sudah diuji end-to-end lewat HTTP di kedua app).

---

## Catatan Tambahan (temuan baru, di luar 4 poin — belum diperbaiki)

Saat memverifikasi tabel `users` di atas, ditemukan bahwa **`created_at`/`updated_at` yang ditulis lewat stored procedure (`NOW()`) memakai timezone lokal server MySQL**, sedangkan yang ditulis lewat Eloquent/Carbon di App ORM memakai UTC (`config('app.timezone') = 'UTC'` di kedua `config/app.php`, identik). Contoh nyata dari akun test yang baru didaftarkan:

- `db_orm_bench.users.created_at` = `2026-08-13 14:54:51` (UTC — benar, sesuai config)
- `db_sp_bench.users.created_at` = `2026-08-13 21:54:58` (selisih ~7 jam — waktu lokal server MySQL, bukan UTC)

Dikonfirmasi lewat `SELECT NOW(), UTC_TIMESTAMP()` di MySQL: `NOW()` mengembalikan waktu lokal sistem (`@@time_zone = SYSTEM`), sedangkan `UTC_TIMESTAMP()` mengembalikan waktu UTC yang sesuai dengan apa yang ditulis App ORM. Karena semua stored procedure `*_create`/`*_update` di App SP memakai `NOW()`, **setiap baris yang dibuat/diupdate lewat App SP** (products, employees, users) kemungkinan besar punya `created_at`/`updated_at` yang bergeser ~7 jam dibanding kalau baris yang sama dibuat lewat App ORM — meskipun kolomnya sendiri sudah bertipe identik (`timestamp`).

**Ini di luar 4 poin yang diminta, jadi belum saya perbaiki** — hanya dilaporkan. Kalau mau disamakan, opsinya: ganti `NOW()` menjadi `UTC_TIMESTAMP()` di semua stored procedure App SP, atau set `SET time_zone = '+00:00'` di koneksi MySQL Laravel. Beri tahu saya kalau ingin ini diperbaiki juga.
