<?php

namespace Database\Seeders;

use App\Services\Workflows\Nodes\NodeCatalogSync;
use Illuminate\Database\Seeder;

class NodeCatalogSeeder extends Seeder
{
    /**
     * The catalog is defined in code (see WorkflowServiceProvider::NODES) and projected
     * into the nodes table, so seeding is just a sync — there is no second hand-written
     * list here that could drift from what the engine can actually execute.
     */
    public function run(): void
    {
        app(NodeCatalogSync::class)->sync();
    }
}
