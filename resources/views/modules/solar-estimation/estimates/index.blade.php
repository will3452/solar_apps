<x-app-layout title="Solar Estimation Module">
    <header class="page-heading heading-actions">
        <div><h1>Solar Estimation Module</h1><p>From sizing to materials and a customer proposal.</p></div>
        <a class="button button-primary button-auto" href="{{ route('modules.solar-estimation.estimates.create') }}">New estimate</a>
    </header>
    <x-estimation-nav />
    <section class="surface" aria-labelledby="estimates-heading">
        <h2 id="estimates-heading">Estimates</h2>
        <p class="section-help">Saved estimates keep their own prices and assumptions.</p>
        @forelse ($estimates as $estimate)
            <a class="estimate-row" href="{{ route('modules.solar-estimation.estimates.show', $estimate) }}">
                <div><strong>{{ $estimate->customer_name }}</strong><p>{{ $estimate->reference }} · {{ $estimate->updated_at->format('d M Y') }}</p></div>
                <div class="estimate-row-total">₱{{ number_format($estimate->snapshot['pricing']['total_cents'] / 100, 2) }}<span>Draft estimate</span></div>
            </a>
        @empty
            <p class="empty-message">Start with the customer’s monthly electricity use. We’ll build the first materials list for you.</p>
        @endforelse
        <div class="pagination">{{ $estimates->links() }}</div>
    </section>
</x-app-layout>
