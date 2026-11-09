<a {{ $attributes->class(['brand']) }} href="{{ route('home') }}" aria-label="{{ config('app.name') }} home">
    <svg class="brand-mark" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <circle cx="16" cy="16" r="6" stroke="currentColor" stroke-width="1.8" />
        <path d="M16 2v4m0 20v4M2 16h4m20 0h4M6.1 6.1l2.8 2.8m14.2 14.2 2.8 2.8m0-19.8-2.8 2.8M8.9 23.1l-2.8 2.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
    </svg>
    <span>{{ config('app.name') }}</span>
</a>
