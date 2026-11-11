<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate(
            [
                'name'      => 'required|string|max:150',
                'email'     => 'required|email|max:150',
                'password'  => 'required|string|min:8',
            ]
        );

        $existing = DB::select('CALL sp_user_find_by_email(?)', [$validated
        ['email']]);
        if (count($existing) > 0){
            return back()->withErrors([
                'email' => 'Email Sudah Terdaftar.'
            ]);
        }

        DB::statement('CALL sp_user_create(?, ?, ?)',[
            $validated['name'],
            $validated['email'],
            Hash::make($validated['password']),
        ]);

        $user = DB::select('CALL sp_user_find_by_email(?)', [$validated['email']])[0];

        session(['sp_user_id' => $user->id, 'sp_user_name' => $user->name]);

        return redirect('/dashboard');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'     =>  'required|email',
            'password'  =>  'required|string',
        ]);

        $rows = DB::select('CALL sp_user_find_by_email(?)', [$credentials['email']]);
        if (count($rows) > 0 && Hash::check($credentials['password'], $rows[0]->password)) {
            $request->session()->regenerate();
            session(['sp_user_id' => $rows[0]->id, 'sp_user_name' => $rows[0]->name]);
            return redirect('/dashboard');
        }

        return back()->withErrors(['email' => 'Email atau password salah.']);
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['sp_user_id', 'sp_user_name']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
