<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create or edit a tariff. Admins enter pounds and GB; the Subscription Daemon stores pence and MB.
 *
 * On update every field is optional: a tariff with active lines has its price and allowances
 * frozen, so the form doesn't send them, and only what was sent is changed.
 */
class SaveTariffRequest extends FormRequest
{
    private const FLAGS = ['eu_roaming', 'international_roaming', 'five_g_plus', 'available'];

    protected function prepareForValidation(): void
    {
        // Checkboxes arrive as "1"/"0"/"on" (or not at all, which only counts as false when creating).
        foreach ([...self::FLAGS, 'minutes_unlimited', 'texts_unlimited', 'data_unlimited'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([$flag => filter_var($this->input($flag), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->route('tariff') === null;
        $present = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$present, 'string', 'min:2', 'max:60'],
            'description' => ['nullable', 'string', 'max:200'],
            'monthly_price' => [$present, 'numeric', 'min:0', 'max:1000', 'decimal:0,2'],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:100000', $creating ? 'required_unless:minutes_unlimited,true' : 'sometimes'],
            'texts' => ['nullable', 'integer', 'min:0', 'max:100000', $creating ? 'required_unless:texts_unlimited,true' : 'sometimes'],
            'data_gb' => ['nullable', 'numeric', 'min:0', 'max:10000', 'decimal:0,2', $creating ? 'required_unless:data_unlimited,true' : 'sometimes'],
            'minutes_unlimited' => ['sometimes', 'boolean'],
            'texts_unlimited' => ['sometimes', 'boolean'],
            'data_unlimited' => ['sometimes', 'boolean'],
            ...array_fill_keys(self::FLAGS, ['sometimes', 'boolean']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'minutes.required_unless' => 'Enter minutes, or tick unlimited.',
            'texts.required_unless' => 'Enter texts, or tick unlimited.',
            'data_gb.required_unless' => 'Enter a data allowance, or tick unlimited.',
        ];
    }

    /**
     * The fields to send to the Subscription Daemon: everything when creating, only what was submitted when editing.
     *
     * @return array<string, mixed>
     */
    public function tariffFields(): array
    {
        $creating = $this->route('tariff') === null;
        $data = $this->validated();
        $fields = [];

        if (array_key_exists('name', $data)) {
            $fields['name'] = trim((string) $data['name']);
        }

        if ($creating || $this->has('description')) {
            $fields['description'] = filled($data['description'] ?? null) ? trim((string) $data['description']) : null;
        }

        if (array_key_exists('monthly_price', $data)) {
            $fields['monthly_price_pence'] = (int) round((float) $data['monthly_price'] * 100);
        }

        $quantity = function (string $key, string $unlimitedKey, callable $convert) use ($data, $creating): array {
            if (! $creating && ! array_key_exists($key, $data) && ! array_key_exists($unlimitedKey, $data)) {
                return [];
            }

            return [($data[$unlimitedKey] ?? false) ? null : $convert($data[$key] ?? 0)];
        };

        foreach ([
            'minutes' => $quantity('minutes', 'minutes_unlimited', fn ($v) => (int) $v),
            'texts' => $quantity('texts', 'texts_unlimited', fn ($v) => (int) $v),
            'data_mb' => $quantity('data_gb', 'data_unlimited', fn ($v) => (int) round((float) $v * 1024)),
        ] as $key => $value) {
            if ($value !== []) {
                $fields[$key] = $value[0];
            }
        }

        foreach (self::FLAGS as $flag) {
            if ($creating || array_key_exists($flag, $data)) {
                $fields[$flag] = (bool) ($data[$flag] ?? ($flag === 'available'));
            }
        }

        return $fields;
    }
}
