<?php

namespace App\Services\Workflows\Nodes\Apps\Mysql;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class MysqlRawQueryNode extends AppNode
{
    public function type(): string
    {
        return 'mysql.raw_query';
    }

    public function name(): string
    {
        return 'MySQL: Raw Query';
    }

    public function description(): string
    {
        return 'Execute a raw SQL query against MySQL.';
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
        return ['type' => 'object', 'properties' => ['query' => ['type' => 'string'], 'bindings' => ['type' => 'array', 'items' => ['type' => 'string']]], 'required' => ['query']];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['rows' => ['type' => 'array'], 'affected_rows' => ['type' => 'integer']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        try {
            $db = $this->connect($credential);
            $sql = $config['query'] ?? '';
            $bindings = $config['bindings'] ?? [];
            if (preg_match('/^\s*select/i', $sql)) {
                $result = ['rows' => $db->select($sql, $bindings)];
            } else {
                $result = ['affected_rows' => $db->affectingStatement($sql, $bindings)];
            }
            $this->recordMetric($run, true, $startedAt);

            return $result;
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
