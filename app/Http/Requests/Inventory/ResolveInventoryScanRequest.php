<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ResolveInventoryScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'raw_code' => ['required', 'string', 'max:255'],
            'scan_session_uuid' => ['nullable', 'uuid'],
            'step' => ['nullable', 'string', 'max:30'],
            'device_code' => ['nullable', 'string', 'max:100'],
            'movement_id' => ['nullable', 'integer'],
        ];
    }
}
