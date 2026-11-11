# App SP — Panduan Step by Step (Stored Procedure)

Aplikasi ini adalah **versi B** dari eksperimen: seluruh akses data (auth, CRUD
product, CRUD employee) memakai **stored procedure MySQL**, dipanggil lewat
`DB::select()` / `DB::statement()` — **bukan** Eloquent.

> Wajib baca `APP-ORM-eloquent-stepbystep.md` dulu. Routing, tampilan, skema tabel,
> data seed, dan validasi harus identik dengan App ORM. Yang beda cuma layer akses
> datanya.

---

## 0. Prasyarat

Sama seperti App ORM: PHP >= 8.2, Composer, XAMPP, Laravel 11.x, Bootstrap 5 (CDN).

---

## 1. Inisialisasi Project

```bash
composer create-project laravel/laravel app-sp
cd app-sp
```

```sql
CREATE DATABASE db_sp_bench CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`.env`:

```env
DB_DATABASE=db_sp_bench
DB_USERNAME=root
DB_PASSWORD=
```

---

## 2. Migration — products & employees (struktur tabel, identik dengan App ORM)

```bash
php artisan make:migration create_products_table
php artisan make:migration create_employees_table
```

`database/migrations/xxxx_create_products_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('sku', 50)->unique();
            $table->string('category', 100);
            $table->decimal('price', 12, 2);
            $table->integer('stock');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

`database/migrations/xxxx_create_employees_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 150)->unique();
            $table->string('position', 100);
            $table->string('department', 100);
            $table->decimal('salary', 12, 2);
            $table->date('hire_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
```

```bash
php artisan migrate
```

Import data seed yang sama persis dengan App ORM:

```bash
mysql -u root db_sp_bench < seed-data.sql
```

---

## 3. Stored Procedures

```bash
php artisan make:migration create_stored_procedures
```

`database/migrations/xxxx_create_stored_procedures.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
     
        DB::unprepared("
            CREATE PROCEDURE sp_user_create(
                IN p_name VARCHAR(150), IN p_email VARCHAR(150), IN p_password VARCHAR(255)
            )
            BEGIN
                INSERT INTO users (name, email, password, created_at, updated_at)
                VALUES (p_name, p_email, p_password, NOW(), NOW());
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE sp_user_find_by_email(IN p_email VARCHAR(150))
            BEGIN
                SELECT * FROM users WHERE email = p_email LIMIT 1;
            END
        ");

        // ===== PRODUCTS =====
        DB::unprepared("
            CREATE PROCEDURE sp_product_list(IN p_limit INT, IN p_offset INT)
            BEGIN
                SELECT * FROM products ORDER BY id DESC LIMIT p_limit OFFSET p_offset;
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE sp_product_count()
            BEGIN
                SELECT COUNT(*) AS total FROM products;
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE sp_product_find(IN p_id BIGINT)
            BEGIN
                SELECT * FROM products WHERE id = p_id;
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE sp_product_create(
                IN p_name VARCHAR(150), IN p_sku VARCHAR(50), IN p_category VARCHAR(100),
                IN p_price DECIMAL(12,2), IN p_stock INT
            )
            BEGIN
                INSERT INTO products (name, sku, category, price, stock, created_at, updated_at)
                VALUES (p_name, p_sku, p_category, p_price, p_stock, NOW(), NOW());
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE sp_product_update(
                IN p_id BIGINT, IN p_name VARCHAR(150), IN p_sku VARCHAR(50),
                IN p_category VARCHAR(100), IN p_price DECIMAL(12,2), IN p_stock INT
            )
            BEGIN
                UPDATE products
                SET name = p_name, sku = p_sku, category = p_category,
                    price = p_price, stock = p_stock, updated_at = NOW()
                WHERE id = p_id;
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE sp_product_delete(IN p_id BIGINT)
            BEGIN
                DELETE FROM products WHERE id = p_id;
            END
        ");

        // ===== EMPLOYEES =====
        DB::unprepared("
            CREATE PROCEDURE sp_employee_list(IN p_limit INT, IN p_offset INT)
            BEGIN
                SELECT * FROM employees ORDER BY id DESC LIMIT p_limit OFFSET p_offset;
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE sp_employee_count()
            BEGIN
                SELECT COUNT(*) AS total FROM employees;
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE sp_employee_find(IN p_id BIGINT)
            BEGIN
                SELECT * FROM employees WHERE id = p_id;
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE sp_employee_create(
                IN p_name VARCHAR(150), IN p_email VARCHAR(150), IN p_position VARCHAR(100),
                IN p_department VARCHAR(100), IN p_salary DECIMAL(12,2), IN p_hire_date DATE
            )
            BEGIN
                INSERT INTO employees (name, email, position, department, salary, hire_date, created_at, updated_at)
                VALUES (p_name, p_email, p_position, p_department, p_salary, p_hire_date, NOW(), NOW());
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE sp_employee_update(
                IN p_id BIGINT, IN p_name VARCHAR(150), IN p_email VARCHAR(150),
                IN p_position VARCHAR(100), IN p_department VARCHAR(100),
                IN p_salary DECIMAL(12,2), IN p_hire_date DATE
            )
            BEGIN
                UPDATE employees
                SET name = p_name, email = p_email, position = p_position,
                    department = p_department, salary = p_salary, hire_date = p_hire_date,
                    updated_at = NOW()
                WHERE id = p_id;
            END
        ");

        DB::unprepared("
            CREATE PROCEDURE sp_employee_delete(IN p_id BIGINT)
            BEGIN
                DELETE FROM employees WHERE id = p_id;
            END
        ");
    }

    public function down(): void
    {
        $procedures = [
            'sp_user_create', 'sp_user_find_by_email',
            'sp_product_list', 'sp_product_count', 'sp_product_find',
            'sp_product_create', 'sp_product_update', 'sp_product_delete',
            'sp_employee_list', 'sp_employee_count', 'sp_employee_find',
            'sp_employee_create', 'sp_employee_update', 'sp_employee_delete',
        ];

        foreach ($procedures as $proc) {
            DB::unprepared("DROP PROCEDURE IF EXISTS {$proc}");
        }
    }
};
```

> Verifikasi password (`Hash::check`) tetap dilakukan di PHP, bukan di SQL —
> `sp_user_find_by_email` cuma tugas ambil baris user, bukan verifikasi password.

```bash
php artisan migrate
```

Cek SP sudah masuk:

```sql
SHOW PROCEDURE STATUS WHERE Db = 'db_sp_bench';
```

---

## 4. Auth (Register, Login, Logout) — tanpa Eloquent Auth bawaan

```bash
php artisan make:controller AuthController
php artisan make:middleware EnsureAuthenticatedSp
```

`app/Http/Controllers/AuthController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'password' => 'required|string|min:8',
        ]);

        $existing = DB::select('CALL sp_user_find_by_email(?)', [$validated['email']]);
        if (count($existing) > 0) {
            return back()->withErrors(['email' => 'Email sudah terdaftar.']);
        }

        DB::statement('CALL sp_user_create(?, ?, ?)', [
            $validated['name'],
            $validated['email'],
            Hash::make($validated['password']),
        ]);

        $user = DB::select('CALL sp_user_find_by_email(?)', [$validated['email']])[0];

        session(['sp_user_id' => $user->id, 'sp_user_name' => $user->name]);

        return redirect('/dashboard');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $rows = DB::select('CALL sp_user_find_by_email(?)', [$credentials['email']]);

        if (count($rows) > 0 && Hash::check($credentials['password'], $rows[0]->password)) {
            $request->session()->regenerate();
            session(['sp_user_id' => $rows[0]->id, 'sp_user_name' => $rows[0]->name]);
            return redirect('/dashboard');
        }

        return back()->withErrors(['email' => 'Email atau password salah.']);
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['sp_user_id', 'sp_user_name']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
```

`app/Http/Middleware/EnsureAuthenticatedSp.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticatedSp
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('sp_user_id')) {
            return redirect('/login');
        }

        return $next($request);
    }
}
```

Daftarkan middleware di `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'auth.sp' => \App\Http\Middleware\EnsureAuthenticatedSp::class,
    ]);
})
```

---

## 5. CRUD Product (lewat Stored Procedure)

```bash
php artisan make:controller ProductController
```

`app/Http/Controllers/ProductController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $perPage = 20;
        $currentPage = (int) $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        $rows = DB::select('CALL sp_product_list(?, ?)', [$perPage, $offset]);
        $total = DB::select('CALL sp_product_count()')[0]->total;

        $products = new LengthAwarePaginator($rows, $total, $perPage, $currentPage, [
            'path' => $request->url(),
        ]);

        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'sku' => 'required|string|max:50',
            'category' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        DB::statement('CALL sp_product_create(?, ?, ?, ?, ?)', [
            $validated['name'], $validated['sku'], $validated['category'],
            $validated['price'], $validated['stock'],
        ]);

        return redirect('/products')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $rows = DB::select('CALL sp_product_find(?)', [$id]);
        abort_if(count($rows) === 0, 404);
        $product = $rows[0];

        return view('products.edit', compact('product'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'sku' => 'required|string|max:50',
            'category' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        DB::statement('CALL sp_product_update(?, ?, ?, ?, ?, ?)', [
            $id, $validated['name'], $validated['sku'], $validated['category'],
            $validated['price'], $validated['stock'],
        ]);

        return redirect('/products')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy($id)
    {
        DB::statement('CALL sp_product_delete(?)', [$id]);
        return redirect('/products')->with('success', 'Produk berhasil dihapus.');
    }
}
```

---

## 6. CRUD Employee (lewat Stored Procedure)

```bash
php artisan make:controller EmployeeController
```

`app/Http/Controllers/EmployeeController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = 20;
        $currentPage = (int) $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        $rows = DB::select('CALL sp_employee_list(?, ?)', [$perPage, $offset]);
        $total = DB::select('CALL sp_employee_count()')[0]->total;

        $employees = new LengthAwarePaginator($rows, $total, $perPage, $currentPage, [
            'path' => $request->url(),
        ]);

        return view('employees.index', compact('employees'));
    }

    public function create()
    {
        return view('employees.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'position' => 'required|string|max:100',
            'department' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0',
            'hire_date' => 'required|date',
        ]);

        DB::statement('CALL sp_employee_create(?, ?, ?, ?, ?, ?)', [
            $validated['name'], $validated['email'], $validated['position'],
            $validated['department'], $validated['salary'], $validated['hire_date'],
        ]);

        return redirect('/employees')->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $rows = DB::select('CALL sp_employee_find(?)', [$id]);
        abort_if(count($rows) === 0, 404);
        $employee = $rows[0];

        return view('employees.edit', compact('employee'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'position' => 'required|string|max:100',
            'department' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0',
            'hire_date' => 'required|date',
        ]);

        DB::statement('CALL sp_employee_update(?, ?, ?, ?, ?, ?, ?)', [
            $id, $validated['name'], $validated['email'], $validated['position'],
            $validated['department'], $validated['salary'], $validated['hire_date'],
        ]);

        return redirect('/employees')->with('success', 'Karyawan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        DB::statement('CALL sp_employee_delete(?)', [$id]);
        return redirect('/employees')->with('success', 'Karyawan berhasil dihapus.');
    }
}
```

---

## 7. Routes

`routes/web.php`

```php
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth.sp')->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/create', [ProductController::class, 'create']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{id}/edit', [ProductController::class, 'edit']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/create', [EmployeeController::class, 'create']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::get('/employees/{id}/edit', [EmployeeController::class, 'edit']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
});
```

---

## 8. View & UI

**Salin langsung** semua file dari `resources/views/` App ORM ke App SP tanpa
diubah — layout, `auth/login.blade.php`, `auth/register.blade.php`,
`products/*.blade.php`, `employees/*.blade.php`. Karena hasil `DB::select()` berupa
array of `stdClass`, akses properti di Blade tetap sama (`$product->name`,
`$product->price`, dst.) — tidak ada bagian Blade yang perlu diubah.

Satu-satunya perbedaan: di sidebar `layouts/app.blade.php`, ganti label
`App ORM` jadi `App SP` supaya gampang dibedakan pas testing manual.

---

## 9. Checklist Kesetaraan (parity check)

- [ ] Data seed identik dengan App ORM (`seed-data.sql` yang sama)
- [ ] Field, validasi, dan pesan error sama persis
- [ ] Pagination size sama (20/halaman) dan tampilannya konsisten
- [ ] Struktur URL/routes sama persis dengan App ORM
- [ ] View/Blade sama persis (file disalin langsung)
- [ ] Tiap operasi CRUD cuma 1 SP call — dicatat sebagai baseline pembanding ke jumlah query Eloquent

---

## 10. Langkah selanjutnya

Setelah App SP jalan dan lolos parity check terhadap App ORM, lanjut ke tahap
instrumentasi monitoring (Laravel Debugbar untuk App ORM, `microtime()` manual +
`EXPLAIN`/`EXPLAIN ANALYZE` untuk membandingkan query plan, lalu load testing pakai
`ab` atau `k6`) — dibahas di dokumen terpisah.
