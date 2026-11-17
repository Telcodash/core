<?php

namespace App\Http\Requests\Team;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveTeamMemberRequest extends FormRequest
{
    /**
     * Administrators manage everyone. Managers may only edit advisors and advisor pluses.
     * (Creating and deleting are restricted to administrators by route middleware.)
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $member = $this->route('member');

        if ($user->hasRole(Role::Administrator)) {
            return true;
        }

        return $member instanceof User
            && in_array($member->role, Role::managerAssignable(), true);
    }

    private function isAdministrator(): bool
    {
        return $this->user()->hasRole(Role::Administrator);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $member = $this->route('member');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($member instanceof User ? $member->id : null),
            ],
            'role' => [
                'required',
                $this->isAdministrator()
                    ? Rule::enum(Role::class)
                    : Rule::in(array_map(fn (Role $role) => $role->value, Role::managerAssignable())),
            ],
            'password' => $this->isAdministrator()
                ? [$member ? 'nullable' : 'required', 'string', Password::default()]
                : ['prohibited'],
        ];
    }

    /**
     * Normalise the email the same way login does.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
    }
}
