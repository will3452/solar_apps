@props(['title', 'description'])
<x-page-layout :title="$title">
    <div class="auth-shell">
        <aside class="auth-story" aria-label="About your workspace">
            <x-brand />
            <div class="auth-story-copy">
                <h2>Your solar work,<br>in one place.</h2>
                <p>One account. Every module.</p>
            </div>
            <p class="auth-story-footer">A shared workspace for your team.</p>
        </aside>
        <main id="main-content" class="auth-main">
            <div class="auth-form-wrap">
                <header class="page-heading">
                    <h1>{{ $title }}</h1>
                    <p>{{ $description }}</p>
                </header>
                <x-auth-status />
                {{ $slot }}
            </div>
        </main>
    </div>
</x-page-layout>
