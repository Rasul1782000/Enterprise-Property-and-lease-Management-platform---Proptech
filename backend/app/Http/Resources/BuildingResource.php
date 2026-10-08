<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'floors' => $this->floors,
            'year_built' => $this->year_built,
            'construction_type' => $this->construction_type,
            'description' => $this->description,
            'status' => $this->status,
            'units_count' => $this->whenCounted('units'),
            'property' => $this->whenLoaded('property'),
            'units' => $this->whenLoaded('units'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}