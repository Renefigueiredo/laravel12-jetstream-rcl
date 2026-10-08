<?php

namespace App\Livewire\Forms;

use App\Services\ExcludedCodes\OperationCode;
use Livewire\Form;

class ExcludedCodeForm extends Form
{
    public string $code = '';

    public string $description = '';

    /**
     * Remove the surrounding spaces before validating, as the list compares codes without them.
     */
    public function normalize(): void
    {
        $this->code = OperationCode::normalize($this->code) ?? '';
        $this->description = OperationCode::normalize($this->description) ?? '';
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{1,'.OperationCode::MAX_LENGTH.'}\z/'],
            'description' => ['nullable', 'string', 'max:'.OperationCode::DESCRIPTION_MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => __('conciliation.excluded_codes.errors.code_required'),
            'code.regex' => __('conciliation.excluded_codes.errors.code_invalid'),
            'description.max' => __('conciliation.excluded_codes.errors.description_max'),
        ];
    }
}
