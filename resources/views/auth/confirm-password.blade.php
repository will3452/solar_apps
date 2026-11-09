<x-auth-layout title="Confirm your password" description="Please confirm your password before continuing.">
    <form method="POST" action="{{ route('password.confirm.store') }}" class="auth-form">
        @csrf
        <x-form-field name="password" label="Password" type="password" autocomplete="current-password" required autofocus />
        <button type="submit" class="button button-primary">Confirm password</button>
    </form>
    <p class="auth-switch"><a class="text-link" href="{{ route('dashboard') }}">Back to workspace</a></p>
</x-auth-layout>
