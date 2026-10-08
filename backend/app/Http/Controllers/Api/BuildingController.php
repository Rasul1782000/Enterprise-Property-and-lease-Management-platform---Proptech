<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuildingResource;
use App\Models\Building;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BuildingController extends Controller
{
    public function index(Request $request)
    {
        $buildings = QueryBuilder::for(Building::class)
            ->with('property')
            ->withCount('units')
            ->allowedFilters([
                AllowedFilter::exact('property_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::partial('city'),
            ])
            ->allowedSorts(['name', 'code', 'floors', 'created_at'])
            ->paginate($request->get('per_page', 15))
            ->appends($request->query());

        return BuildingResource::collection($buildings);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:buildings,code',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:120',
            'zip' => 'nullable|string|max:20',
            'floors' => 'required|integer|min:1',
            'year_built' => 'nullable|integer',
            'construction_type' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,under_maintenance',
            'description' => 'nullable|string',
        ]);

        $building = Building::create($data);

        return new BuildingResource($building->load('property')->loadCount('units'));
    }

    public function show(Building $building)
    {
        return new BuildingResource(
            $building->load(['property', 'units'])->loadCount('units')
        );
    }

    public function update(Request $request, Building $building)
    {
        $building->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:buildings,code,'.$building->id,
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:120',
            'zip' => 'nullable|string|max:20',
            'floors' => 'sometimes|integer|min:1',
            'year_built' => 'nullable|integer',
            'construction_type' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,under_maintenance',
            'description' => 'nullable|string',
        ]));

        return new BuildingResource(
            $building->fresh(['property', 'units'])->loadCount('units')
        );
    }

    public function destroy(Building $building)
    {
        $building->delete();

        return response()->json(null, 204);
    }
}