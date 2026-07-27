<?php

namespace App\Http\Requests\Api\V1\Workspaces;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreGitSyncConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::GitSyncManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['sometimes', 'string', 'max:30'],
            'repository' => ['required', 'string', 'max:255'],
            'branch' => ['sometimes', 'string', 'max:255'],
            'base_path' => ['sometimes', 'string', 'max:255'],
            'access_token' => ['required', 'string'],
            'webhook_secret' => ['sometimes', 'nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
