@php($editable = $editable ?? false)
@php($pendingPrices = collect($result['lines'])->filter(fn ($line) => $line['quantity'] > 0 && $line['unit_cost'] === null)->count())
@php($pendingCables = collect($result['cables'])->whereNotIn('status', ['candidate', 'not_needed'])->count())
@if($pendingCables)<p class="attention-note">{{ $pendingCables }} cable selections need details. Provisional workbook allowances remain in the price.</p>@endif
<div class="result-summary">
    <div><span>Solar panels</span><strong>{{ $result['sizing']['panel_count'] }}</strong><small>{{ number_format($result['sizing']['pv_kwp'], 2) }} kWp</small></div>
    <div><span>Battery storage</span><strong>{{ $result['sizing']['battery_count'] }} units</strong><small>{{ number_format($result['sizing']['battery_kwh'], 2) }} kWh nominal</small></div>
    <div><span>{{ $pendingPrices ? 'Known priced total' : 'Draft total' }}</span><strong>₱{{ number_format($result['pricing']['total_cents'] / 100, 2) }}</strong><small>Includes configured tax and discount</small></div>
</div>
<details class="review-details"><summary>Energy and savings estimates</summary><dl class="facts-grid"><div><dt>Daily electricity use</dt><dd>{{ number_format($result['sizing']['daily_kwh'], 2) }} kWh</dd></div><div><dt>Installed inverter rating</dt><dd>{{ $result['sizing']['inverter_count'] }} units · {{ number_format($result['sizing']['inverter_kw'], 2) }} kW total</dd></div><div><dt>Estimated monthly generation</dt><dd>{{ number_format($result['sizing']['monthly_yield_kwh'], 1) }} kWh</dd></div><div><dt>Estimated monthly savings</dt><dd>₱{{ number_format($result['sizing']['monthly_savings'], 2) }}</dd></div><div><dt>Simple payback</dt><dd>{{ $result['sizing']['payback_months'] === null ? 'Not available at zero savings' : $result['sizing']['payback_months'].' months' }}</dd></div></dl><p class="field-hint">Energy-based target {{ number_format($result['sizing']['energy_target_kwp'], 2) }} kWp. Peak electrical load must be checked separately. Assumes {{ $result['assumptions']['sun_hours'] }} sun hours/day, {{ $result['assumptions']['yield_percent'] }}% yield and {{ $result['assumptions']['self_consumption_percent'] }}% self-consumption.</p></details>
<details class="review-details" @if(!$editable) open @endif><summary>{{ $editable ? 'Adjust quantities and buying prices' : 'Materials and cost breakdown' }}</summary>
    <p class="section-help">Buying cost is quantity × unit buying price. Selling amounts use {{ $result['pricing']['margin_percent'] }}% gross margin. Blank adjustments keep automatic quantities and saved prices. Zero is a valid adjustment.</p>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Material / work</th><th>Quantity</th><th>Unit</th><th>Buy / unit</th><th>Buying total</th><th>Selling total</th>@if($editable)<th>Adjust this estimate</th>@endif</tr></thead><tbody>
    @foreach($result['lines'] as $line)
        <tr><td><strong>{{ $line['name'] }}</strong><span class="line-state">{{ ucfirst($line['status']) }}</span><details class="line-reason"><summary>Quantity and source</summary><p>{{ $line['reason'] }}</p>@if($line['source'])<p>{{ $line['source']['workbook'] ?? '' }} · {{ $line['source']['sheet'] ?? '' }} row {{ $line['source']['row'] ?? '' }}</p>@endif</details></td><td>{{ rtrim(rtrim(number_format($line['quantity'], 3, '.', ''), '0'), '.') }}</td><td>{{ $line['unit'] }}</td><td>{{ $line['unit_cost'] === null ? 'Missing' : '₱'.number_format($line['unit_cost'], 2) }}</td><td>₱{{ number_format($line['buying_cents'] / 100, 2) }}</td><td>₱{{ number_format($line['selling_cents'] / 100, 2) }}</td>
        @if($editable)
            <td>
                @if($line['product_id'])
                    @php($mainEquipment = $line['is_main'] ?? in_array($line['sku'], ['BOM-24', 'BOM-25', 'BOM-26']))
                    <div class="adjustment-fields">
                        @if(!$mainEquipment)
                            <x-estimate-field :name="'overrides['.$line['adjustment_id'].'][quantity]'" label="Quantity adjustment" :input="$input" min="0" />
                        @endif
                        <x-estimate-field :name="'overrides['.$line['adjustment_id'].'][unit_cost]'" label="Buying price adjustment" :input="$input" min="0" />
                        <x-estimate-field :name="'overrides['.$line['adjustment_id'].'][note]'" label="Adjustment reason" type="text" :input="$input" />
                        @if(!$mainEquipment)
                            <label class="checkbox-label"><input type="checkbox" name="overrides[{{ $line['adjustment_id'] }}][exclude]" value="1" @checked(data_get($input, 'overrides.'.$line['adjustment_id'].'.exclude'))> Exclude</label>
                        @endif
                    </div>
                @else
                    <span>See Additional works</span>
                @endif
            </td>
        @endif
        </tr>
    @endforeach
    </tbody></table></div>
</details>
<details class="review-details"><summary>Cable selections and missing details</summary>
    @foreach($result['cables'] as $circuit => $choice)<div class="cable-result"><h3>{{ \App\Services\SolarEstimation\CableSelector::CIRCUITS[$circuit] }} · {{ str_replace('_', ' ', $choice['status']) }}</h3><p>{{ $choice['message'] }}</p>@if(isset($choice['selected']))<p><strong>{{ $choice['selected']['name'] }}</strong> · {{ $choice['selected']['quantity'] }} {{ $choice['selected']['unit'] }} · {{ $choice['selected']['corrected_ampacity_a'] }} A adjusted ampacity · {{ $choice['selected']['drop_percent'] }}% voltage drop.</p><p class="field-hint">Source: {{ $choice['selected']['provenance'] }}</p>@endif @if($choice['missing'])<p class="field-hint">Needed: {{ implode('; ', $choice['missing']) }}.</p>@endif @foreach($choice['rejected'] ?? [] as $reason)<p class="field-hint">{{ $reason }}</p>@endforeach</div>@endforeach
</details>
<details class="review-details"><summary>What needs confirmation</summary><ul class="assumption-list">@foreach($result['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach<li>Grounding, PV string/MPPT compatibility, battery/BMS limits and local installation requirements need project-specific review. An estimate does not provide utility approval.</li></ul></details>
<dl class="price-totals"><div><dt>Total buying cost</dt><dd>₱{{ number_format($result['pricing']['buying_cents'] / 100, 2) }}</dd></div><div><dt>Selling subtotal</dt><dd>₱{{ number_format($result['pricing']['selling_cents'] / 100, 2) }}</dd></div><div><dt>Discount</dt><dd>−₱{{ number_format($result['pricing']['discount_cents'] / 100, 2) }}</dd></div><div><dt>Tax ({{ $result['pricing']['tax_percent'] }}%)</dt><dd>₱{{ number_format($result['pricing']['tax_cents'] / 100, 2) }}</dd></div><div class="total"><dt>{{ $pendingPrices ? 'Known total (prices pending)' : 'Draft estimate total' }}</dt><dd>₱{{ number_format($result['pricing']['total_cents'] / 100, 2) }}</dd></div></dl>
<p class="draft-note">Draft estimate. Incomplete cable selections keep provisional source allowances. Confirm prices, units and design before ordering or installation.</p>
