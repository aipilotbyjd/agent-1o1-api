<?php

namespace App\Http\Requests\Api\V1\Billing;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PackCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::BillingManage->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pack_key' => ['required', 'string', Rule::in(array_keys(config('billing.packs')))],
        ];
    }
}
