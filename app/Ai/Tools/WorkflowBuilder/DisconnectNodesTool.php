<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DisconnectNodesTool implements Tool
{
    public function __construct(public WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'disconnect_nodes';
    }

    public function description(): Stringable|string
    {
        return 'Remove the edge between two steps, if one exists.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();
        $from = (string) $arguments['from'];
        $to = (string) $arguments['to'];

        $this->session->disconnect($from, $to, $this->session->user);

        return "Disconnected [{$from}] from [{$to}].";
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('The key of the edge\'s source step.')->required(),
            'to' => $schema->string()->description('The key of the edge\'s target step.')->required(),
        ];
    }
}
