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

        $skuTaken = DB::select('CALL sp_product_sku_exists(?, ?)', [$validated['sku'], 0])[0]->total;
        if ($skuTaken > 0) {
            return back()->withErrors(['sku' => 'SKU sudah digunakan.'])->withInput();
        }

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

        $skuTaken = DB::select('CALL sp_product_sku_exists(?, ?)', [$validated['sku'], $id])[0]->total;
        if ($skuTaken > 0) {
            return back()->withErrors(['sku' => 'SKU sudah digunakan.'])->withInput();
        }

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
