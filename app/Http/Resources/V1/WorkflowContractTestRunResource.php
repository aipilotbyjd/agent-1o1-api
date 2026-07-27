<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowContractTestRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'status' => $this->status,
            'results' => $this->results,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
