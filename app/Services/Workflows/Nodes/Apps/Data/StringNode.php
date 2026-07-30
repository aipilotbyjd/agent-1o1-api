<?php

namespace App\Services\Workflows\Nodes\Apps\Data;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use Illuminate\Support\Str;
use RuntimeException;

class StringNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'string.utility';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'String Utilities';
    }

    public function description(): string
    {
        return 'Manipulate strings: case conversion, trimming, splitting, replacing, and more.';
    }

    public function category(): string
    {
        return 'data';
    }

    public function icon(): string
    {
        return 'type';
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
                'text' => ['type' => 'string'],
                'separator' => ['type' => 'string'],
                'search' => ['type' => 'string'],
                'replace' => ['type' => 'string'],
                'start' => ['type' => 'integer'],
                'length' => ['type' => 'integer'],
                'with' => ['type' => 'string'],
                'pad' => ['type' => 'string'],
                'pattern' => ['type' => 'string'],
                'preserve_ascii' => ['type' => 'boolean'],
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
        $text = (string) ($config['text'] ?? '');

        return match ($operation) {
            'uppercase' => ['result' => mb_strtoupper($text)],
            'lowercase' => ['result' => mb_strtolower($text)],
            'title_case' => ['result' => Str::title($text)],
            'trim' => ['result' => trim($text)],
            'length' => ['result' => mb_strlen($text)],
            'split' => ['result' => explode($config['separator'] ?? ',', $text)],
            'replace' => ['result' => str_replace($config['search'] ?? '', $config['replace'] ?? '', $text)],
            'substring' => ['result' => mb_substr($text, (int) ($config['start'] ?? 0), isset($config['length']) ? (int) $config['length'] : null)],
            'contains' => ['result' => str_contains($text, (string) ($config['search'] ?? ''))],
            'slug' => ['result' => Str::slug($text)],
            'camel' => ['result' => Str::camel($text)],
            'snake' => ['result' => Str::snake($text)],
            'concat' => ['result' => $text.($config['with'] ?? '')],
            'pad' => ['result' => str_pad($text, (int) ($config['length'] ?? 0), $config['pad'] ?? ' ')],
            'regex_match' => $this->regexMatch($text, $config),
            'regex_replace' => ['result' => preg_replace($config['pattern'] ?? '//', $config['replace'] ?? '', $text)],
            default => throw new RuntimeException("String: unknown operation '{$operation}'"),
        };
    }

    private function regexMatch(string $text, array $config): array
    {
        preg_match_all($config['pattern'] ?? '//', $text, $matches);

        return ['result' => $matches[0] ?? [], 'groups' => $matches];
    }
}
