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
