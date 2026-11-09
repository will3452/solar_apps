<nav class="module-tabs" aria-label="Solar Estimation Module">
    <a href="{{ route('modules.solar-estimation.index') }}" @class(['is-active' => request()->routeIs('modules.solar-estimation.index', 'modules.solar-estimation.estimates.*')])>Estimates</a>
    <a href="{{ route('modules.solar-estimation.catalog.index') }}" @class(['is-active' => request()->routeIs('modules.solar-estimation.catalog.*')])>Materials catalog</a>
</nav>
<x-auth-status />
