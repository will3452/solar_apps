@props(['title'])
<x-page-layout :title="$title">
    <div class="app-shell">
        <header class="app-header">
            <x-brand />
            <div class="account-menu">
                <span class="account-name">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="button button-secondary">Sign out</button>
                </form>
            </div>
        </header>
        <div class="app-body">
            <aside class="app-sidebar">
                <nav class="app-nav" aria-label="Workspace navigation">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>Workspace</a>
                    @foreach (config('modules') as $module)
                        <a href="{{ route($module['route']) }}" class="nav-link {{ request()->routeIs($module['active']) ? 'is-active' : '' }}" @if (request()->routeIs($module['active'])) aria-current="page" @endif>{{ $module['name'] }}</a>
                    @endforeach
                </nav>
            </aside>
            <main id="main-content" class="app-main">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-page-layout>
