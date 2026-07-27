<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class UpdateNodeTool implements Tool
{
    public function __construct(public WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'update_node';
    }

    public function description(): Stringable|string
    {
        return 'Merge new values into an existing step\'s config, by key.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();
        $configJson = (string) ($arguments['config_json'] ?? '{}');

        try {
            $config = json_decode($configJson, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return 'config_json must be a valid JSON object string.';
        }

        try {
            $this->session->updateStep(
                key: (string) $arguments['key'],
                config: $config,
                by: $this->session->user,
            );
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return "Updated step [{$arguments['key']}].";
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('The key of the step to update.')->required(),
            'config_json' => $schema->string()->description('The config fields to merge in, as a JSON object string.')->required(),
        ];
    }
}
