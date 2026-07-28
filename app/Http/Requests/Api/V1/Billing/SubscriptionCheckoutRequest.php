<?php

namespace App\Http\Requests\Api\V1\Billing;

use App\Enums\Workspaces\Permission;
use Illuminate\Foundation\Http\FormRequest;

class SubscriptionCheckoutRequest extends FormRequest
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
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'interval' => ['required', 'string', 'in:monthly,yearly'],
        ];
    }
}
