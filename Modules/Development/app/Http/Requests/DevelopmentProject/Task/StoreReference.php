<?php

namespace Modules\Development\Http\Requests\DevelopmentProject\Task;

use App\Enums\Development\Project\ReferenceType;
use Illuminate\Foundation\Http\FormRequest;

class StoreReference extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
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
                },
            ],
            'references.*.link' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $type = request()->input("references.{$index}.type");

                    if ($type == ReferenceType::Link->value && empty($value)) {
                        $fail('The link field is required when type is link.');
                    }
                },
            ],
            'references.*.link_name' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $type = request()->input("references.{$index}.type");

                    if ($type == ReferenceType::Link->value && empty($value)) {
                        $fail('The link name field is required when type is link.');
                    }
                },
            ],
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
