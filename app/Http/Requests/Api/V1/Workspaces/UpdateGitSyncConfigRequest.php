<?php

namespace App\Http\Requests\Api\V1\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGitSyncConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasWorkspaceRole($this->route('workspace'), 'owner', 'admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'repository' => ['sometimes', 'string', 'max:255'],
            'branch' => ['sometimes', 'string', 'max:255'],
            'base_path' => ['sometimes', 'string', 'max:255'],
            'access_token' => ['sometimes', 'string'],
            'webhook_secret' => ['sometimes', 'nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
