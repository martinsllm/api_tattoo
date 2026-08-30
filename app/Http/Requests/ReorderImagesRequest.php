<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderImagesRequest extends FormRequest
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
        $artist = $this->route('artist');

        return [
            'images_ids' => ['required', 'array', 'min:1'],
            'images_ids.*' => [
                'integer',
                Rule::exists('artist_images', 'id')->where('artist_profile_id', $artist->id),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $artist = $this->route('artist');
            $imagesIds = $this->input('images_ids', []);

            if (! is_array($imagesIds)) {
                return;
            }

            if (count($imagesIds) !== count(array_unique($imagesIds))) {
                $validator->errors()->add('images_ids', 'Os IDs das imagens não podem ser duplicados.');
            }

            if (count($imagesIds) !== $artist->images()->count()) {
                $validator->errors()->add('images_ids', 'O número de IDs das imagens não corresponde ao número de imagens do artista.');
            }
        });
    }
}
