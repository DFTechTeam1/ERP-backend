<?php

namespace Modules\Development\Http\Requests\DevelopmentProject;

use App\Enums\Development\Project\ReferenceType;
use Illuminate\Foundation\Http\FormRequest;

class Create extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable',
            'references' => 'nullable|array',
            'references.*.type' => 'nullable|string',
            'references.*.image' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $type = request()->input("references.{$index}.type");

                    if ($type == ReferenceType::Media->value && empty($value)) {
                        $fail('The image field is required when type is media.');
                    }
                }
            ],
            'references.*.link' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $type = request()->input("references.{$index}.type");

                    if ($type == ReferenceType::Link->value && empty($value)) {
                        $fail('The link field is required when type is link.');
                    }
                }
            ],
            'pics' => 'nullable|array',
            'pics.*.employee_id' => 'nullable|string',
            'project_date' => 'required|date_format:Y-m-d',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
