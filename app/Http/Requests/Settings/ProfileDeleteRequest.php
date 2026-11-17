<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }

    /**
     * Prevent the last manager of a team from deleting their own account.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $user = $this->user();

                if ($user->role->isManagerOrAbove()
                    && $user->team_id !== null
                    && ! User::teamHasManager($user->team_id, excluding: $user->id)) {
                    $validator->errors()->add('password', __('You are the only manager of your team, so your account cannot be deleted.'));
                }
            },
        ];
    }
}
