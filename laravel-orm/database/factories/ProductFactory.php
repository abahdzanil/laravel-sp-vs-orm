<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @var array<string, array<int, string>>
     */
    protected array $productNamesByCategory = [
        'Elektronik' => [
            'Kulkas 2 Pintu', 'Mesin Cuci Front Loading', 'TV LED 43 Inch', 'AC Split 1 PK',
            'Blender 3 in 1', 'Rice Cooker Digital', 'Setrika Uap', 'Kipas Angin Berdiri',
            'Speaker Bluetooth', 'Power Bank 10000mAh', 'Headset Wireless', 'Router WiFi',
        ],
        'Alat Tulis' => [
            'Pulpen Gel', 'Pensil 2B', 'Buku Tulis 58 Lembar', 'Spidol Whiteboard',
            'Penghapus Karet', 'Penggaris 30 cm', 'Stapler Kecil', 'Tinta Printer',
            'Map Plastik', 'Correction Tape', 'Kertas HVS A4', 'Highlighter Set',
        ],
        'Furnitur' => [
            'Meja Kerja Minimalis', 'Kursi Kantor Ergonomis', 'Lemari Pakaian 2 Pintu', 'Rak Buku Susun',
            'Sofa 3 Dudukan', 'Meja Makan Lipat', 'Kasur Busa Single', 'Nakas Kayu',
            'Kursi Makan', 'Rak Sepatu', 'Meja Rias', 'Lemari Arsip',
        ],
        'Pakaian' => [
            'Kaos Polos Cotton', 'Kemeja Formal Lengan Panjang', 'Celana Chino', 'Jaket Hoodie',
            'Kaos Kaki Katun', 'Rok Span', 'Blouse Wanita', 'Celana Jeans',
            'Jas Hujan', 'Sweater Rajut', 'Kemeja Flanel', 'Dress Casual',
        ],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(array_keys($this->productNamesByCategory));

        return [
            'name'      => fake()->randomElement($this->productNamesByCategory[$category]),
            'sku'       => strtoupper(fake()->unique()->bothify('SKU-###??')),
            'category'  => $category,
            'price'     => fake()->numberBetween(10000, 5000000),
            'stock'     => fake()->numberBetween(0, 500),
        ];
    }
}
