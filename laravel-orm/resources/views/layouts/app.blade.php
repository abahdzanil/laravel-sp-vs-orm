<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Benchmark App')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <nav class="bg-primary border-end vh-100 p-3" style="width: 220px;">
        <div class="fw-bold mb-3 text-light">App ORM</div>
        <ul class="nav flex-column gap-1 ">
            <li class="nav-item"><a class="nav-link text-light" href="/dashboard">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link text-light" href="/products">Produk</a></li>
            <li class="nav-item"><a class="nav-link text-light" href="/employees">Karyawan</a></li>
            <li class="nav-item">
                <form action="/logout" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-link nav-link p-0 text-light">Logout</button>
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
