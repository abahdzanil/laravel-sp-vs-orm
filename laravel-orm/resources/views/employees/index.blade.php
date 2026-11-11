@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Karyawan</h4>
    <a href="/employees/create" class="btn btn-primary">Tambah Karyawan</a>
</div>
<table class="table table-striped">
    <thead>
        <tr><th>Nama</th><th>Email</th><th>Posisi</th><th>Departemen</th><th>Gaji</th><th>Tanggal Masuk</th><th></th></tr>
    </thead>
    <tbody>
        @foreach ($employees as $employee)
        <tr>
            <td>{{ $employee->name }}</td>
            <td>{{ $employee->email }}</td>
            <td>{{ $employee->position }}</td>
            <td>{{ $employee->department }}</td>
            <td>{{ number_format($employee->salary, 0, ',', '.') }}</td>
            <td>{{ $employee->hire_date }}</td>
            <td>
                <a href="/employees/{{ $employee->id }}/edit" class="btn btn-sm btn-outline-secondary">Edit</a>
                <form action="/employees/{{ $employee->id }}" method="POST" class="d-inline">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus karyawan ini?')">Hapus</button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $employees->links() }}
@endsection
