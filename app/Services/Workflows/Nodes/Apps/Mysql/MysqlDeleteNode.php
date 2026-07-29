<?php

namespace App\Services\Workflows\Nodes\Apps\Mysql;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class MysqlDeleteNode extends AppNode
{
    public function type(): string
    {
        return 'mysql.delete';
    }

    public function name(): string
    {
        return 'MySQL: Delete Rows';
    }

    public function description(): string
    {
        return 'Delete rows from a MySQL table.';
    }

    public function icon(): string
    {
        return 'database';
    }

    public function color(): string
    {
        return '#4479a1';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BASIC_AUTH;
    }

    public function docsUrl(): ?string
    {
        return 'https://dev.mysql.com/doc/';
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => ['table' => ['type' => 'string'], 'where' => ['type' => 'object']], 'required' => ['table', 'where']];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['affected_rows' => ['type' => 'integer']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        try {
            $db = $this->connect($credential);
            $affected = $db->table($config['table'])->where($config['where'] ?? [])->delete();
            $this->recordMetric($run, true, $startedAt);

            return ['affected_rows' => $affected];
        } catch (\Throwable $e) {
            $this->recordMetric($run, false, $startedAt);
            throw $e;
        }
    }

    private function connect(Credential $credential): Connection
    {
        $name = 'engine_mysql_'.md5(json_encode($credential->data));
        config(["database.connections.{$name}" => ['driver' => 'mysql', 'host' => $credential->data['host'] ?? 'localhost', 'port' => (int) ($credential->data['port'] ?? 3306), 'database' => $credential->data['database'] ?? '', 'username' => $credential->data['username'] ?? '', 'password' => $credential->data['password'] ?? '']]);

        return DB::connection($name);
    }
}
