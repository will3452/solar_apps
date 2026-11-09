<x-auth-layout title="Welcome back" description="Sign in to your workspace to get started.">
    <form method="POST" action="{{ route('login.store') }}" class="auth-form">
        @csrf
        <x-form-field name="email" label="Email address" type="email" autocomplete="username" required autofocus />
        <x-form-field name="password" label="Password" type="password" autocomplete="current-password" required />
        <div class="form-options">
            <label class="checkbox-label" for="remember">
                <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span>Remember me</span>
            </label>
            <a class="text-link" href="{{ route('password.request') }}">Forgot password?</a>
        </div>
        <button type="submit" class="button button-primary">Sign in</button>
    </form>
    @if (Route::has('register'))
        <p class="auth-switch">New here? <a class="text-link" href="{{ route('register') }}">Create an account</a></p>
    @endif
</x-auth-layout>
