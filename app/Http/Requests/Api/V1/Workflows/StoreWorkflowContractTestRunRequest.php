<?php

namespace App\Http\Requests\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowContractTestRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WorkflowContractTestRunManage->value);
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
