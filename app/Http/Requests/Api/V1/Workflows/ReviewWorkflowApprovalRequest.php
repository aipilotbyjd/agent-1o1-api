<?php

namespace App\Http\Requests\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;

class ReviewWorkflowApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkflowApprovalReview->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
