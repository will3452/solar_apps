<?php

namespace App\Http\Controllers\SolarEstimation;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolarEstimation\EstimateRequest;
use App\Models\SolarEstimation\CatalogItem;
use App\Models\SolarEstimation\Estimate;
use App\Services\SolarEstimation\CableSelector;
use App\Services\SolarEstimation\EstimateCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class EstimateController extends Controller
{
    public function index(): View
    {
        return view('modules.solar-estimation.estimates.index', ['estimates' => Estimate::latest('updated_at')->paginate(15)]);
    }

    public function create(): View
    {
        $input = config('solar_estimation.defaults');
        foreach (['panel', 'inverter', 'battery'] as $role) {
            $input[$role.'_id'] = CatalogItem::where('active', true)->where('role', $role)->value('id');
        }
        $input['customer_name'] = '';

        return $this->form(new Estimate, $input);
    }

    public function store(EstimateRequest $request, EstimateCalculator $calculator): RedirectResponse
    {
        $input = $request->validated();
        $estimate = Estimate::create(['reference' => 'SE-'.Str::upper(Str::random(10)), 'customer_name' => $input['customer_name'], 'address' => $input['address'] ?? '', 'inputs' => $input, 'snapshot' => $calculator->calculate($input), 'created_by' => $request->user()->id]);

        return to_route('modules.solar-estimation.estimates.show', $estimate)->with('status', 'Estimate saved. Review the materials, then open the customer proposal.');
    }

    public function show(Estimate $estimate): View
    {
        return view('modules.solar-estimation.estimates.show', ['estimate' => $estimate, 'result' => $estimate->snapshot]);
    }

    public function edit(Estimate $estimate): View
    {
        return $this->form($estimate, $estimate->inputs, $estimate->snapshot);
    }

    public function update(EstimateRequest $request, Estimate $estimate, EstimateCalculator $calculator): RedirectResponse
    {
        $input = $request->validated();
        $estimate->update(['customer_name' => $input['customer_name'], 'address' => $input['address'] ?? '', 'inputs' => $input, 'snapshot' => $calculator->calculate($input, $estimate->snapshot)]);

        return to_route('modules.solar-estimation.estimates.show', $estimate)->with('status', 'Estimate updated. The saved proposal uses this calculation.');
    }

    public function preview(EstimateRequest $request, EstimateCalculator $calculator): JsonResponse
    {
        $estimate = $request->route('estimate');
        $input = $request->validated();
        $result = $calculator->calculate($input, $estimate?->snapshot ?? []);

        return response()->json(['html' => view('modules.solar-estimation.estimates.review', ['result' => $result, 'input' => $input, 'editable' => true])->render()]);
    }

    public function proposal(Estimate $estimate): View
    {
        return view('modules.solar-estimation.estimates.proposal', ['estimate' => $estimate, 'result' => $estimate->snapshot]);
    }

    private function form(Estimate $estimate, array $input, ?array $result = null): View
    {
        return view('modules.solar-estimation.estimates.form', ['estimate' => $estimate, 'input' => $input, 'result' => $result, 'products' => CatalogItem::where('active', true)->whereIn('role', ['panel', 'inverter', 'battery'])->get()->groupBy('role'), 'circuits' => CableSelector::CIRCUITS, 'circuitFields' => CableSelector::FIELDS]);
    }
}
