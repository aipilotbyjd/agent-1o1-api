<?php

namespace App\Services\Workflows\Nodes\Connectors;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Runs\ConnectorMetricRecorder;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class SlackPostMessageNode extends NodeDefinition implements ExecutableNode
{
    private const ENDPOINT = 'https://slack.com/api/chat.postMessage';

    public function type(): string
    {
        return 'slack.post_message';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'Slack: Post Message';
    }

    public function description(): string
    {
        return 'Post a message to a Slack channel using a bot token credential.';
    }

    public function category(): string
    {
        return 'actions';
    }

    public function icon(): string
    {
        return 'message-square';
    }

    public function color(): string
    {
        return '#4a154b';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://api.slack.com/methods/chat.postMessage';
    }

    /**
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'channel', 'text'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'channel' => ['type' => 'string'],
                'text' => ['type' => 'string'],
                'thread_ts' => ['type' => 'string'],
                'blocks' => ['type' => 'array'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ok' => ['type' => 'boolean'],
                'channel' => ['type' => 'string'],
                'ts' => ['type' => 'string'],
                'error' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(Run $run, array $config, array $context): array
    {
        $credential = Credential::query()
            ->where('workspace_id', $run->workspace_id)
            ->find($config['credential_id'] ?? null);

        if ($credential === null) {
            throw new InvalidArgumentException('This step references a credential that no longer exists.');
        }

        $payload = array_filter([
            'channel' => $config['channel'] ?? null,
            'text' => $config['text'] ?? null,
            'thread_ts' => $config['thread_ts'] ?? null,
            'blocks' => $config['blocks'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        $startedAt = microtime(true);

        $response = HttpRequestNode::applyCredential(Http::timeout(15), $credential)
            ->post(self::ENDPOINT, $payload);

        $body = $response->json() ?? [];

        // Slack answers 200 with `ok: false` for application errors, so the transport
        // status alone would report a failed post as a success.
        $ok = $response->successful() && ($body['ok'] ?? false) === true;

        app(ConnectorMetricRecorder::class)->record(
            $run->workspace_id,
            $this->type(),
            $ok,
            (int) round((microtime(true) - $startedAt) * 1000),
        );

        return [
            'ok' => $ok,
            'channel' => $body['channel'] ?? null,
            'ts' => $body['ts'] ?? null,
            'error' => $body['error'] ?? null,
        ];
    }
}
