@extends('layouts.storefront')
@section('title', 'Reset password')
@section('content')
<section class="auth-page"><div class="auth-card"><span class="eyebrow">Password help</span><h1>Reset your password</h1><p>Enter the email used for your SNH account and we’ll send a secure reset link.</p><form method="post" action="{{ route('password.email') }}">@csrf<label class="field">Email address<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label><button class="button button--full" type="submit">Send reset link</button></form><p class="auth-switch"><a href="{{ route('login') }}">← Back to sign in</a></p></div></section>
@endsection
