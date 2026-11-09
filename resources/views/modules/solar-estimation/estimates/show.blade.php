<x-app-layout :title="$estimate->customer_name.' · Estimate'">
    <header class="page-heading heading-actions"><div><h1>{{ $estimate->customer_name }}</h1><p>{{ $estimate->reference }} · Draft estimate · Saved {{ $estimate->updated_at->format('d M Y H:i') }} UTC</p></div><a class="button button-primary button-auto" href="{{ route('modules.solar-estimation.estimates.proposal', $estimate) }}">Customer proposal</a></header>
    <x-estimation-nav />
    <div class="flow-steps"><a href="{{ route('modules.solar-estimation.estimates.edit', $estimate) }}#sizing">1. Edit sizing and site</a><a href="{{ route('modules.solar-estimation.estimates.edit', $estimate) }}#materials">2. Adjust materials</a><a href="{{ route('modules.solar-estimation.estimates.proposal', $estimate) }}">3. Customer proposal</a></div>
    <section class="surface">@include('modules.solar-estimation.estimates.review')</section>
</x-app-layout>
