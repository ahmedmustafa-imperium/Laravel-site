@extends('layouts.storefront')
@section('title', 'Choose a new password')
@section('content')
<section class="auth-page"><div class="auth-card"><span class="eyebrow">Almost there</span><h1>Choose a new password</h1><form method="post" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><label class="field">Email address<input type="email" name="email" value="{{ old('email', $email) }}" required></label><label class="field">New password<input type="password" name="password" autocomplete="new-password" required></label><label class="field">Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" required></label><button class="button button--full" type="submit">Update password</button></form></div></section>
@endsection
