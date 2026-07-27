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

class AddNodeTool implements Tool
{
    public function __construct(public WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'add_node';
    }

    public function description(): Stringable|string
    {
        return 'Add a new step to the workflow draft. The key must be unique within the draft. '
            .'Use inspect_node_schema first to know what "config" fields the chosen type expects.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();
        $configJson = $arguments['config_json'] ?? null;

        try {
            $config = $configJson ? json_decode((string) $configJson, true, flags: JSON_THROW_ON_ERROR) : [];
        } catch (Throwable) {
            return 'config_json must be a valid JSON object string.';
        }

        try {
            $this->session->addStep(
                key: (string) $arguments['key'],
                type: (string) $arguments['type'],
                config: $config,
                by: $this->session->user,
            );
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return "Added step [{$arguments['key']}].";
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('A unique, short identifier for this step, e.g. "send_email".')->required(),
            'type' => $schema->string()->description('The node step type, from list_available_nodes.')->required(),
            'config_json' => $schema->string()->description('The step config as a JSON object string, matching the node\'s config_schema.'),
        ];
    }
}
