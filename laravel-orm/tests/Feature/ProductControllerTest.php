<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/products');

        $response->assertRedirect('/login');
    }

    public function test_index_displays_products(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['name' => 'Produk A']);

        $response = $this->actingAs($user)->get('/products');

        $response->assertStatus(200);
        $response->assertViewHas('products');
    }

    public function test_store_creates_product(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/products', [
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
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/products', []);

        $response->assertSessionHasErrors(['name', 'sku', 'category', 'price', 'stock']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_store_rejects_duplicate_sku(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['sku' => 'SKU-DUP-001']);

        $response = $this->actingAs($user)->post('/products', [
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
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Produk Lama', 'sku' => 'SKU-OLD-001']);

        $response = $this->actingAs($user)->put("/products/{$product->id}", [
            'name' => 'Produk Sudah Diupdate',
            'sku' => 'SKU-OLD-001',
            'category' => 'Elektronik',
            'price' => 20000,
            'stock' => 3,
        ]);

        $response->assertRedirect('/products');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Produk Sudah Diupdate', 'stock' => 3]);
    }

    public function test_update_rejects_sku_used_by_another_product(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['sku' => 'SKU-A-001']);
        $productB = Product::factory()->create(['sku' => 'SKU-B-001']);

        $response = $this->actingAs($user)->put("/products/{$productB->id}", [
            'name' => 'Produk B Diubah',
            'sku' => 'SKU-A-001',
            'category' => 'Elektronik',
            'price' => 5000,
            'stock' => 2,
        ]);

        $response->assertSessionHasErrors(['sku']);
        $this->assertDatabaseHas('products', ['id' => $productB->id, 'sku' => 'SKU-B-001']);
    }

    public function test_update_allows_keeping_same_sku(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['sku' => 'SKU-SAME-001']);

        $response = $this->actingAs($user)->put("/products/{$product->id}", [
            'name' => 'Nama Baru',
            'sku' => 'SKU-SAME-001',
            'category' => 'Elektronik',
            'price' => 9999,
            'stock' => 7,
        ]);

        $response->assertRedirect('/products');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Nama Baru']);
    }

    public function test_destroy_deletes_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->delete("/products/{$product->id}");

        $response->assertRedirect('/products');
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
