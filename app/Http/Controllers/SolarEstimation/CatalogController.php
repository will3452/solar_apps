<?php

namespace App\Http\Controllers\SolarEstimation;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolarEstimation\CatalogItemRequest;
use App\Models\SolarEstimation\CatalogItem;
use App\Services\SolarEstimation\DependencyGraph;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $query = CatalogItem::query();
        $search = mb_substr((string) $request->string('search'), 0, 100);
        if ($search !== '') {
            $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%'));
        }

        return view('modules.solar-estimation.catalog.index', ['items' => $query->orderBy('id')->paginate(30)->withQueryString(), 'search' => $search]);
    }

    public function create(): View
    {
        return $this->form(new CatalogItem(['role' => 'material', 'quantity_rule' => 'fixed', 'quantity_factor' => 1, 'default_quantity' => 1, 'group_size' => 6, 'active' => true, 'unit' => 'pcs', 'specification_status' => 'source_unverified', 'specs' => []]));
    }

    public function store(CatalogItemRequest $request, DependencyGraph $graph): RedirectResponse
    {
        return $this->save($request, new CatalogItem, $graph);
    }

    public function edit(CatalogItem $catalogItem): View
    {
        return $this->form($catalogItem->load('dependencies'));
    }

    public function update(CatalogItemRequest $request, CatalogItem $catalogItem, DependencyGraph $graph): RedirectResponse
    {
        return $this->save($request, $catalogItem, $graph);
    }

    private function form(CatalogItem $item): View
    {
        return view('modules.solar-estimation.catalog.form', ['item' => $item, 'items' => CatalogItem::where('id', '!=', $item->id ?? 0)->orderBy('name')->get(), 'rules' => CatalogItem::quantityRules(), 'specFields' => CatalogItemRequest::SPEC_FIELDS]);
    }

    private function save(CatalogItemRequest $request, CatalogItem $item, DependencyGraph $graph): RedirectResponse
    {
        DB::transaction(function () use ($request, $item, $graph): void {
            $input = $request->validated();
            $item->fill(collect($input)->except(['dependencies', 'specs_json'])->all());
            $item->save();
            $item->dependencies()->sync($input['dependencies'] ?? []);
            $all = CatalogItem::with('dependencies')->get()->keyBy('id');
            $graph->ordered($all, $all->modelKeys());
        });

        return to_route('modules.solar-estimation.catalog.index')->with('status', 'Catalog item saved. Existing estimates keep their saved prices.');
    }
}
