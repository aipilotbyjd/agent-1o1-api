<?php

namespace App\Http\Requests\Api\V1\Agents;

use Illuminate\Foundation\Http\FormRequest;

class ChatAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasWorkspaceRole($this->route('workspace'), 'owner', 'admin', 'member');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:20000'],
            'conversation_id' => ['nullable', 'string', 'max:36'],
        ];
    }
}
