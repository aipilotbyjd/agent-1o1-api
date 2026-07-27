<?php

namespace App\Http\Requests\Api\V1\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentEmbeddingRequest extends FormRequest
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
            'collection' => ['sometimes', 'string', 'max:255'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'chunk_text' => ['required', 'string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
