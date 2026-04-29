<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasLat = ! is_null($this->input('lat'));
            $hasLng = ! is_null($this->input('lng'));

            if ($hasLat !== $hasLng) {
                $validator->errors()->add('lat', 'Os parâmetros lat e lng devem ser enviados juntos.');
            }
        });
    }
}
