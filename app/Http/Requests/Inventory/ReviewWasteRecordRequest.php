<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ReviewWasteRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:approved,review_pending,reversed'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
