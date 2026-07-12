<?php

namespace App\Http\Requests\User;

use App\Domain\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(6)],
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
