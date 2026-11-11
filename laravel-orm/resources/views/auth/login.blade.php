@extends('layouts.guest')
@section('content')
<div class="col-md-4 offset-md-4 mt-5">
    <h4 class="mb-3">Login</h4>
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="/login">
        @csrf
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
        <a href="/register" class="btn btn-outline-secondary">Register</a>
    </form>
</div>
@endsection
