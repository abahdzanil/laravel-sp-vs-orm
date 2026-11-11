<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_redirects_to_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'User Baru',
            'email' => 'userbaru@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', ['email' => 'userbaru@example.com']);
        $response->assertSessionHas('sp_user_id');
    }

    public function test_register_rejects_invalid_email_format(): void
    {
        $response = $this->post('/register', [
            'name' => 'User Invalid',
            'email' => 'bukan-email',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        DB::table('users')->insert([
            'name' => 'Existing',
            'email' => 'existing@example.com',
            'password' => Hash::make('secret123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/register', [
            'name' => 'User Baru',
            'email' => 'existing@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_login_success_redirects_to_dashboard(): void
    {
        DB::table('users')->insert([
            'name' => 'Login User',
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('sp_user_id');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        DB::table('users')->insert([
            'name' => 'Login User',
            'email' => 'login2@example.com',
            'password' => Hash::make('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => 'login2@example.com',
            'password' => 'wrongpass',
        ]);

        $response->assertSessionHasErrors(['email']);
        $response->assertSessionMissing('sp_user_id');
    }

    public function test_logout_clears_session(): void
    {
        $this->withSession(['sp_user_id' => 1, 'sp_user_name' => 'Test']);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $response->assertSessionMissing('sp_user_id');
    }
}
