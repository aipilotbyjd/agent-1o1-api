<?php

namespace App\Http\Requests\Api\V1\Workflows;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowContractTestRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasWorkspaceRole($this->route('workspace'), 'owner', 'admin');
    }

    /**
     * No input fields: status/results are computed by ContractGenerator::diff(), not
     * client-supplied — see WorkflowContractTestRunController@store.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
