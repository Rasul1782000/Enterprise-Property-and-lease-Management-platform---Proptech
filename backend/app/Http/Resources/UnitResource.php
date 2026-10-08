<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $building = $this->relationLoaded('building') ? $this->building : null;
        $activeLease = $this->relationLoaded('activeLease') ? $this->activeLease : null;

        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'property_id' => $building?->property_id,
            'code' => $this->unit_number,
            'name' => $this->name ?: $this->unit_number,
            'type' => $this->unit_type,
            'floor' => $this->floor,
            'area_sqft' => (float) $this->sqft,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'base_rent' => (float) $this->rent_amount,
            'status' => $this->status,
            'amenities' => $this->amenities,
            'description' => $this->description,
            'current_lease_id' => $activeLease?->id,
            'current_tenant_id' => $activeLease?->tenant_id,
            'building' => $this->whenLoaded('building'),
            'property' => $this->whenLoaded('property'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}