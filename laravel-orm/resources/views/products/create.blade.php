@extends('layouts.app')
@section('content')
<h4 class="mb-3">Tambah Produk</h4>
@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif
<form method="POST" action="/products">
    @csrf
    <div class="mb-3">
        <label class="form-label">Nama</label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label">SKU</label>
        <input type="text" name="sku" class="form-control" value="{{ old('sku') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Kategori</label>
        <input type="text" name="category" class="form-control" value="{{ old('category') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Harga</label>
        <input type="number" step="0.01" name="price" class="form-control" value="{{ old('price') }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Stok</label>
        <input type="number" name="stock" class="form-control" value="{{ old('stock') }}" required>
    </div>
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="/products" class="btn btn-outline-secondary">Batal</a>
</form>
@endsection
