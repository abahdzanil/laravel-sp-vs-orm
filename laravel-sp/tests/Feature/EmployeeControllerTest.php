<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSpUser(): static
    {
        $this->withSession(['sp_user_id' => 1, 'sp_user_name' => 'Test User']);

        return $this;
    }

    private function makeEmployee(array $overrides = []): int
    {
        $data = array_merge([
            'name' => 'Karyawan Test',
            'email' => 'karyawan.'.uniqid().'@example.com',
            'position' => 'Staff',
            'department' => 'IT',
            'salary' => 5000000,
            'hire_date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return DB::table('employees')->insertGetId($data);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/employees');

        $response->assertRedirect('/login');
    }

    public function test_index_displays_employees(): void
    {
        $this->makeEmployee(['name' => 'Karyawan A']);

        $response = $this->actingAsSpUser()->get('/employees');

        $response->assertStatus(200);
        $response->assertViewHas('employees');
    }

    public function test_store_creates_employee(): void
    {
        $response = $this->actingAsSpUser()->post('/employees', [
            'name' => 'Karyawan Baru',
            'email' => 'karyawan.baru@example.com',
            'position' => 'Staff',
            'department' => 'IT',
            'salary' => 6000000,
            'hire_date' => '2026-01-01',
        ]);

        $response->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', ['email' => 'karyawan.baru@example.com', 'name' => 'Karyawan Baru']);
    }

    public function test_store_fails_validation_when_fields_missing(): void
    {
        $response = $this->actingAsSpUser()->post('/employees', []);

        $response->assertSessionHasErrors(['name', 'email', 'position', 'department', 'salary', 'hire_date']);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $this->makeEmployee(['email' => 'dup@example.com']);

        $response = $this->actingAsSpUser()->post('/employees', [
            'name' => 'Karyawan Duplikat',
            'email' => 'dup@example.com',
            'position' => 'Staff',
            'department' => 'IT',
            'salary' => 5000000,
            'hire_date' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_update_modifies_employee(): void
    {
        $id = $this->makeEmployee(['name' => 'Karyawan Lama', 'email' => 'lama@example.com']);

        $response = $this->actingAsSpUser()->put("/employees/{$id}", [
            'name' => 'Karyawan Sudah Diupdate',
            'email' => 'lama@example.com',
            'position' => 'Manager',
            'department' => 'Finance',
            'salary' => 12000000,
            'hire_date' => '2026-01-01',
        ]);

        $response->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', ['id' => $id, 'name' => 'Karyawan Sudah Diupdate', 'position' => 'Manager']);
    }

    public function test_update_rejects_email_used_by_another_employee(): void
    {
        $this->makeEmployee(['email' => 'a@example.com']);
        $idB = $this->makeEmployee(['email' => 'b@example.com']);

        $response = $this->actingAsSpUser()->put("/employees/{$idB}", [
            'name' => 'Karyawan B Diubah',
            'email' => 'a@example.com',
            'position' => 'Staff',
            'department' => 'IT',
            'salary' => 5000000,
            'hire_date' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseHas('employees', ['id' => $idB, 'email' => 'b@example.com']);
    }

    public function test_update_allows_keeping_same_email(): void
    {
        $id = $this->makeEmployee(['email' => 'same@example.com']);

        $response = $this->actingAsSpUser()->put("/employees/{$id}", [
            'name' => 'Nama Baru',
            'email' => 'same@example.com',
            'position' => 'Staff',
            'department' => 'IT',
            'salary' => 7000000,
            'hire_date' => '2026-01-01',
        ]);

        $response->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', ['id' => $id, 'name' => 'Nama Baru']);
    }

    public function test_destroy_deletes_employee(): void
    {
        $id = $this->makeEmployee();

        $response = $this->actingAsSpUser()->delete("/employees/{$id}");

        $response->assertRedirect('/employees');
        $this->assertDatabaseMissing('employees', ['id' => $id]);
    }
}
