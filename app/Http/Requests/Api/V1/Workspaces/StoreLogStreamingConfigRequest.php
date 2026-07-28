<?php

namespace App\Http\Requests\Api\V1\Workspaces;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLogStreamingConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkspaceUpdate->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'destination' => ['required', 'string', Rule::in(['webhook', 'datadog', 'elasticsearch'])],
            'endpoint' => ['required', 'url', 'max:2048'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'headers.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
