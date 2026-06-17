<?php

namespace App\Http\Requests;

use App\Enums\BrazilianState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArtistRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'studio_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', Rule::in(BrazilianState::values())],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'styles' => ['array'],
            'styles.*' => ['exists:styles,id'],
            'tags' => ['array'],
            'tags.*' => ['exists:tags,id'],
        ];
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
