<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Rules\ReservedTeamName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * When creating, the user may join an existing team (`team_id`) or, for a
     * manager or above, start a new one (`new_team_name`).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $editing = $user instanceof User;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($editing ? $user->id : null),
            ],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => [$editing ? 'nullable' : 'required', 'string', Password::default()],
            'team_id' => $editing
                ? ['required', Rule::exists(Team::class, 'id')->withoutTrashed()]
                : ['nullable', 'required_without:new_team_name', 'prohibits:new_team_name', Rule::exists(Team::class, 'id')->withoutTrashed()],
            'new_team_name' => $editing
                ? ['prohibited']
                : [
                    'nullable', 'required_without:team_id', 'string', 'max:255',
                    Rule::unique(Team::class, 'name')->withoutTrashed(),
                    new ReservedTeamName,
                ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
    }
}
