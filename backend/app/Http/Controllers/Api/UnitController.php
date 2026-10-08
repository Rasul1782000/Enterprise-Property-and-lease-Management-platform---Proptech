<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $units = QueryBuilder::for(Unit::class)
            ->with(['building', 'building.property', 'activeLease'])
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('unit_type'),
                AllowedFilter::exact('building_id'),
                AllowedFilter::exact('type', 'unit_type'),
                AllowedFilter::partial('unit_number'),
                AllowedFilter::partial('code', 'unit_number'),
            ])
            ->allowedSorts(['rent_amount', 'sqft', 'floor', 'created_at'])
            ->allowedIncludes(['building', 'building.property', 'activeLease', 'leases'])
            ->paginate($request->get('per_page', 15))
            ->appends($request->query());

        return UnitResource::collection($units);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'building_id' => 'required|exists:buildings,id',
            'code' => 'required|string|max:50',
            'name' => 'nullable|string|max:255',
            'floor' => 'required|integer|min:0',
            'area_sqft' => 'required|numeric|min:1',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'base_rent' => 'required|numeric|min:0',
            'type' => 'required|in:residential,commercial,office,retail,warehouse',
            'status' => 'sometimes|in:vacant,occupied,reserved,under_maintenance',
            'amenities' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $unit = Unit::create([
            'building_id' => $data['building_id'],
            'unit_number' => $data['code'],
            'name' => $data['name'] ?? null,
            'floor' => $data['floor'],
            'sqft' => $data['area_sqft'],
            'bedrooms' => $data['bedrooms'] ?? null,
            'bathrooms' => $data['bathrooms'] ?? null,
            'rent_amount' => $data['base_rent'],
            'unit_type' => $data['type'],
            'status' => $data['status'] ?? 'vacant',
            'amenities' => $data['amenities'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        return new UnitResource($unit->load(['building', 'building.property', 'activeLease']));
    }

    public function show(Unit $unit)
    {
        return new UnitResource(
            $unit->load(['building.property', 'leases.tenant', 'activeLease'])
        );
    }

    public function update(Request $request, Unit $unit)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50',
            'floor' => 'sometimes|integer|min:0',
            'area_sqft' => 'sometimes|numeric|min:1',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'base_rent' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|in:residential,commercial,office,retail,warehouse',
            'status' => 'sometimes|in:vacant,occupied,reserved,under_maintenance',
            'description' => 'nullable|string',
        ]);

        $columns = [
            'name' => 'name',
            'code' => 'unit_number',
            'floor' => 'floor',
            'area_sqft' => 'sqft',
            'bedrooms' => 'bedrooms',
            'bathrooms' => 'bathrooms',
            'base_rent' => 'rent_amount',
            'type' => 'unit_type',
            'status' => 'status',
            'description' => 'description',
        ];

        $unit->fill(array_combine(
            array_map(fn ($key) => $columns[$key], array_keys($data)),
            array_values($data)
        ))->save();

        return new UnitResource(
            $unit->fresh(['building', 'building.property', 'activeLease'])
        );
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();

        return response()->json(null, 204);
    }

    public function updateStatus(Request $request, Unit $unit)
    {
        $unit->update($request->validate([
            'status' => 'required|in:vacant,occupied,reserved,under_maintenance',
        ]));

        return new UnitResource(
            $unit->fresh(['building', 'building.property', 'activeLease'])
        );
    }
}