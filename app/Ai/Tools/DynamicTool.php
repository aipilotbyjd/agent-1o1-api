<?php

namespace App\Ai\Tools;

use App\Models\Runs\Run;
use App\Models\Tool as ToolModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class DynamicTool implements Tool
{
    public function __construct(
        public ToolModel $tool,
        public ?Run $run = null,
    ) {}

    public function name(): string
    {
        return str_replace('-', '_', $this->tool->slug);
    }

    public function description(): Stringable|string
    {
        return $this->tool->description;
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();

        $step = $this->run?->steps()->create([
            'key' => 'tool:'.$this->tool->slug,
            'type' => 'tool',
            'input' => $arguments,
        ]);

        $step?->markRunning();

        try {
            $result = app(ToolHandlerRegistry::class)->for($this->tool)->execute($this->tool, $arguments);
        } catch (Throwable $exception) {
            $step?->markFailed($exception->getMessage());

            return 'Tool execution failed: '.$exception->getMessage();
        }

        $step?->markCompleted(['result' => $result]);

        return $result;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $parameters = $this->tool->config['parameters'] ?? [];
        $types = [];

        foreach ($parameters as $parameter) {
            $type = match ($parameter['type'] ?? 'string') {
                'integer' => $schema->integer(),
                'number' => $schema->number(),
                'boolean' => $schema->boolean(),
                default => $schema->string(),
            };

            $type = $type->description($parameter['description'] ?? '');

            if ($parameter['required'] ?? false) {
                $type = $type->required();
            }

            $types[$parameter['name']] = $type;
        }

        return $types;
    }
}
