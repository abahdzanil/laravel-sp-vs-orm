<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $perPage = 20;
        $currentPage = (int) $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        $rows = DB::select('CALL sp_employee_list(?, ?)', [$perPage, $offset]);
        $total = DB::select('CALL sp_employee_count()')[0]->total;

        $employees = new LengthAwarePaginator($rows, $total, $perPage, $currentPage, [
            'path' => $request->url(),
        ]);

        return view('employees.index', compact('employees'));
    }

    public function create()
    {
        return view('employees.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'position' => 'required|string|max:100',
            'department' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0',
            'hire_date' => 'required|date',
        ]);

        $emailTaken = DB::select('CALL sp_employee_email_exists(?, ?)', [$validated['email'], 0])[0]->total;
        if ($emailTaken > 0) {
            return back()->withErrors(['email' => 'Email sudah digunakan.'])->withInput();
        }

        DB::statement('CALL sp_employee_create(?, ?, ?, ?, ?, ?)', [
            $validated['name'], $validated['email'], $validated['position'],
            $validated['department'], $validated['salary'], $validated['hire_date'],
        ]);

        return redirect('/employees')->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $rows = DB::select('CALL sp_employee_find(?)', [$id]);
        abort_if(count($rows) === 0, 404);
        $employee = $rows[0];

        return view('employees.edit', compact('employee'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'position' => 'required|string|max:100',
            'department' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0',
            'hire_date' => 'required|date',
        ]);

        $emailTaken = DB::select('CALL sp_employee_email_exists(?, ?)', [$validated['email'], $id])[0]->total;
        if ($emailTaken > 0) {
            return back()->withErrors(['email' => 'Email sudah digunakan.'])->withInput();
        }

        DB::statement('CALL sp_employee_update(?, ?, ?, ?, ?, ?, ?)', [
            $id, $validated['name'], $validated['email'], $validated['position'],
            $validated['department'], $validated['salary'], $validated['hire_date'],
        ]);

        return redirect('/employees')->with('success', 'Karyawan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        DB::statement('CALL sp_employee_delete(?)', [$id]);
        return redirect('/employees')->with('success', 'Karyawan berhasil dihapus.');
    }
}
