<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RemoveNodeTool implements Tool
{
    public function __construct(public WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'remove_node';
    }

    public function description(): Stringable|string
    {
        return 'Remove a step from the draft by key, along with any edges connected to it.';
    }

    public function handle(Request $request): Stringable|string
    {
        $key = (string) ($request->all()['key'] ?? '');

        $this->session->removeStep($key, $this->session->user);

        return "Removed step [{$key}] and any edges connected to it.";
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('The key of the step to remove.')->required(),
        ];
    }
}
