<?php

namespace App\Models\Credentials;

use Database\Factories\Credentials\CredentialTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'name', 'description', 'auth_type', 'color', 'icon', 'docs_url', 'fields', 'is_active', 'sort_order'])]
class CredentialType extends Model
{
    /** @use HasFactory<CredentialTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
