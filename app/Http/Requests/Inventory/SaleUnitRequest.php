<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $saleUnitId = $this->route('saleUnit')?->id;

        return [
            'label' => ['required', 'string', 'max:255'],
            'barcode' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sale_units', 'barcode')->ignore($saleUnitId),
            ],
            'pack_size' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['required', 'numeric', 'min:0'],
        ];
    }
}
