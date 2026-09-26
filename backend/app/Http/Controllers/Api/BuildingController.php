<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    public function index(Request $request)
    {
        $q = Building::with(['property','units'])
            ->when($request->property_id, fn($qq)=> $qq->where('property_id',$request->property_id))
            ->when($request->search, fn($qq,$s)=> $qq->where('name','like',"%{$s}%"));
        return response()->json($q->paginate($request->get('per_page',15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id'=>'required|exists:properties,id',
            'name'=>'required|string|max:255',
            'code'=>'required|string|unique:buildings,code',
            'floors'=>'required|integer|min:1',
            'year_built'=>'nullable|integer',
            'construction_type'=>'nullable|string',
            'description'=>'nullable|string',
        ]);
        return response()->json(Building::create($data),201);
    }

    public function show(Building $building)
    {
        return response()->json($building->load(['property','units']));
    }

    public function update(Request $request, Building $building)
    {
        $building->update($request->validate([
            'name'=>'sometimes|string|max:255',
            'code'=>'sometimes|string|unique:buildings,code,'.$building->id,
            'floors'=>'sometimes|integer|min:1',
            'description'=>'nullable|string',
        ]));
        return response()->json($building->fresh());
    }

    public function destroy(Building $building)
    {
        $building->delete();
        return response()->json(null,204);
    }
}
