@extends('layouts.app')
@section('content')
<h4 class="mb-3">Edit Karyawan</h4>
@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif
<form method="POST" action="/employees/{{ $employee->id }}">
    @csrf
    @method('PUT')
    <div class="mb-3">
        <label class="form-label">Nama</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $employee->name) }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $employee->email) }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Posisi</label>
        <input type="text" name="position" class="form-control" value="{{ old('position', $employee->position) }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Departemen</label>
        <input type="text" name="department" class="form-control" value="{{ old('department', $employee->department) }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Gaji</label>
        <input type="number" step="0.01" name="salary" class="form-control" value="{{ old('salary', $employee->salary) }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Tanggal Masuk</label>
        <input type="date" name="hire_date" class="form-control" value="{{ old('hire_date', $employee->hire_date) }}" required>
    </div>
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="/employees" class="btn btn-outline-secondary">Batal</a>
</form>
@endsection
