<?php

namespace App\Http\Requests;

use App\Enums\BrazilianState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FilterArtistsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'styles' => ['nullable', 'array'],
            'styles.*' => ['integer', 'exists:styles,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', Rule::in(BrazilianState::values())],
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort' => ['nullable', 'string', 'in:rating,distance,newest'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasLat = ! is_null($this->input('lat'));
            $hasLng = ! is_null($this->input('lng'));

            if ($this->input('sort') === 'distance' && (! $hasLat || ! $hasLng)) {
                $validator->errors()->add('sort', 'O parâmetro sort deve ser enviado juntos com os parâmetros lat e lng.');
            }

            if ($hasLat !== $hasLng) {
                $validator->errors()->add('lat', 'Os parâmetros lat e lng devem ser enviados juntos.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('state')) {
            $this->merge([
                'state' => strtoupper($this->input('state')),
            ]);
        }
    }
}
