<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'tenant_id' => $this->tenant_id,
            'amount' => (float) $this->amount,
            'payment_date' => $this->paid_at,
            'payment_method' => $this->method,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'invoice' => $this->whenLoaded('invoice'),
            'tenant' => $this->whenLoaded('tenant'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}