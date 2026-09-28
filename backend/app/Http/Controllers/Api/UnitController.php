<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $units = QueryBuilder::for(Unit::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('unit_type'),
                AllowedFilter::exact('building_id'),
                AllowedFilter::partial('unit_number'),
            ])
            ->allowedSorts(['rent_amount', 'sqft', 'floor', 'created_at'])
            ->allowedIncludes(['building', 'building.property', 'activeLease', 'leases'])
            ->paginate($request->get('per_page', 15));

        return response()->json($units);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'building_id' => 'required|exists:buildings,id',
            'unit_number' => 'required|string|max:50',
            'floor' => 'required|integer|min:0',
            'sqft' => 'required|numeric|min:1',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'rent_amount' => 'required|numeric|min:0',
            'unit_type' => 'required|in:office,retail,apartment,studio,warehouse,residential,commercial',
            'status' => 'sometimes|in:vacant,occupied,maintenance,reserved,under_maintenance',
            'amenities' => 'nullable|string',
            'description' => 'nullable|string',
        ]);
        $data['unit_number'] = $data['unit_number'] ?? $data['code'] ?? null;
        $data['sqft'] = $data['sqft'] ?? $data['area_sqft'] ?? null;
        $data['rent_amount'] = $data['rent_amount'] ?? $data['base_rent'] ?? null;
        $data['unit_type'] = $data['unit_type'] ?? $data['type'] ?? null;
        $data['status'] = $data['status'] ?? 'vacant';
        if ($data['status'] === 'under_maintenance') {
            $data['status'] = 'maintenance';
        }
        if ($data['unit_type'] === 'residential') {
            $data['unit_type'] = 'apartment';
        }
        if ($data['unit_type'] === 'commercial') {
            $data['unit_type'] = 'office';
        }
        $unit = Unit::create($data);

        return response()->json($unit->load('building'), 201);
    }

    public function show(Unit $unit)
    {
        return response()->json($unit->load(['building.property', 'leases.tenant', 'activeLease']));
    }

    public function update(Request $request, Unit $unit)
    {
        $unit->update($request->validate([
            'rent_amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:vacant,occupied,maintenance,reserved',
            'description' => 'nullable|string',
        ]));

        return response()->json($unit->fresh());
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();

        return response()->json(null, 204);
    }

    public function updateStatus(Request $request, Unit $unit)
    {
        $unit->update($request->validate([
            'status' => 'required|in:vacant,occupied,maintenance,reserved',
        ]));

        return response()->json($unit->fresh());
    }
}
