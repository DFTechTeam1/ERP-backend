<?php

namespace Modules\Production\Http\Requests\Interactive;

use App\Enums\Interactive\InteractiveProjectStatus;
use Illuminate\Foundation\Http\FormRequest;

class ChangeStatus extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $status = InteractiveProjectStatus::cases();
        $statusValue = array_map(fn ($s) => $s->value, $status);

        return [
            'status' => 'required|integer|in:'.implode(',', $statusValue),
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
