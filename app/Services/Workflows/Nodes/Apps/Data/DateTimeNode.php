<?php

namespace App\Services\Workflows\Nodes\Apps\Data;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use Carbon\Carbon;

class DateTimeNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'datetime.utility';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'Date/Time Utilities';
    }

    public function description(): string
    {
        return 'Manipulate dates and times: format, add, subtract, diff, and more.';
    }

    public function category(): string
    {
        return 'data';
    }

    public function icon(): string
    {
        return 'clock';
    }

    public function color(): string
    {
        return '#0ea5e9';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['operation'],
            'properties' => [
                'operation' => ['type' => 'string'],
                'date' => ['type' => 'string'],
                'timezone' => ['type' => 'string'],
                'format' => ['type' => 'string'],
                'unit' => ['type' => 'string'],
                'amount' => ['type' => 'integer'],
                'other_date' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object'];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $operation = $config['operation'] ?? 'default';
        $timezone = $config['timezone'] ?? 'UTC';

        $date = isset($config['date'])
            ? Carbon::parse($config['date'], $timezone)
            : Carbon::now($timezone);

        return match ($operation) {
            'now' => ['result' => $date->toISOString()],
            'format' => ['result' => $date->format($config['format'] ?? 'Y-m-d H:i:s')],
            'add' => ['result' => $date->add($config['unit'] ?? 'days', (int) ($config['amount'] ?? 1))->toISOString()],
            'subtract' => ['result' => $date->sub($config['unit'] ?? 'days', (int) ($config['amount'] ?? 1))->toISOString()],
            'diff' => $this->diff($date, $config),
            'start_of' => ['result' => $date->startOf($config['unit'] ?? 'day')->toISOString()],
            'end_of' => ['result' => $date->endOf($config['unit'] ?? 'day')->toISOString()],
            'timestamp' => ['result' => $date->timestamp],
            default => throw new \RuntimeException("DateTime: unknown operation '{$operation}'"),
        };
    }

    private function diff(Carbon $date, array $config): array
    {
        $other = Carbon::parse($config['other_date'] ?? now());
        $unit = $config['unit'] ?? 'seconds';

        $result = match ($unit) {
            'minutes' => $date->diffInMinutes($other),
            'hours' => $date->diffInHours($other),
            'days' => $date->diffInDays($other),
            default => $date->diffInSeconds($other),
        };

        return ['result' => $result, 'unit' => $unit];
    }
}
