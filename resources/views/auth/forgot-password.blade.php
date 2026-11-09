<x-auth-layout title="Forgot your password?" description="Enter your account email and we’ll send you a link to reset your password.">
    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
        @csrf
        <x-form-field name="email" label="Email address" type="email" autocomplete="username" required autofocus />
        <button type="submit" class="button button-primary">Send reset link</button>
    </form>
    <p class="auth-switch"><a class="text-link" href="{{ route('login') }}">Back to sign in</a></p>
</x-auth-layout>
