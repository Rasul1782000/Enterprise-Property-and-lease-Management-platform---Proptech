<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paidAmount = $this->relationLoaded('payments')
            ? (float) $this->payments->sum('amount')
            : (float) ($this->payments_sum_amount ?? $this->payments()->sum('amount'));

        $total = (float) $this->total_amount;

        return [
            'id' => $this->id,
            'lease_id' => $this->lease_id,
            'property_id' => $this->resolvePropertyId(),
            'tenant_id' => $this->tenant_id,
            'unit_id' => $this->unit_id,
            'code' => $this->invoice_number,
            'type' => $this->type,
            'status' => $this->status,
            'issue_date' => $this->issue_date,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'due_date' => $this->due_date,
            'paid_date' => $this->paidAt(),
            'amount' => (float) $this->amount,
            'late_fee' => (float) $this->late_fee,
            'total_amount' => $total,
            'paid_amount' => $paidAmount,
            'balance' => round($total - $paidAmount, 2),
            'currency' => $this->currency,
            'description' => $this->notes,
            'notes' => $this->notes,
            'sent_at' => $this->sent_at,
            'lease' => $this->whenLoaded('lease'),
            'tenant' => $this->whenLoaded('tenant'),
            'unit' => $this->whenLoaded('unit'),
            'property' => $this->whenLoaded('property'),
            'payments' => $this->whenLoaded('payments'),
            'line_items' => $this->whenLoaded('lineItems'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function resolvePropertyId(): ?int
    {
        $building = $this->relationLoaded('unit') ? $this->unit?->building : null;

        return $building?->property_id;
    }

    private function paidAt(): ?string
    {
        $latest = $this->relationLoaded('payments')
            ? $this->payments->sortByDesc('paid_at')->first()
            : $this->payments()->orderByDesc('paid_at')->first();

        return $latest?->paid_at?->toDateTimeString();
    }
}