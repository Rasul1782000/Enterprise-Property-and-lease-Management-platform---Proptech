<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lease_number' => $this->lease_number,
            'unit' => $this->whenLoaded('unit'),
            'tenant' => $this->whenLoaded('tenant'),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'rent_amount' => $this->rent_amount,
            'deposit_amount' => $this->deposit_amount,
            'status' => $this->status,
            'payment_frequency' => $this->payment_frequency,
            'terms' => $this->terms,
            'invoices' => $this->whenLoaded('invoices'),
            'created_at' => $this->created_at,
        ];
    }
}
