<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSpUser(): static
    {
        $this->withSession(['sp_user_id' => 1, 'sp_user_name' => 'Test User']);

        return $this;
    }

    private function makeProduct(array $overrides = []): int
    {
        $data = array_merge([
            'name' => 'Produk Test',
            'sku' => 'SKU-TEST-'.uniqid(),
            'category' => 'Elektronik',
            'price' => 10000,
            'stock' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return DB::table('products')->insertGetId($data);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/products');

        $response->assertRedirect('/login');
    }

    public function test_index_displays_products(): void
    {
        $this->makeProduct(['name' => 'Produk A']);

        $response = $this->actingAsSpUser()->get('/products');

        $response->assertStatus(200);
        $response->assertViewHas('products');
    }

    public function test_store_creates_product(): void
    {
        $response = $this->actingAsSpUser()->post('/products', [
            'name' => 'Produk Baru',
            'sku' => 'SKU-NEW-001',
            'category' => 'Elektronik',
            'price' => 15000,
            'stock' => 10,
        ]);

        $response->assertRedirect('/products');
        $this->assertDatabaseHas('products', ['sku' => 'SKU-NEW-001', 'name' => 'Produk Baru']);
    }

    public function test_store_fails_validation_when_fields_missing(): void
    {
        $response = $this->actingAsSpUser()->post('/products', []);

        $response->assertSessionHasErrors(['name', 'sku', 'category', 'price', 'stock']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_store_rejects_duplicate_sku(): void
    {
        $this->makeProduct(['sku' => 'SKU-DUP-001']);

        $response = $this->actingAsSpUser()->post('/products', [
            'name' => 'Produk Duplikat',
            'sku' => 'SKU-DUP-001',
            'category' => 'Elektronik',
            'price' => 5000,
            'stock' => 1,
        ]);

        $response->assertSessionHasErrors(['sku']);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_update_modifies_product(): void
    {
        $id = $this->makeProduct(['name' => 'Produk Lama', 'sku' => 'SKU-OLD-001']);

        $response = $this->actingAsSpUser()->put("/products/{$id}", [
            'name' => 'Produk Sudah Diupdate',
            'sku' => 'SKU-OLD-001',
            'category' => 'Elektronik',
            'price' => 20000,
            'stock' => 3,
        ]);

        $response->assertRedirect('/products');
        $this->assertDatabaseHas('products', ['id' => $id, 'name' => 'Produk Sudah Diupdate', 'stock' => 3]);
    }

    public function test_update_rejects_sku_used_by_another_product(): void
    {
        $this->makeProduct(['sku' => 'SKU-A-001']);
        $idB = $this->makeProduct(['sku' => 'SKU-B-001']);

        $response = $this->actingAsSpUser()->put("/products/{$idB}", [
            'name' => 'Produk B Diubah',
            'sku' => 'SKU-A-001',
            'category' => 'Elektronik',
            'price' => 5000,
            'stock' => 2,
        ]);

        $response->assertSessionHasErrors(['sku']);
        $this->assertDatabaseHas('products', ['id' => $idB, 'sku' => 'SKU-B-001']);
    }

    public function test_update_allows_keeping_same_sku(): void
    {
        $id = $this->makeProduct(['sku' => 'SKU-SAME-001']);

        $response = $this->actingAsSpUser()->put("/products/{$id}", [
            'name' => 'Nama Baru',
            'sku' => 'SKU-SAME-001',
            'category' => 'Elektronik',
            'price' => 9999,
            'stock' => 7,
        ]);

        $response->assertRedirect('/products');
        $this->assertDatabaseHas('products', ['id' => $id, 'name' => 'Nama Baru']);
    }

    public function test_destroy_deletes_product(): void
    {
        $id = $this->makeProduct();

        $response = $this->actingAsSpUser()->delete("/products/{$id}");

        $response->assertRedirect('/products');
        $this->assertDatabaseMissing('products', ['id' => $id]);
    }
}
