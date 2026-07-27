<?php

namespace App\Http\Requests\Api\V1\Variables;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVariableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::VariableManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['sometimes', 'string'],
            'is_secret' => ['sometimes', 'boolean'],
        ];
    }
}
