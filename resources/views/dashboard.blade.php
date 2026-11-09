<x-app-layout title="Workspace">
    <header class="page-heading workspace-heading">
        <h1>Your workspace</h1>
        <p>Choose a module to get started.</p>
    </header>
    <section aria-labelledby="modules-heading" class="module-list">
        <h2 id="modules-heading">Your modules</h2>
        @foreach (config('modules') as $module)
            <a class="module-entry" href="{{ route($module['route']) }}">
                <div>
                    <h3>{{ $module['name'] }}</h3>
                    <p>{{ $module['description'] }}</p>
                </div>
                <span class="module-open">Open module <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
            </a>
        @endforeach
    </section>
</x-app-layout>
