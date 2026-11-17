<?php

namespace App\Http\Requests;

use App\Services\Numbers\NumberDirectory;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CustomerSearchRequest extends FormRequest
{
    /** UK postcode, outward code ("M14") or outward + partial inward ("M14 5"). */
    public const POSTCODE_PATTERN = '/^[A-Za-z]{1,2}[0-9][A-Za-z0-9]?(\s*[0-9]([A-Za-z]{1,2})?)?$/';

    /**
     * Filter fields, in the order they are shown.
     */
    public const FILTERS = ['last_name', 'postcode', 'date_of_birth', 'account_number', 'msisdn'];

    public function authorize(): bool
    {
        return true; // Team membership is enforced by route middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:8', 'regex:'.self::POSTCODE_PATTERN],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'account_number' => ['nullable', 'digits:10'],
            'msisdn' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) {
                if (! NumberDirectory::isMobileNumber((string) $value)) {
                    $fail('Enter a UK mobile number, e.g. 07700 910123.');
                }
            }],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postcode.regex' => 'Enter a UK postcode or the first part of one, e.g. M14 or M14 5RG.',
            'account_number.digits' => 'Account numbers are 10 digits.',
        ];
    }

    public function searchTerm(): ?string
    {
        $q = trim((string) $this->validated('q'));

        return $q === '' ? null : $q;
    }

    /**
     * @return array{last_name?: string, postcode?: string, date_of_birth?: string, account_number?: string, msisdn?: string}
     */
    public function filters(): array
    {
        $filters = [];

        foreach (self::FILTERS as $key) {
            $value = trim((string) $this->validated($key));

            if ($value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    public function hasCriteria(): bool
    {
        return $this->searchTerm() !== null || $this->filters() !== [];
    }
}
