<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Ownership is enforced by ProjectPolicy through the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every field is optional so a client can send a partial update, but any
     * field that is sent must still be valid — `name` may not be blanked out.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'required', Rule::enum(ProjectStatus::class)],
        ];
    }
}
