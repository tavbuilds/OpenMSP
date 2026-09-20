@extends('portal.layouts.app')

@section('title', 'Sign in')

@section('content')
<div class="login-box card">
    <h1>Customer portal</h1>
    <p class="muted">Request a sign-in link. We email a one-time link — no password.</p>
    <form method="POST" action="{{ route('portal.login.request') }}" style="margin-top:1.25rem">
        @csrf
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" inputmode="email" placeholder="name@company.com">
        <div style="margin-top:1rem">
            <button type="submit" class="btn btn-block">Send sign-in link</button>
        </div>
    </form>
</div>
@endsection
