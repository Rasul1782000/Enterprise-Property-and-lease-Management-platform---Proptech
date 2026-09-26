<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'status' => $this->status,
            'total_area_sqft' => $this->total_area_sqft,
            'year_built' => $this->year_built,
            'occupancy_rate' => $this->when(!is_null($this->occupancy_rate), $this->occupancy_rate),
            'manager' => $this->whenLoaded('manager'),
            'buildings' => $this->whenLoaded('buildings'),
            'buildings_count' => $this->whenCounted('buildings'),
            'units_count' => $this->when(isset($this->units_count), $this->units_count),
            'created_at' => $this->created_at,
        ];
    }
}
