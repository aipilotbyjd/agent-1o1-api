<?php

namespace App\Services\Workflows\Nodes\Apps\Mysql;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class MysqlSelectNode extends AppNode
{
    public function type(): string
    {
        return 'mysql.select';
    }

    public function name(): string
    {
        return 'MySQL: Select Rows';
    }

    public function description(): string
    {
        return 'Select rows from a MySQL table with optional where filter and limit.';
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
        return ['type' => 'object', 'properties' => ['table' => ['type' => 'string'], 'where' => ['type' => 'object'], 'limit' => ['type' => 'integer', 'default' => 100]], 'required' => ['table']];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['rows' => ['type' => 'array'], 'count' => ['type' => 'integer']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        try {
            $db = $this->connect($credential);
            $rows = $db->table($config['table'])->when($config['where'] ?? null, fn ($q, $w) => $q->where($w))->limit((int) ($config['limit'] ?? 100))->get();
            $this->recordMetric($run, true, $startedAt);

            return ['rows' => $rows->toArray(), 'count' => $rows->count()];
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
