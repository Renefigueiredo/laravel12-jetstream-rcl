<?php

namespace App\Livewire\Forms;

use App\Models\ReconciliationSettings;
use Livewire\Form;

class ReconciliationSettingsForm extends Form
{
    public string $toleranceAmount = '';

    public string $tolerancePercent = '';

    public string $toleranceCap = '';

    public string $surchargeCapPercent = '';

    public function fillFrom(ReconciliationSettings $settings): void
    {
        $this->toleranceAmount = $this->decimal($settings->tolerance_cents, 2);
        $this->tolerancePercent = $settings->tolerance_basis_points === null ? '' : $this->decimal($settings->tolerance_basis_points);
        $this->toleranceCap = $settings->tolerance_cap_cents === null ? '' : $this->decimal($settings->tolerance_cap_cents, 2);
        $this->surchargeCapPercent = $this->decimal($settings->surcharge_cap_basis_points);
    }

    public function normalize(): void
    {
        foreach (['toleranceAmount', 'tolerancePercent', 'toleranceCap', 'surchargeCapPercent'] as $field) {
            $this->{$field} = trim(str_replace(['R$', '%', ' '], '', $this->{$field}));
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $money = 'regex:/\A\d{1,3}(\.?\d{3})*(,\d{1,2})?\z/';
        $percent = 'regex:/\A\d{1,3}(,\d{1,2})?\z/';

        return [
            'toleranceAmount' => ['required', 'string', $money],
            'tolerancePercent' => ['nullable', 'string', $percent],
            'toleranceCap' => ['nullable', 'string', $money],
            'surchargeCapPercent' => ['required', 'string', $percent],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'toleranceAmount.required' => __('conciliation.settings.errors.tolerance_amount'),
            'toleranceAmount.regex' => __('conciliation.settings.errors.tolerance_amount'),
            'tolerancePercent.regex' => __('conciliation.settings.errors.tolerance_percent'),
            'toleranceCap.regex' => __('conciliation.settings.errors.tolerance_cap'),
            'surchargeCapPercent.required' => __('conciliation.settings.errors.surcharge_cap'),
            'surchargeCapPercent.regex' => __('conciliation.settings.errors.surcharge_cap'),
        ];
    }

    public function toleranceCents(): int
    {
        return $this->hundredths($this->toleranceAmount);
    }

    public function toleranceBasisPoints(): ?int
    {
        return $this->tolerancePercent === '' ? null : $this->hundredths($this->tolerancePercent);
    }

    public function toleranceCapCents(): ?int
    {
        return $this->toleranceCap === '' ? null : $this->hundredths($this->toleranceCap);
    }

    public function surchargeCapBasisPoints(): int
    {
        return $this->hundredths($this->surchargeCapPercent);
    }

    /**
     * Reais to cents and percent to basis points are the same conversion, done without floats.
     */
    protected function hundredths(string $value): int
    {
        [$whole, $fraction] = array_pad(explode(',', str_replace('.', '', $value), 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    protected function decimal(int $hundredths, int $minimumDecimals = 0): string
    {
        $text = number_format($hundredths / 100, 2, ',', '.');

        return $minimumDecimals === 2 ? $text : rtrim(rtrim($text, '0'), ',');
    }
}
