<x-app-layout :title="$estimate->exists ? 'Edit estimate' : 'New estimate'">
    <header class="page-heading"><h1>{{ $estimate->exists ? 'Edit estimate' : 'New estimate' }}</h1><p>Enter what you know. Review the automatic materials list before preparing a proposal.</p></header>
    <x-estimation-nav />
    <div class="flow-steps" aria-label="Estimate steps"><a href="#sizing">1. Sizing and site</a><a href="#materials">2. Review materials</a>@if($estimate->exists)<a href="{{ route('modules.solar-estimation.estimates.proposal', $estimate) }}">3. Customer proposal</a>@else<span>3. Customer proposal</span>@endif</div>
    @if ($errors->any())<div class="form-errors" role="alert"><h2>Check these details</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $estimate->exists ? route('modules.solar-estimation.estimates.update', $estimate) : route('modules.solar-estimation.estimates.store') }}" data-estimate-form data-preview-url="{{ $estimate->exists ? route('modules.solar-estimation.estimates.preview-saved', $estimate) : route('modules.solar-estimation.estimates.preview') }}">
        @csrf
        @if ($estimate->exists) @method('PUT') @endif
        <section class="surface form-section" id="sizing">
            <h2>Customer and electricity use</h2>
            <div class="field-grid">
                <x-estimate-field name="customer_name" label="Customer name" type="text" :input="$input" required maxlength="255" />
                <x-estimate-field name="address" label="Site address" type="text" :input="$input" maxlength="2000" />
                <x-estimate-field name="monthly_kwh" label="Monthly electricity use (kWh)" :input="$input" min="0" required hint="Use the kWh value on the electricity bill, not its peso amount." />
                <x-estimate-field name="night_percent" label="Share used at night (%)" :input="$input" min="0" max="100" required hint="An estimate of how much electricity is used after sunset." />
                <x-estimate-field name="electricity_rate" label="Electricity price (₱ / kWh)" :input="$input" min="0" required />
            </div>
        </section>
        <section class="surface form-section">
            <h2>Main equipment</h2><p class="section-help">Workbook products are generic allowances. Confirm the actual models before ordering.</p>
            <div class="field-grid">
                @foreach(['panel' => 'Solar panel', 'inverter' => 'Inverter', 'battery' => 'Battery'] as $role => $label)
                    <div class="form-field"><label for="{{ $role }}_id">{{ $label }}</label><select id="{{ $role }}_id" name="{{ $role }}_id" class="form-input" @if($role !== 'battery') required @endif><option value="">Select {{ strtolower($label) }}</option>@foreach($products->get($role, collect()) as $product)<option value="{{ $product->id }}" @selected((string) old($role.'_id', $input[$role.'_id'] ?? '') === (string) $product->id)>{{ $product->name }}</option>@endforeach</select></div>
                @endforeach
                <x-estimate-field name="inverter_count" label="Number of physical inverters" :input="$input" min="1" max="100" required hint="Equipment count. This is separate from each inverter’s kW rating." />
            </div>
            <input type="hidden" name="battery_enabled" value="0">
            <label class="checkbox-label field-check"><input type="checkbox" name="battery_enabled" value="1" @checked(old('battery_enabled', $input['battery_enabled'] ?? true))> Include battery storage and its required accessories</label>
        </section>
        <details class="surface form-section">
            <summary>Sizing assumptions and pricing</summary>
            <p class="section-help">Defaults come from the workbook. Margin uses cost ÷ (1 − margin). Discount is applied before tax.</p>
            <div class="field-grid">
                @foreach(['sun_hours' => ['Useful sun hours / day', 0.1, 24], 'yield_percent' => ['Yield factor (%)', 0.1, 100], 'dod_percent' => ['Usable battery depth (%)', 0.1, 100], 'self_consumption_percent' => ['Generated energy used on site (%)', 0, 100], 'margin_percent' => ['Gross margin (%)', 0, 95], 'tax_percent' => ['Quote tax rate (%)', 0, 100], 'discount_amount' => ['Discount (₱)', 0, 1000000000000]] as $key => $field)
                    <x-estimate-field :name="$key" :label="$field[0]" :input="$input" :min="$field[1]" :max="$field[2]" required />
                @endforeach
                <x-estimate-field name="peak_load_kw" label="Known peak electrical load (kW), if available" :input="$input" min="0" hint="Monthly electricity use does not determine peak load." />
                <x-estimate-field name="panel_count_override" label="Panel count adjustment (blank = automatic)" :input="$input" min="0" />
            </div>
            @if ($estimate->exists)<label class="checkbox-label field-check"><input type="checkbox" name="refresh_catalog_prices" value="1"> Refresh buying prices from the current catalog</label><p class="field-hint">Leave unchecked to keep saved buying prices. Product specifications are recalculated when you save.</p>@endif
        </details>
        <details class="surface form-section" id="cable-details">
            <summary>Site routes and cable checks</summary>
            <p class="section-help">While these details are missing, the workbook cable allowances stay visible. Enter circuit values from the actual product manual and electrical design. A passing result is a candidate for review.</p>
            @foreach($circuits as $key => $label)
                <details class="circuit-details"><summary>{{ $label }}</summary>
                    <div class="field-grid">
                        @foreach($circuitFields as $field => $fieldLabel)
                            <x-estimate-field :name="'circuits['.$key.']['.$field.']'" :label="$fieldLabel" :input="$input" min="0.01" />
                        @endforeach
                        <div class="form-field"><label for="phase-{{ $key }}">Circuit type</label><select id="phase-{{ $key }}" name="circuits[{{ $key }}][phase]" class="form-input"><option value="">Choose when known</option>@foreach(['dc' => 'DC', 'single' => 'Single-phase AC', 'three' => 'Three-phase AC'] as $value => $name)<option value="{{ $value }}" @selected(old('circuits.'.$key.'.phase', data_get($input, 'circuits.'.$key.'.phase')) === $value)>{{ $name }}</option>@endforeach</select></div>
                        <x-estimate-field :name="'circuits['.$key.'][basis]'" label="Product manual / design reference" type="text" :input="$input" maxlength="1000" />
                    </div>
                </details>
            @endforeach
            <p class="field-hint">Voltage drop uses documented conductor resistance. AC reactance, surge, short-circuit duty and parallel-current sharing are outside these checks. Enter current per run and confirm all protection with the project designer.</p>
        </details>
        <section class="surface form-section" id="materials">
            <div class="heading-actions"><div><h2>Review materials and prices</h2><p class="section-help">This updates as you change the estimate. Nothing is saved until you choose Save.</p></div><button type="button" class="button button-secondary" data-recalculate>Recalculate</button></div>
            <p data-preview-status class="field-hint" role="status" aria-live="polite"></p>
            <div data-estimate-preview>@if($result) @include('modules.solar-estimation.estimates.review', ['editable' => true]) @else <p class="empty-message">Enter the customer name and electricity use to see the automatic materials list.</p> @endif</div>
        </section>
        <details class="surface form-section">
            <summary>Additional works</summary><p class="section-help">Use the materials adjustments for the workbook’s roof tile and restorative allowances. Add other work here once.</p>
            <div data-additional-rows>
                @foreach(old('additional_works', $input['additional_works'] ?? [[]]) as $index => $work)
                    <div class="additional-row"><x-estimate-field :name="'additional_works['.$index.'][description]'" label="Work description" type="text" :input="$input" /><x-estimate-field :name="'additional_works['.$index.'][quantity]'" label="Quantity" :input="$input" min="0" /><x-estimate-field :name="'additional_works['.$index.'][unit]'" label="Unit" type="text" :input="$input" /><x-estimate-field :name="'additional_works['.$index.'][unit_cost]'" label="Buying price / unit (₱)" :input="$input" min="0" /><button type="button" class="text-link" data-remove-work>Remove</button></div>
                @endforeach
            </div>
            <button type="button" class="button button-secondary" data-add-work>Add work item</button>
            <template data-work-template><div class="additional-row"><x-estimate-field name="additional_works[__index__][description]" label="Work description" type="text" /><x-estimate-field name="additional_works[__index__][quantity]" label="Quantity" min="0" /><x-estimate-field name="additional_works[__index__][unit]" label="Unit" type="text" /><x-estimate-field name="additional_works[__index__][unit_cost]" label="Buying price / unit (₱)" min="0" /><button type="button" class="text-link" data-remove-work>Remove</button></div></template>
        </details>
        <details class="surface form-section"><summary>Proposal notes and terms</summary><div class="form-field"><label for="notes">Scope and assumptions for the customer</label><textarea id="notes" name="notes" class="form-input" rows="4">{{ old('notes', $input['notes'] ?? '') }}</textarea></div><div class="form-field"><label for="terms">Payment, delivery and warranty terms</label><textarea id="terms" name="terms" class="form-input" rows="4">{{ old('terms', $input['terms'] ?? '') }}</textarea></div></details>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('modules.solar-estimation.index') }}">Back to estimates</a><button type="submit" class="button button-primary button-auto">Save and review materials</button></div>
    </form>
</x-app-layout>
