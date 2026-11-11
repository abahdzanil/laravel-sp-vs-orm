# Parity Check Report — App ORM vs App SP

**Tanggal**: 2026-08-13
**Lokasi**: `laravel-orm/` (App ORM, Eloquent) vs `laravel-sp/` (App SP, Stored Procedure)
**Tujuan**: Memastikan kedua aplikasi identik di semua hal kecuali layer akses data, sebelum lanjut ke tahap benchmarking.

> Catatan lokasi folder: instruksi menyebut `./app-orm` dan `./app-sp`, tapi folder aktual di root workspace bernama `laravel-orm` dan `laravel-sp`. Semua pengecekan di bawah dilakukan terhadap folder tersebut.

---

## 1. Perbandingan Routes

`php artisan route:list` dijalankan di kedua project (21 routes masing-masing).

| Path | ORM Method | SP Method | Nama Route (ORM) | Nama Route (SP) | Status |
|---|---|---|---|---|---|
| `/register` | GET, POST | GET, POST | `register` | `register` | ✅ sama |
| `/login` | GET, POST | GET, POST | `login` | `login` | ✅ sama |
| `/logout` | POST | POST | `logout` | `logout` | ✅ sama |
| `/dashboard` | GET | GET | `dashboard` | `dashboard` | ✅ sama |
| `/products` | GET, POST | GET, POST | `products.index`/`products.store` | *(tanpa nama)* | ⚠️ beda |
| `/products/create` | GET | GET | `products.create` | *(tanpa nama)* | ⚠️ beda |
| `/products/{product}` vs `/products/{id}` | PUT**+PATCH**, DELETE | **PUT saja**, DELETE | `products.update`/`products.destroy` | *(tanpa nama)* | ⚠️ beda |
| `/products/{..}/edit` | GET | GET | `products.edit` | *(tanpa nama)* | ⚠️ beda |
| `/employees` + sub-path | sama pola dengan products | sama pola dengan products | bernama | tanpa nama | ⚠️ beda |

Semua **path dan HTTP method dasar** (GET/POST/DELETE) untuk `/login`, `/register`, `/products`, `/employees`, dll ada di kedua sisi — **tidak ada route yang hilang di salah satu sisi**.

Perbedaan yang ditemukan (bukan soal path hilang, tapi cara define route):

1. **PATCH hilang di App SP.** App ORM pakai `Route::resource(...)` sehingga endpoint update otomatis menerima `PUT` **dan** `PATCH`. App SP mendefinisikan route manual dengan `Route::put(...)` saja — request `PATCH` ke `/products/{id}` atau `/employees/{id}` akan mendapat `405 Method Not Allowed` di App SP, sedangkan di App ORM tidak.
   - *Dampak saat ini*: rendah — semua form di kedua app memakai `@method('PUT')` (bukan PATCH), jadi tidak ada regresi fungsional yang terlihat dari UI. Tapi ini tetap perbedaan kontrak API yang sebaiknya diketahui sebelum benchmarking (terutama kalau nanti ada test otomatis yang expect kedua app berperilaku identik).
2. **Route tanpa nama di App SP.** Route `products.*` dan `employees.*` di App ORM diberi nama otomatis oleh `Route::resource()`. Di App SP, route yang setara didefinisikan manual tanpa `->name(...)`, jadi tidak bisa dipanggil dengan `route('products.edit', $id)` dsb.
   - *Dampak saat ini*: tidak ada — sudah diverifikasi tidak ada view di App SP yang memakai helper `route()` untuk products/employees (lihat Bagian 2), semua pakai hardcoded path (`/products/{id}`).
3. **Parameter route berbeda nama**: `{product}`/`{employee}` (ORM, route model binding) vs `{id}` (SP, manual). Konsekuensi logis dari App SP tidak memakai Eloquent — bukan masalah, tapi relevan untuk Bagian 7 (lihat query implisit dari route-model-binding).
4. **Middleware group berbeda**: `auth` (guard bawaan Laravel, ORM) vs `auth.sp` (middleware custom `EnsureAuthenticatedSp`, cek `session('sp_user_id')`, SP). Ini memang **seharusnya** berbeda karena App SP tidak bisa pakai guard Eloquent bawaan — perilakunya (redirect ke `/login` jika belum login) sudah diverifikasi setara.

---

## 2. Perbandingan Views

`diff -rq` terhadap seluruh folder `resources/views/` (layouts, auth, products, employees, dashboard, welcome):

**Hasil: dari semua file, hanya 2 file yang berbeda isinya** — sisanya (termasuk seluruh `auth/`, `products/`, `employees/`) **100% identik byte-per-byte**.

### `resources/views/layouts/app.blade.php`
```diff
-        <div class="fw-bold mb-3 text-light">App ORM</div>
+        <div class="fw-bold mb-3 text-light">App SP</div>
```
✅ Sesuai ekspektasi — hanya label sidebar yang beda.

### `resources/views/dashboard.blade.php`
```diff
-<p>Selamat datang, {{ auth()->user()->name }}.</p>
+<p>Selamat datang, {{ session('sp_user_name') }}.</p>
...
-                <p class="card-text fs-4">{{ \App\Models\Product::count() }}</p>
+                <p class="card-text fs-4">{{ $productCount }}</p>
...
-                <p class="card-text fs-4">{{ \App\Models\Employee::count() }}</p>
+                <p class="card-text fs-4">{{ $employeeCount }}</p>
```
✅ Sesuai ekspektasi — perbedaan murni karena sumber datanya beda (Eloquent langsung di view vs data dikirim dari controller yang manggil SP `sp_product_count`/`sp_employee_count`). Tidak ada perbedaan tampilan/struktur HTML.

**Kesimpulan Bagian 2**: Views sudah identik sesuai spesifikasi ("harus identik kecuali label App ORM vs App SP"). Tidak ada temuan yang perlu diperbaiki.

---

## 3. Perbandingan Validasi & Field

### ProductController

| Field | App ORM (`store`/`update`) | App SP (`store`/`update`) | Status |
|---|---|---|---|
| `name` | `required\|string\|max:150` | `required\|string\|max:150` | ✅ sama |
| `sku` | `required\|string\|max:50\|unique:products,sku[,id]` | `required\|string\|max:50` | 🔴 **beda** |
| `category` | `required\|string\|max:100` | `required\|string\|max:100` | ✅ sama |
| `price` | `required\|numeric\|min:0` | `required\|numeric\|min:0` | ✅ sama |
| `stock` | `required\|integer\|min:0` | `required\|integer\|min:0` | ✅ sama |

### EmployeeController

| Field | App ORM (`store`/`update`) | App SP (`store`/`update`) | Status |
|---|---|---|---|
| `name` | `required\|string\|max:150` | `required\|string\|max:150` | ✅ sama |
| `email` | `required\|email\|max:150\|unique:employees,email[,id]` | `required\|email\|max:150` | 🔴 **beda** |
| `position` | `required\|string\|max:100` | `required\|string\|max:100` | ✅ sama |
| `department` | `required\|string\|max:100` | `required\|string\|max:100` | ✅ sama |
| `salary` | `required\|numeric\|min:0` | `required\|numeric\|min:0` | ✅ sama |
| `hire_date` | `required\|date` | `required\|date` | ✅ sama |

### 🔴 Temuan Utama: rule `unique` hilang di App SP

App SP **tidak** memvalidasi keunikan `sku` (products) maupun `email` (employees) di layer Laravel — beda dengan App ORM yang eksplisit melakukan `unique:products,sku` / `unique:employees,email`.

Karena kolom `sku` dan `email` tetap punya **UNIQUE index** di level database (dikonfirmasi di Bagian 4), submit data duplikat akan berakhir seperti ini:

- **App ORM**: validasi Laravel menangkap duplikat lebih dulu → redirect balik dengan pesan error yang rapi.
- **App SP**: validasi lolos → `DB::statement('CALL sp_product_create...')` dieksekusi → MySQL melempar *duplicate entry* error → **`Illuminate\Database\QueryException` tidak tertangkap → HTTP 500** (bukan pesan validasi yang ramah).

Ini adalah **perbedaan perilaku fungsional nyata**, bukan cuma soal layer akses data, dan sebaiknya diperbaiki sebelum benchmarking (idealnya App SP menambahkan pengecekan manual sebelum `CALL`, mirip pola yang sudah dipakai di `AuthController@register`, lihat catatan tambahan di bawah).

### Field yang dipakai di form create/edit

Karena seluruh view `products/` dan `employees/` identik byte-per-byte (Bagian 2), field yang dikirim dari form (`name`, `sku`/`email`, `category`/`position`, `department`, `price`/`salary`, `stock`/`hire_date`) otomatis sama persis di kedua sisi. Field ini juga cocok dengan `$fillable` di `Product`/`Employee` model (App ORM):
```php
// Product: ['name', 'sku', 'category', 'price', 'stock']
// Employee: ['name', 'email', 'position', 'department', 'salary', 'hire_date']
```
✅ Field parity terjaga.

### Catatan tambahan (di luar scope Product/Employee, tapi relevan): `AuthController@register`

Ditemukan saat menelusuri konteks auth (tidak diminta eksplisit, tapi berdampak ke parity keseluruhan aplikasi):

- App ORM: `'email' => 'required|email|max:150|unique:users,email'`
- App SP: `'email' => 'required|string|max:150'` — **rule format `email` hilang**, hanya dicek sebagai string biasa. App SP memang melakukan pengecekan duplikat secara manual (`sp_user_find_by_email` sebelum `sp_user_create`), jadi soal keunikan sudah tertutupi — tapi validasi **format email** (harus mengandung `@`, dsb.) tidak ada di App SP. Artinya App SP akan menerima input seperti `"bukan-email"` sebagai email yang valid, App ORM akan menolaknya.

---

## 4. Perbandingan Skema Database

XAMPP MySQL (MariaDB 10.4.32) aktif dan dicek langsung via `information_schema.columns` + `SHOW INDEX` untuk tabel `products`, `employees`, `users` di `db_orm_bench` vs `db_sp_bench`.

**Hasil: struktur kolom, tipe data, nullable, primary key, dan unique index 100% identik** di kedua database untuk ketiga tabel.

| Tabel | Kolom | Tipe (kedua DB sama) | Index |
|---|---|---|---|
| `products` | id, name, sku, category, price, stock, created_at, updated_at | lihat catatan ⚠️ di bawah | PK(id), UNIQUE(sku) |
| `employees` | id, name, email, position, department, salary, hire_date, created_at, updated_at | decimal(12,2) untuk salary, date untuk hire_date | PK(id), UNIQUE(email) |
| `users` | id, name, email, email_verified_at, password, remember_token, created_at, updated_at | standar Laravel | PK(id), UNIQUE(email) |

### ⚠️ Catatan (bukan masalah parity, tapi masalah kualitas data yang sama-sama diwarisi kedua sisi)

Kolom `products.price` bertipe **`varchar(12)`** dan `products.stock` bertipe **`varchar(255)`** di **kedua** database — seharusnya `decimal(12,2)` dan `integer`.

Investigasi lebih lanjut:
- Migration **App ORM** (`create_products_table.php`) masih punya bug: `$table->string('price', 12, 2)` dan `$table->string('stock')` — argumen ke-3 pada `string()` diabaikan Laravel, hasilnya kolom jadi varchar, bukan decimal.
- Migration **App SP** justru **sudah benar**: `$table->decimal('price', 12, 2)` dan `$table->integer('stock')`.
- Tapi skema live di **kedua** database tetap varchar — karena keduanya di-provision dari file `seed-data.sql` yang sama persis (MD5 identik: `c093483b4ad1da36d9ea6acce0e0f12e`), bukan dari hasil `php artisan migrate`. Dump SQL ini berisi `CREATE TABLE` versi lama (varchar), jadi migration App SP yang sudah diperbaiki **tidak pernah benar-benar dijalankan ulang** terhadap database yang sekarang dipakai.

**Rekomendasi**: Ini tidak menyebabkan ketidakcocokan antara App ORM vs App SP (keduanya identik, sama-sama varchar), tapi tetap bug laten yang sebaiknya diperbaiki sebelum benchmarking — `ORDER BY price` atau perbandingan numerik pada kolom varchar akan terurut secara leksikografis (string), bukan numerik, yang bisa mendistorsi hasil benchmark query yang melibatkan sorting/filtering `price`/`stock`.

---

## 5. Verifikasi Stored Procedure

`SHOW PROCEDURE STATUS WHERE Db = 'db_sp_bench'` menghasilkan **14 stored procedure**, dan **semuanya cocok** dengan yang didefinisikan di migration `2026_08_09_163651_create_stored_procedures.php` (dikonfirmasi silang dengan `information_schema.routines`, count = 14, tidak ada yang hilang atau berlebih):

| Procedure | Status |
|---|---|
| `sp_user_create` | ✅ terinstall |
| `sp_user_find_by_email` | ✅ terinstall |
| `sp_product_list` | ✅ terinstall |
| `sp_product_count` | ✅ terinstall |
| `sp_product_find` | ✅ terinstall |
| `sp_product_create` | ✅ terinstall |
| `sp_product_update` | ✅ terinstall |
| `sp_product_delete` | ✅ terinstall |
| `sp_employee_list` | ✅ terinstall |
| `sp_employee_count` | ✅ terinstall |
| `sp_employee_find` | ✅ terinstall |
| `sp_employee_create` | ✅ terinstall |
| `sp_employee_update` | ✅ terinstall |
| `sp_employee_delete` | ✅ terinstall |

Isi body tiap procedure juga diperiksa: masing-masing hanya berisi **satu statement SQL murni** (`SELECT`/`INSERT`/`UPDATE`/`DELETE`), tidak ada loop, tidak ada pengecekan duplikat internal, tidak ada logic tersembunyi.

---

## 6. Verifikasi Data Seed

| Tabel | `db_orm_bench` count | `db_sp_bench` count | `CHECKSUM TABLE` ORM | `CHECKSUM TABLE` SP | Status |
|---|---|---|---|---|---|
| `products` | 1000 | 1000 | 2013868051 | 2013868051 | ✅ identik |
| `employees` | 1000 | 1000 | 2284782134 | 2284782134 | ✅ identik |
| `users` | **1** | **0** | 1967378838 | 0 | 🔴 **beda** |

`products` dan `employees` **identik sempurna** — jumlah baris maupun checksum data sama persis, jadi data seed benar-benar sama isinya, bukan cuma sama jumlahnya.

### 🔴 Temuan: tabel `users` tidak sinkron

`db_orm_bench.users` punya 1 baris (akun `Adzanil <adzanil@gmail.com>`, dibuat `2026-08-09`), sedangkan `db_sp_bench.users` **kosong**. Ini bukan bug kode — `users` memang tidak termasuk dalam `seed-data.sql` (yang hanya berisi `products`+`employees`), jadi baris ini kemungkinan besar hasil registrasi manual saat testing App ORM.

**Dampak**: bukan masalah kode, tapi berarti App SP saat ini **belum punya akun untuk login** — kalau mau membandingkan alur login/dashboard secara end-to-end di kedua app, perlu registrasi user dulu di App SP (atau insert manual) supaya kondisi awal keduanya setara.

---

## 7. Cek Query Tersembunyi

### App ORM — `ProductController` & `EmployeeController`

- Model `Product` dan `Employee` **tidak mendefinisikan relasi apa pun** (`hasMany`/`belongsTo`/dst tidak ditemukan di seluruh `app/`), jadi tidak ada N+1 atau eager-loading tersembunyi yang mungkin terjadi.
- Tidak ada pemanggilan `->with(...)` (relasi Eloquent) atau `->load(...)` di kedua controller. Satu-satunya `->with(...)` yang muncul saat grep adalah `redirect(...)->with('success', ...)` (flash message session, bukan eager-loading Eloquent).
- `index()` memakai `->paginate(20)` — ini standar Eloquent, secara internal menjalankan **2 query** (1 `SELECT COUNT(*)` + 1 `SELECT ... LIMIT`), bukan tambahan yang tidak wajar.

✅ **Tidak ditemukan query berlebih/eager-loading tersembunyi di App ORM.**

### App SP — `ProductController` & `EmployeeController`

Setiap operasi CRUD memanggil SP sebanyak:

| Operasi | Jumlah CALL | Detail |
|---|---|---|
| `store` (create) | **1** | `CALL sp_product_create(...)` / `sp_employee_create(...)` |
| `edit` (form) | **1** | `CALL sp_product_find(...)` / `sp_employee_find(...)` |
| `update` | **1** | `CALL sp_product_update(...)` / `sp_employee_update(...)` |
| `destroy` | **1** | `CALL sp_product_delete(...)` / `sp_employee_delete(...)` |
| `index` (list) | **2** | `CALL sp_product_list(...)` + `CALL sp_product_count()` |

✅ `store`/`edit`/`update`/`destroy` sudah sesuai ketentuan **"1 operasi = 1 SP call"**.

⚠️ `index()` memanggil **2** SP (list + count) — ini secara teknis lebih dari 1, **tapi ini setara/adil** dibanding App ORM yang juga menjalankan 2 query internal untuk `->paginate(20)` (count + select). Jadi bukan pelanggaran, melainkan padanan yang wajar untuk mendukung pagination di kedua sisi.

### Asimetri jumlah round-trip yang perlu diketahui sebelum benchmarking

Karena App ORM memakai **route model binding** (`Product $product` / `Employee $employee` di signature method), Laravel otomatis menjalankan **1 SELECT implisit** untuk resolve model dari `{product}`/`{employee}` di URL — ini terjadi **sebelum** `edit()`, `update()`, dan `destroy()` dieksekusi. Ditambah `unique` validation yang juga menjalankan 1 SELECT tambahan di `update()`. Sehingga jumlah query per request untuk **update** sebenarnya:

- **App ORM `update`**: 1 (route-model-binding select) + 1 (unique-check select) + 1 (update) = **~3 query**
- **App SP `update`**: **1 CALL saja** (tidak ada fetch-dulu, tidak ada unique-check — lihat Bagian 3)

Dan untuk **destroy**:
- **App ORM `destroy`**: 1 (route-model-binding select) + 1 (delete) = **~2 query**
- **App SP `destroy`**: **1 CALL saja**

Ini **bukan bug** — murni konsekuensi alami dari pendekatan arsitektur masing-masing (Eloquent route-model-binding vs manual `$id`). Tapi ini penting dicatat: kalau benchmarking nanti mengukur "jumlah query per request" atau round-trip ke database sebagai metrik, App SP secara struktural akan selalu terlihat lebih sedikit query untuk `update`/`destroy` karena App ORM melakukan fetch tambahan yang implisit. Sebaiknya metodologi benchmark menyadari hal ini (atau ukur end-to-end response time saja, bukan query count, supaya perbandingannya adil).

---

## Ringkasan

### Status keseluruhan: 🟡 **ADA MASALAH** (minor–moderate, semua bisa diperbaiki cepat sebelum benchmarking)

Fondasi besar sudah solid: **routing paths**, **seluruh views**, **skema tabel**, **stored procedure**, dan **data seed products/employees** semuanya identik antara App ORM dan App SP. Namun ada beberapa celah parity yang sebaiknya ditutup dulu supaya benchmark nanti membandingkan performa "apple-to-apple", bukan sekaligus membandingkan bug/kelalaian implementasi.

### Daftar poin yang perlu diperbaiki

1. 🔴 **[Validasi] Tambahkan rule `unique` di App SP** — `ProductController@store/update` perlu `unique:products,sku` dan `EmployeeController@store/update` perlu `unique:employees,email` (atau minimal pengecekan manual sebelum `CALL ...create/update`, seperti pola yang sudah dipakai di `AuthController@register`). Tanpa ini, submit data duplikat di App SP akan menghasilkan HTTP 500 mentah, bukan pesan validasi yang rapi seperti App ORM.
2. 🔴 **[Validasi] Perbaiki rule email di `AuthController@register` App SP** — ganti `required|string|max:150` menjadi `required|email|max:150` supaya format email tervalidasi sama seperti App ORM.
3. 🟡 **[Skema DB] Perbaiki tipe kolom `products.price`/`products.stock`** — saat ini varchar di kedua database (seharusnya `decimal(12,2)`/`integer`). Sudah identik di kedua sisi jadi tidak mengganggu *parity*, tapi berpotensi mendistorsi hasil benchmark yang melibatkan sorting/agregasi numerik pada kolom ini. Pertimbangkan re-provision database dari migration terbaru (App SP migration-nya sudah benar) + re-seed, bukan dari `seed-data.sql` dump lama.
4. 🟡 **[Seed data] Samakan kondisi tabel `users`** — `db_orm_bench` punya 1 user manual, `db_sp_bench` kosong. Tambahkan/registrasikan user pembanding di App SP (atau hapus user manual di App ORM) supaya kedua app punya kondisi awal yang setara untuk uji login/dashboard.
5. 🟢 **[Routing, opsional]** Tambahkan `->name(...)` pada route products/employees di App SP dan pertimbangkan menambah dukungan `PATCH` (selain `PUT`) supaya kontrak route benar-benar identik dengan `Route::resource()` di App ORM — saat ini tidak berdampak fungsional karena tidak dipakai, tapi baik untuk konsistensi jangka panjang.

Tidak ada poin blocker yang mencegah benchmarking dimulai, tapi poin 1–3 disarankan diperbaiki lebih dulu karena berpotensi memengaruhi *validitas* hasil benchmark (perbedaan perilaku fungsional dan tipe data numerik-vs-string).
