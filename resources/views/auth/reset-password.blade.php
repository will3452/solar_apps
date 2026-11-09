<x-auth-layout title="Choose a new password" description="Set a new password to get back to your workspace.">
    <form method="POST" action="{{ route('password.update') }}" class="auth-form">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        @error('token')
            <p class="field-error" role="alert">{{ $message }}</p>
        @enderror
        <x-form-field name="email" label="Email address" type="email" :value="old('email', $request->email)" autocomplete="username" required />
        <x-form-field name="password" label="New password" type="password" autocomplete="new-password" minlength="8" hint="Use at least 8 characters." required autofocus />
        <x-form-field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" minlength="8" required />
        <button type="submit" class="button button-primary">Reset password</button>
    </form>
    <p class="auth-switch"><a class="text-link" href="{{ route('login') }}">Back to sign in</a></p>
</x-auth-layout>
