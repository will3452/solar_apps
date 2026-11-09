<x-auth-layout title="Create your account" description="One account for all your solar business modules.">
    <form method="POST" action="{{ route('register.store') }}" class="auth-form">
        @csrf
        <x-form-field name="name" label="Full name" autocomplete="name" maxlength="255" required autofocus />
        <x-form-field name="email" label="Email address" type="email" autocomplete="username" maxlength="255" required />
        <x-form-field name="password" label="Password" type="password" autocomplete="new-password" minlength="8" hint="Use at least 8 characters." required />
        <x-form-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" minlength="8" required />
        <button type="submit" class="button button-primary">Create account</button>
    </form>
    <p class="auth-switch">Already have an account? <a class="text-link" href="{{ route('login') }}">Sign in</a></p>
</x-auth-layout>
