<?php

namespace App\Http\Requests\Api\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['required', 'string', 'max:20000'],
            'provider' => ['sometimes', 'string', Rule::in(['openai', 'anthropic', 'gemini', 'azure', 'groq', 'xai', 'deepseek', 'mistral', 'ollama', 'openrouter'])],
            'model' => ['nullable', 'string', 'max:100'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
