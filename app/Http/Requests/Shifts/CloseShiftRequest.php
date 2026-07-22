<?php

namespace App\Http\Requests\Shifts;

use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'counted_cash' => ['required', 'numeric', 'min:0'],
        ];
    }
}
