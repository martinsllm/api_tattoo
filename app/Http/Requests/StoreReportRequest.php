<?php

namespace App\Http\Requests;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Traits\ResolvesReportableClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReportRequest extends FormRequest
{
    use ResolvesReportableClass;

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
        $reportableType = $this->input('reportable_type');
        $reportableIdRules = ['required', 'integer'];

        if ($reportableType === 'artist_profile') {
            $reportableIdRules[] = Rule::exists('artist_profiles', 'id');
        } elseif ($reportableType === 'review') {
            $reportableIdRules[] = Rule::exists('reviews', 'id');
        }

        return [
            'reportable_id' => $reportableIdRules,
            'reportable_type' => ['required', Rule::in(['artist_profile', 'review'])],
            'reason' => ['required', 'string', Rule::in(ReportReason::values())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $id = $this->input('reportable_id');
        $type = $this->input('reportable_type');

        $validator->after(function (Validator $validator) use ($id, $type) {
            $modelType = $this->resolveReportableClass($type);

            if ($modelType === null) {
                return;
            }

            $alreadyReported = Report::query()
                ->where('reporter_id', $this->user()->id)
                ->where('reportable_id', $id)
                ->where('reportable_type', $modelType)
                ->where('status', ReportStatus::PENDING)
                ->exists();

            if ($alreadyReported) {
                $validator->errors()->add('reportable_id', 'The reportable item is already reported.');
            }
        });
    }

    public function prepareForValidation(): void
    {
        if ($this->filled('reason')) {
            $this->merge([
                'reason' => strtolower($this->input('reason')),
            ]);
        }
    }
}
