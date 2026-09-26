<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('property')?->id;
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:properties,code,'.$id,
            'type' => 'required|in:commercial,multi_family,mixed_use',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:50',
            'zip' => 'nullable|string|max:20',
            'country' => 'sometimes|string|max:100',
            'total_area_sqft' => 'nullable|numeric|min:0',
            'year_built' => 'nullable|integer|min:1800|max:'.(date('Y')+1),
            'manager_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,under_maintenance',
        ];
    }
}
