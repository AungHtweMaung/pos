<?php

namespace App\Http\Requests\Users;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'max:60', 'alpha_dash',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }
}
