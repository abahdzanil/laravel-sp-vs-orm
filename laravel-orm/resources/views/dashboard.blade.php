@extends('layouts.app')
@section('content')
<h4 class="mb-3">Dashboard</h4>
<p>Selamat datang, {{ auth()->user()->name }}.</p>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Produk</h6>
                <p class="card-text fs-4">{{ \App\Models\Product::count() }}</p>
                <a href="/products" class="btn btn-sm btn-outline-primary">Lihat Produk</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Karyawan</h6>
                <p class="card-text fs-4">{{ \App\Models\Employee::count() }}</p>
                <a href="/employees" class="btn btn-sm btn-outline-primary">Lihat Karyawan</a>
            </div>
        </div>
    </div>
</div>
@endsection
