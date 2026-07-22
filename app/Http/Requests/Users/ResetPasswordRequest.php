<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
