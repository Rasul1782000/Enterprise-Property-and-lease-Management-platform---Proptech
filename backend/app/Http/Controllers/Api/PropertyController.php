<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $properties = QueryBuilder::for(Property::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('city'),
                AllowedFilter::partial('name'),
                'code'
            ])
            ->allowedSorts(['name','city','created_at','total_area_sqft'])
            ->allowedIncludes(['buildings','buildings.units','manager','units'])
            ->paginate($request->get('per_page', 15))
            ->appends($request->query());

        return PropertyResource::collection($properties);
    }

    public function store(StorePropertyRequest $request)
    {
        $property = Property::create($request->validated());
        return new PropertyResource($property->load(['buildings','manager']));
    }

    public function show(Property $property)
    {
        return new PropertyResource($property->load(['buildings.units','manager','units']));
    }

    public function update(StorePropertyRequest $request, Property $property)
    {
        $property->update($request->validated());
        return new PropertyResource($property->fresh(['buildings','manager']));
    }

    public function destroy(Property $property)
    {
        $property->delete();
        return response()->json(null, 204);
    }
}
