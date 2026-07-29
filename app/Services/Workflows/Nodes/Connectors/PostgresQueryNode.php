<?php

namespace App\Services\Workflows\Nodes\Connectors;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Http\OutboundUrlGuard;
use App\Services\Runs\ConnectorMetricRecorder;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostgresQueryNode extends NodeDefinition implements ExecutableNode
{
    /**
     * A run-scoped connection name, so two concurrent steps never share config.
     */
    private const CONNECTION_PREFIX = 'workflow_pgsql_';

    public function type(): string
    {
        return 'postgres.query';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'Postgres: Query';
    }

    public function description(): string
    {
        return 'Run a parameterised SQL query against an external Postgres database.';
    }

    public function category(): string
    {
        return 'data';
    }

    public function icon(): string
    {
        return 'database';
    }

    public function color(): string
    {
        return '#10b981';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_DATABASE;
    }

    /**
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'query'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'query' => ['type' => 'string'],
                'bindings' => ['type' => 'array'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'rows' => ['type' => 'array'],
                'count' => ['type' => 'integer'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(Run $run, array $config, array $context): array
    {
        $credential = Credential::query()
            ->where('workspace_id', $run->workspace_id)
            ->find($config['credential_id'] ?? null);

        if ($credential === null) {
            throw new InvalidArgumentException('This step references a credential that no longer exists.');
        }

        if ($credential->isExpired()) {
            throw new InvalidArgumentException("Credential [{$credential->name}] has expired.");
        }

        $query = trim((string) ($config['query'] ?? ''));

        if ($query === '') {
            throw new InvalidArgumentException('This step has no query to run.');
        }

        $data = $credential->data ?? [];
        $host = (string) ($data['host'] ?? '');

        // The same reasoning as outbound HTTP: a workspace-supplied host must not be
        // able to point the application at its own internal network.
        app(OutboundUrlGuard::class)->assertAllowed('tcp://'.$host);

        $credential->touchLastUsed();

        $connection = self::CONNECTION_PREFIX.$run->id;

        Config::set("database.connections.{$connection}", [
            'driver' => 'pgsql',
            'host' => $host,
            'port' => (int) ($data['port'] ?? 5432),
            'database' => (string) ($data['database'] ?? ''),
            'username' => (string) ($data['username'] ?? ''),
            'password' => (string) ($data['password'] ?? ''),
            'charset' => 'utf8',
            'sslmode' => 'prefer',
        ]);

        $startedAt = microtime(true);

        try {
            $rows = DB::connection($connection)->select($query, $config['bindings'] ?? []);
        } finally {
            DB::purge($connection);
            Config::set("database.connections.{$connection}", null);
        }

        app(ConnectorMetricRecorder::class)->record(
            $run->workspace_id,
            $this->type(),
            true,
            (int) round((microtime(true) - $startedAt) * 1000),
        );

        $rows = array_map(fn (object $row): array => (array) $row, $rows);

        return ['rows' => $rows, 'count' => count($rows)];
    }
}
