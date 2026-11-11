<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/employees');

        $response->assertRedirect('/login');
    }

    public function test_index_displays_employees(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['name' => 'Karyawan A']);

        $response = $this->actingAs($user)->get('/employees');

        $response->assertStatus(200);
        $response->assertViewHas('employees');
    }

    public function test_store_creates_employee(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/employees', [
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
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/employees', []);

        $response->assertSessionHasErrors(['name', 'email', 'position', 'department', 'salary', 'hire_date']);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['email' => 'dup@example.com']);

        $response = $this->actingAs($user)->post('/employees', [
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
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['name' => 'Karyawan Lama', 'email' => 'lama@example.com']);

        $response = $this->actingAs($user)->put("/employees/{$employee->id}", [
            'name' => 'Karyawan Sudah Diupdate',
            'email' => 'lama@example.com',
            'position' => 'Manager',
            'department' => 'Finance',
            'salary' => 12000000,
            'hire_date' => '2026-01-01',
        ]);

        $response->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => 'Karyawan Sudah Diupdate', 'position' => 'Manager']);
    }

    public function test_update_rejects_email_used_by_another_employee(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['email' => 'a@example.com']);
        $employeeB = Employee::factory()->create(['email' => 'b@example.com']);

        $response = $this->actingAs($user)->put("/employees/{$employeeB->id}", [
            'name' => 'Karyawan B Diubah',
            'email' => 'a@example.com',
            'position' => 'Staff',
            'department' => 'IT',
            'salary' => 5000000,
            'hire_date' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseHas('employees', ['id' => $employeeB->id, 'email' => 'b@example.com']);
    }

    public function test_update_allows_keeping_same_email(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['email' => 'same@example.com']);

        $response = $this->actingAs($user)->put("/employees/{$employee->id}", [
            'name' => 'Nama Baru',
            'email' => 'same@example.com',
            'position' => 'Staff',
            'department' => 'IT',
            'salary' => 7000000,
            'hire_date' => '2026-01-01',
        ]);

        $response->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => 'Nama Baru']);
    }

    public function test_destroy_deletes_employee(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->delete("/employees/{$employee->id}");

        $response->assertRedirect('/employees');
        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }
}
