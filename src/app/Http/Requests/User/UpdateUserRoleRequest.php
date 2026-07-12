<?php

namespace App\Http\Requests\User;

use App\Domain\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => [
                'required',
                Rule::in([
                    UserRole::Cashier->value,
                    UserRole::Driver->value,
                ]),
            ],
        ];
    }
}
