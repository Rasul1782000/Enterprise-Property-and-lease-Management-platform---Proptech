<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Support\Storage\S3ClientFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

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
                'code',
            ])
            ->allowedSorts(['name', 'city', 'created_at', 'total_area_sqft'])
            ->allowedIncludes(['buildings', 'buildings.units', 'manager', 'units'])
            ->paginate($request->get('per_page', 15))
            ->appends($request->query());

        return PropertyResource::collection($properties);
    }

    public function store(StorePropertyRequest $request)
    {
        $property = Property::create($request->validated());

        return new PropertyResource($property->load(['buildings', 'manager']));
    }

    public function show(Property $property)
    {
        return new PropertyResource($property->load(['buildings.units', 'manager', 'units']));
    }

    public function update(StorePropertyRequest $request, Property $property)
    {
        $property->update($request->validated());

        return new PropertyResource($property->fresh(['buildings', 'manager']));
    }

    /**
     * Upload a property photo to object storage (Floci in dev/CI, S3 in prod).
     */
    public function uploadImage(Request $request, Property $property)
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $path = $request->file('image')->store(
            'properties/'.$property->id,
            'documents',
        );

        if ($path === false) {
            return response()->json([
                'message' => 'Could not store the image; the property was left unchanged.',
                'storage_endpoint' => S3ClientFactory::endpoint(),
            ], 500);
        }

        // Replace the previous object rather than leaking it in the bucket.
        if ($property->image_path) {
            Storage::disk('documents')->delete($property->image_path);
        }

        $property->update(['image_path' => $path]);

        return response()->json(new PropertyResource($property->fresh()), 201);
    }

    public function destroy(Property $property)
    {
        if ($property->image_path) {
            try {
                Storage::disk('documents')->delete($property->image_path);
            } catch (Throwable) {
                // Deleting the row must succeed even if storage is unreachable.
            }
        }

        $property->delete();

        return response()->json(null, 204);
    }

    public function occupancy(Property $property)
    {
        $totalUnits = $property->units()->count();
        $occupiedUnits = $property->units()->where('status', 'occupied')->count();
        $vacantUnits = $property->units()->where('status', 'vacant')->count();
        $maintenanceUnits = $property->units()->where('status', 'maintenance')->count();
        $occupancyRate = $totalUnits > 0 ? round($occupiedUnits / $totalUnits * 100, 2) : 0;

        return response()->json([
            'property_id' => $property->id,
            'total_units' => $totalUnits,
            'occupied_units' => $occupiedUnits,
            'vacant_units' => $vacantUnits,
            'maintenance_units' => $maintenanceUnits,
            'occupancy_rate' => $occupancyRate,
        ]);
    }

    public function export()
    {
        $properties = Property::with(['buildings', 'manager'])->get();
        $csv = "ID,Name,Code,Type,City,State,Status,Buildings,Manager\n";
        foreach ($properties as $property) {
            $csv .= implode(',', [
                $property->id,
                '"'.str_replace('"', '""', $property->name).'"',
                $property->code,
                $property->type,
                '"'.str_replace('"', '""', $property->city).'"',
                '"'.str_replace('"', '""', $property->state).'"',
                $property->status,
                $property->buildings()->count(),
                $property->manager ? $property->manager->name : 'N/A',
            ])."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="properties.csv"',
        ]);
    }
}
