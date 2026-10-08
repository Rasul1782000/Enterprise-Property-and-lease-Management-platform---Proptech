<?php

namespace App\Http\Resources;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unit = $this->relationLoaded('unit') ? $this->unit : null;
        $building = $this->relationLoaded('building')
            ? $this->building
            : (($unit !== null && $unit->relationLoaded('building')) ? $unit->building : null);

        return [
            'id' => $this->id,
            'property_id' => $building?->property_id,
            'building_id' => $unit?->building_id ?? $building?->id,
            'unit_id' => $this->unit_id,
            'tenant_id' => $this->tenant_id,
            'code' => $this->lease_number,
            'type' => $this->type,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'rent_amount' => (float) $this->rent_amount,
            'deposit_amount' => (float) $this->deposit_amount,
            'late_fee_percent' => (float) $this->late_fee_percent,
            'due_day' => $this->due_day,
            'payment_frequency' => $this->payment_frequency,
            'escalation_clause' => $this->escalation_clause,
            'renewal_options' => $this->renewal_options,
            'terms' => $this->terms,
            'notes' => $this->notes,
            'signed_at' => $this->signed_at,
            'terminated_at' => $this->terminated_at,
            'property' => $this->whenLoaded('property'),
            'building' => $this->whenLoaded('building'),
            'unit' => $this->whenLoaded('unit'),
            'tenant' => $this->whenLoaded('tenant'),
            'invoices' => $this->whenLoaded('invoices'),
            'payments' => $this->whenLoaded('payments'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}