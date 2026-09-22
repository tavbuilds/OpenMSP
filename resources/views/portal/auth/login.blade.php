@extends('portal.layouts.app')

@section('title', __('Sign in'))

@section('content')
<div class="login-box card">
    <h1>{{ __('Customer portal') }}</h1>
    <p class="muted lede">{{ __('See your active services and invoices. We email you a one-time sign-in link — there is no password to remember.') }}</p>

    <form method="POST" action="{{ route('portal.login.request') }}">
        @csrf
        <label for="email">{{ __('Email') }}</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
               autocomplete="email" inputmode="email" placeholder="name@company.com">
        <p class="field-hint">{{ __('Use the address your contact details are registered under.') }}</p>

        <div style="margin-top:1.25rem">
            <button type="submit" class="btn btn-block">{{ __('Send sign-in link') }}</button>
        </div>
    </form>
</div>
@endsection
