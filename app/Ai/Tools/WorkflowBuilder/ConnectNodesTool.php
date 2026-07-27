<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\WorkflowBuilderSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ConnectNodesTool implements Tool
{
    public function __construct(public WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'connect_nodes';
    }

    public function description(): Stringable|string
    {
        return 'Draw an edge from one step to another. Both steps must already exist in the draft. '
            .'For a condition step, set "condition" to the branch value ("true"/"false") this edge follows.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();
        $from = (string) $arguments['from'];
        $to = (string) $arguments['to'];
        $condition = $arguments['condition'] ?? null;

        try {
            $this->session->connect($from, $to, $condition === null ? null : (string) $condition, $this->session->user);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return "Connected [{$from}] to [{$to}].";
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('The key of the step the edge starts at.')->required(),
            'to' => $schema->string()->description('The key of the step the edge points to.')->required(),
            'condition' => $schema->string()->description('Optional branch condition value this edge follows.'),
        ];
    }
}
