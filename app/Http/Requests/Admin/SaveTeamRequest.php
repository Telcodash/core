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

class SaveTeamRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Creating a team also creates its first manager, so no team is ever without one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');

        $rules = [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique(Team::class, 'name')
                    ->withoutTrashed()
                    ->ignore($team instanceof Team ? $team->id : null),
                new ReservedTeamName,
            ],
        ];

        if ($team === null) {
            $rules += [
                'manager_name' => ['required', 'string', 'max:255'],
                'manager_email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
                'manager_password' => ['required', 'string', Password::default()],
                'manager_role' => ['required', Rule::in([Role::Manager->value, Role::Administrator->value])],
            ];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->manager_email)) {
            $this->merge(['manager_email' => strtolower(trim($this->manager_email))]);
        }
    }
}
