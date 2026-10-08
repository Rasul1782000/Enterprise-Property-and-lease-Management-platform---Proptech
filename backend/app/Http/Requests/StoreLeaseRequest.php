<?php

namespace App\Http\Requests;

use App\Models\Lease;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_id' => 'required|exists:units,id',
            'tenant_id' => 'required|exists:tenants,id',
            'type' => 'nullable|in:fixed,periodic,commercial,residential',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'rent_amount' => 'required|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'late_fee_percent' => 'nullable|numeric|min:0|max:100',
            'due_day' => 'nullable|integer|min:1|max:28',
            'status' => 'sometimes|in:draft,active,expired,terminated,renewed',
            'payment_frequency' => 'sometimes|in:monthly,quarterly,yearly',
            'escalation_clause' => 'nullable|string',
            'renewal_options' => 'nullable|integer|min:0',
            'terms' => 'nullable|array',
            'notes' => 'nullable|string',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {

            if ($this->unit_id && $this->start_date && $this->end_date) {
                $conflict = Lease::where('unit_id', $this->unit_id)
                    ->where('status', 'active')
                    ->where(function ($q) {
                        $q->whereBetween('start_date', [$this->start_date, $this->end_date])
                            ->orWhereBetween('end_date', [$this->start_date, $this->end_date])
                            ->orWhere(function ($qq) {
                                $qq->where('start_date', '<=', $this->start_date)->where('end_date', '>=', $this->end_date);
                            });
                    })->exists();
                if ($conflict) {
                    $v->errors()->add('unit_id', 'Unit already has an active lease overlapping these dates.');
                }
            }
        });
    }
}
