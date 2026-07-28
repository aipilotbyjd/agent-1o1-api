<?php

namespace App\Models\Triggers;

use Database\Factories\Triggers\TriggerTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'category', 'key', 'name', 'description', 'mechanism',
    'signature_scheme', 'dedupe_header', 'dedupe_payload_path',
    'preset_config', 'fields', 'is_active', 'sort_order',
])]
class TriggerType extends Model
{
    /** @use HasFactory<TriggerTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preset_config' => 'array',
            'fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Merge the preset config under the user's own config values.
     *
     * @param  array<string, mixed>  $userConfig
     * @return array<string, mixed>
     */
    public function buildConfig(array $userConfig): array
    {
        return array_replace_recursive($this->preset_config ?? [], $userConfig);
    }
}
