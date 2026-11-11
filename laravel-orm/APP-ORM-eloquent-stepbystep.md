# App ORM — Panduan Step by Step (Eloquent)

Aplikasi ini adalah **versi A** dari eksperimen: seluruh akses data (auth, CRUD product,
CRUD employee) memakai **Eloquent ORM** murni. Tujuannya jadi baseline pembanding untuk
App SP (versi B, stored procedure).

> Prinsip utama: kedua aplikasi (ORM & SP) harus **identik** di semua hal yang bukan
> bagian dari variabel yang diuji — routing, tampilan, validasi, skema tabel, jumlah &
> isi data seed. Yang boleh beda cuma layer akses datanya.

---

## 0. Prasyarat

PHP >= 8.2, Composer, XAMPP (Apache + MySQL), Laravel 11.x, Bootstrap 5 (CDN).

---

## 1. Inisialisasi Project

```bash
composer create-project laravel/laravel app-orm
cd app-orm
```

```sql
CREATE DATABASE db_orm_bench CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`.env`:

```env
DB_DATABASE=db_orm_bench
DB_USERNAME=root
DB_PASSWORD=
```

---

## 2. Migration — products & employees

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

Tabel `users` pakai default bawaan Laravel — tidak perlu diubah.

```bash
php artisan migrate
```

---

## 3. Model Eloquent

```bash
php artisan make:model Product
php artisan make:model Employee
```

`app/Models/Product.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'sku', 'category', 'price', 'stock'];
}
```

`app/Models/Employee.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = ['name', 'email', 'position', 'department', 'salary', 'hire_date'];
}
```

---

## 4. Factory & Seeder (data dummy — jumlah & isi harus sama dengan App SP)

```bash
php artisan make:factory ProductFactory --model=Product
php artisan make:factory EmployeeFactory --model=Employee
php artisan make:seeder ProductSeeder
php artisan make:seeder EmployeeSeeder
```

`database/factories/ProductFactory.php`

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'category' => fake()->randomElement(['Elektronik', 'Alat Tulis', 'Furnitur', 'Pakaian']),
            'price' => fake()->numberBetween(10000, 5000000),
            'stock' => fake()->numberBetween(0, 500),
        ];
    }
}
```

`database/factories/EmployeeFactory.php`

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'position' => fake()->randomElement(['Staff', 'Supervisor', 'Manager']),
            'department' => fake()->randomElement(['IT', 'Finance', 'HR', 'Operasional']),
            'salary' => fake()->numberBetween(4000000, 25000000),
            'hire_date' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
        ];
    }
}
```

`database/seeders/ProductSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        fake()->seed(1234); // fixed seed, biar hasilnya konsisten
        Product::factory()->count(1000)->create(); // ubah jumlah sesuai skenario benchmark
    }
}
```

`database/seeders/EmployeeSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        fake()->seed(5678); // fixed seed, beda dari ProductSeeder tapi tetap konsisten
        Employee::factory()->count(1000)->create();
    }
}
```

`database/seeders/DatabaseSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProductSeeder::class,
            EmployeeSeeder::class,
        ]);
    }
}
```

```bash
php artisan db:seed
mysqldump -u root db_orm_bench products employees > seed-data.sql
```

> `seed-data.sql` ini nanti diimport mentah-mentah ke database App SP supaya datanya
> identik — bukan digenerate ulang dengan Faker yang beda seed.

---

## 5. Auth (Register, Login, Logout)

**Jangan pakai Breeze/Jetstream/Fortify.** Bikin manual biar semua query kelihatan
lewat Eloquent.

```bash
php artisan make:controller AuthController
```

`app/Http/Controllers/AuthController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

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

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect('/dashboard');
        }

        return back()->withErrors(['email' => 'Email atau password salah.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
```

`routes/web.php` (bagian auth)

```php
use App\Http\Controllers\AuthController;

Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
```

---

## 6. CRUD Product (Eloquent)

```bash
php artisan make:controller ProductController --resource
```

`app/Http/Controllers/ProductController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::orderByDesc('id')->paginate(20);
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
            'sku' => 'required|string|max:50|unique:products,sku',
            'category' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        Product::create($validated);

        return redirect('/products')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'sku' => 'required|string|max:50|unique:products,sku,' . $product->id,
            'category' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        $product->update($validated);

        return redirect('/products')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect('/products')->with('success', 'Produk berhasil dihapus.');
    }
}
```

---

## 7. CRUD Employee (Eloquent)

```bash
php artisan make:controller EmployeeController --resource
```

`app/Http/Controllers/EmployeeController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::orderByDesc('id')->paginate(20);
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
            'email' => 'required|email|max:150|unique:employees,email',
            'position' => 'required|string|max:100',
            'department' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0',
            'hire_date' => 'required|date',
        ]);

        Employee::create($validated);

        return redirect('/employees')->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function edit(Employee $employee)
    {
        return view('employees.edit', compact('employee'));
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:employees,email,' . $employee->id,
            'position' => 'required|string|max:100',
            'department' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0',
            'hire_date' => 'required|date',
        ]);

        $employee->update($validated);

        return redirect('/employees')->with('success', 'Karyawan berhasil diperbarui.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return redirect('/employees')->with('success', 'Karyawan berhasil dihapus.');
    }
}
```

`routes/web.php` (bagian CRUD, taruh di bawah bagian auth)

```php
use App\Http\Controllers\ProductController;
use App\Http\Controllers\EmployeeController;

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('employees', EmployeeController::class)->except(['show']);
});
```

---

## 8. View & UI (Bootstrap 5, minim "AI slop")

`resources/views/layouts/app.blade.php`

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Benchmark App')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <nav class="bg-light border-end vh-100 p-3" style="width: 220px;">
        <div class="fw-bold mb-3">App ORM</div>
        <ul class="nav flex-column gap-1">
            <li class="nav-item"><a class="nav-link" href="/dashboard">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="/products">Produk</a></li>
            <li class="nav-item"><a class="nav-link" href="/employees">Karyawan</a></li>
            <li class="nav-item">
                <form action="/logout" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-link nav-link p-0">Logout</button>
                </form>
            </li>
        </ul>
    </nav>
    <main class="container-fluid p-4">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @yield('content')
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

`resources/views/auth/login.blade.php`

```blade
@extends('layouts.guest')
@section('content')
<div class="col-md-4 offset-md-4 mt-5">
    <h4 class="mb-3">Login</h4>
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="/login">
        @csrf
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
        <a href="/register" class="btn btn-outline-secondary">Register</a>
    </form>
</div>
@endsection
```

`resources/views/auth/register.blade.php`

```blade
@extends('layouts.guest')
@section('content')
<div class="col-md-4 offset-md-4 mt-5">
    <h4 class="mb-3">Register</h4>
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="/register">
        @csrf
        <div class="mb-3">
            <label class="form-label">Nama</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Register</button>
        <a href="/login" class="btn btn-outline-secondary">Login</a>
    </form>
</div>
@endsection
```

> Buat `resources/views/layouts/guest.blade.php` — sama seperti `layouts/app.blade.php`
> tapi tanpa sidebar (cukup `@yield('content')` di dalam `container`).

`resources/views/products/index.blade.php`

```blade
@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Produk</h4>
    <a href="/products/create" class="btn btn-primary">Tambah Produk</a>
</div>
<table class="table table-striped">
    <thead>
        <tr><th>Nama</th><th>SKU</th><th>Kategori</th><th>Harga</th><th>Stok</th><th></th></tr>
    </thead>
    <tbody>
        @foreach ($products as $product)
        <tr>
            <td>{{ $product->name }}</td>
            <td>{{ $product->sku }}</td>
            <td>{{ $product->category }}</td>
            <td>{{ number_format($product->price, 0, ',', '.') }}</td>
            <td>{{ $product->stock }}</td>
            <td>
                <a href="/products/{{ $product->id }}/edit" class="btn btn-sm btn-outline-secondary">Edit</a>
                <form action="/products/{{ $product->id }}" method="POST" class="d-inline">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus produk ini?')">Hapus</button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $products->links() }}
@endsection
```

`resources/views/products/create.blade.php` (dan `edit.blade.php` mirip, tinggal isi `value` dari `$product`)

```blade
@extends('layouts.app')
@section('content')
<h4 class="mb-3">Tambah Produk</h4>
<form method="POST" action="/products">
    @csrf
    <div class="mb-3">
        <label class="form-label">Nama</label>
        <input type="text" name="name" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">SKU</label>
        <input type="text" name="sku" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Kategori</label>
        <input type="text" name="category" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Harga</label>
        <input type="number" step="0.01" name="price" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Stok</label>
        <input type="number" name="stock" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="/products" class="btn btn-outline-secondary">Batal</a>
</form>
@endsection
```

> `resources/views/employees/*.blade.php` — struktur identik dengan `products/*`,
> tinggal ganti field sesuai skema employees (`position`, `department`, `salary`,
> `hire_date`).

---

## 9. Checklist Kesetaraan (parity check) sebelum lanjut ke App SP

- [ ] Jumlah & isi baris seed data product/employee identik dengan yang akan dipakai App SP (`seed-data.sql`)
- [ ] Field & validasi form sama persis
- [ ] Pagination size sama (20 per halaman)
- [ ] Struktur URL/routes sama persis
- [ ] View sama persis (idealnya Blade-nya bisa disalin langsung ke App SP)
- [ ] Tidak ada query tambahan tersembunyi (cek pakai Laravel Debugbar)

---

## 10. Langkah selanjutnya

Setelah App ORM ini jalan dan lolos checklist parity, lanjut bangun **App SP**
(lihat `APP-SP-storedprocedure-stepbystep.md`), lalu masuk ke tahap instrumentasi
monitoring (Laravel Debugbar / microtime / k6) yang akan dibahas terpisah.
