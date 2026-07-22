<?php

namespace App\Http\Requests\Sales;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any signed-in user can ring up a sale (spec §5 — cashier + admin).
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_unit_id' => ['required', 'integer', 'exists:sale_units,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],

            'payment_method' => ['required', Rule::in([
                Sale::PAYMENT_CASH,
                Sale::PAYMENT_CARD,
                Sale::PAYMENT_QR,
            ])],

            // Cash-only fields — enforced by conditional rules below.
            'cash_tendered' => ['nullable', 'numeric', 'min:0'],

            // QR-only field.
            'qr_reference_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->sometimes(
            'cash_tendered',
            ['required', 'numeric', 'min:0'],
            fn ($input) => $input->payment_method === Sale::PAYMENT_CASH,
        );

        $validator->sometimes(
            'qr_reference_note',
            ['required', 'string', 'max:255'],
            fn ($input) => $input->payment_method === Sale::PAYMENT_QR,
        );
    }
}
