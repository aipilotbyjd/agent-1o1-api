<?php

namespace App\Console\Commands\Workflows;

use App\Services\Workflows\Nodes\NodeCatalogSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('workflows:sync-node-catalog')]
#[Description('Project the registered node definitions into the nodes table')]
class SyncNodeCatalog extends Command
{
    public function handle(NodeCatalogSync $sync): int
    {
        $result = $sync->sync();

        $this->info("Synced {$result['nodes']} nodes across {$result['categories']} categories.");

        return self::SUCCESS;
    }
}
