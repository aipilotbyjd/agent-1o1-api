<?php

namespace App\Ai\Tools;

use App\Enums\Runs\RunStatus;
use App\Models\Agents\Agent as AgentModel;
use App\Models\Nodes\Node;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\NodeResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema as JsonSchemaContract;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Exposes any executable node to an agent as a callable tool.
 *
 * This is what makes the connector catalog and the agent's toolbelt the same thing: a
 * "tool" is a node with part of its config already bound, so the model only ever fills
 * the fields the attachment left open. Credentials and other fixed values live on the
 * pivot and are merged in after the model's arguments, so they cannot be overridden.
 */
class NodeTool implements Tool
{
    public function __construct(
        public Node $node,
        public AgentModel $agent,
        public ?Run $run = null,
    ) {}

    /**
     * Tool names reach the provider as identifiers, so the dotted catalog type is
     * flattened — `slack.post_message` becomes `slack_post_message`.
     */
    public function name(): string
    {
        return str_replace(['.', '-'], '_', $this->node->type);
    }

    public function description(): Stringable|string
    {
        return (string) $this->node->description;
    }

    public function handle(Request $request): Stringable|string
    {
        $config = [...$this->argumentsFrom($request->all()), ...$this->boundConfig()];
        $run = $this->run();

        $step = $run->steps()->create([
            'key' => 'node:'.$this->node->type,
            'type' => 'tool',
            'input' => $config,
        ]);

        $step->markRunning();

        try {
            $result = app(NodeResolver::class)
                ->executable($this->node->type)
                ->execute($run, $config, []);
        } catch (Throwable $exception) {
            $step->markFailed($exception->getMessage());

            return 'Tool execution failed: '.$exception->getMessage();
        }

        $step->markCompleted($result);

        // The model needs text, so the structured result is encoded here rather than in
        // the node — a workflow step consumes the very same result as a real array.
        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    /**
     * Present the node's unbound config fields as the tool's parameters.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchemaContract $schema): array
    {
        $configSchema = $this->node->config_schema ?? [];
        $required = $configSchema['required'] ?? [];
        $types = [];

        foreach ($configSchema['properties'] ?? [] as $name => $definition) {
            if (! $this->isModelFillable((string) $name)) {
                continue;
            }

            // A field whose schema has no JSON Schema equivalent is dropped rather than
            // thrown, so one malformed property cannot take down every tool the agent has.
            try {
                $type = JsonSchema::fromArray($definition);
            } catch (InvalidArgumentException) {
                continue;
            }

            $types[$name] = in_array($name, $required, true) ? $type->required() : $type;
        }

        return $types;
    }

    /**
     * Values fixed when the node was attached to the agent.
     *
     * @return array<string, mixed>
     */
    private function boundConfig(): array
    {
        return $this->node->pivot?->config ?? [];
    }

    /**
     * Drop anything the model supplied that it was not offered, so a hallucinated key
     * can never reach a connector's config.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function argumentsFrom(array $arguments): array
    {
        return array_filter(
            $arguments,
            fn (string $name): bool => $this->isModelFillable($name),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function isModelFillable(string $field): bool
    {
        // A credential is chosen by whoever attached the node, never by the model.
        if ($field === 'credential_id') {
            return false;
        }

        if (array_key_exists($field, $this->boundConfig())) {
            return false;
        }

        $exposed = $this->node->pivot?->exposed_fields;

        return $exposed === null || in_array($field, $exposed, true);
    }

    /**
     * Agent chats normally arrive with a run to record against. One is created here for
     * the paths that do not have one, so a tool call is never invisible.
     */
    private function run(): Run
    {
        return $this->run ??= Run::create([
            'workspace_id' => $this->agent->workspace_id,
            'runnable_type' => $this->agent->getMorphClass(),
            'runnable_id' => $this->agent->id,
            'agent_version' => $this->agent->currentVersionNumber(),
            'status' => RunStatus::Running,
            'trigger_type' => 'agent',
            'started_at' => now(),
        ]);
    }
}
