<?php

namespace App\Ai\Tools\WorkflowBuilder;

use App\Models\Workflows\WorkflowBuilderSession;
use App\Services\Workflows\DryRunner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class DryRunWorkflowTool implements Tool
{
    public function __construct(public WorkflowBuilderSession $session) {}

    public function name(): string
    {
        return 'dry_run_workflow';
    }

    public function description(): Stringable|string
    {
        return 'Simulate the draft end to end without calling any external service. Returns the order steps would run in, each step\'s resolved config, and any template that points at data nothing provides. Use this to check your wiring before telling the user it works.';
    }

    public function handle(Request $request): Stringable|string
    {
        $inputJson = (string) ($request->all()['sample_input_json'] ?? '{}');

        try {
            $input = json_decode($inputJson, true, flags: JSON_THROW_ON_ERROR) ?: [];
        } catch (Throwable) {
            return 'sample_input_json must be a valid JSON object string.';
        }

        $result = app(DryRunner::class)->run(
            $this->session->draft_graph ?? ['steps' => [], 'edges' => []],
            $input,
        );

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sample_input_json' => $schema->string()
                ->description('Example trigger input as a JSON object string, e.g. {"email":"a@b.com"}.'),
        ];
    }
}
