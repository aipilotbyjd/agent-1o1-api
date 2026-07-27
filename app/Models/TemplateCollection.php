<?php

namespace App\Models;

use Database\Factories\TemplateCollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[Fillable(['name', 'slug', 'description', 'icon', 'color', 'template_ids', 'is_active', 'sort_order'])]
class TemplateCollection extends Model
{
    /** @use HasFactory<TemplateCollectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_ids' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return Collection<int, WorkflowTemplate>
     */
    public function templates(): Collection
    {
        return WorkflowTemplate::query()->whereIn('id', $this->template_ids ?? [])->get();
    }
}
