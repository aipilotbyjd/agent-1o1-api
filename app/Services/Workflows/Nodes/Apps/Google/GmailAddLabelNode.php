<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class GmailAddLabelNode extends HttpAppNode
{
    private const BASE_URL = 'https://gmail.googleapis.com/gmail/v1';

    public function type(): string
    {
        return 'gmail.add_label';
    }

    public function name(): string
    {
        return 'Gmail: Add Label';
    }

    public function description(): string
    {
        return 'Add labels to a message.';
    }

    public function icon(): string
    {
        return 'tag';
    }

    public function color(): string
    {
        return '#ea4335';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://developers.google.com/gmail/api/reference/rest/v1/users.messages/modify';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'message_id', 'label_ids'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'message_id' => ['type' => 'string'],
                'label_ids' => ['type' => 'array'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object'];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->post(self::BASE_URL."/users/me/messages/{$config['message_id']}/modify", [
                'addLabelIds' => (array) $config['label_ids'],
                'removeLabelIds' => [],
            ]));

        return $data;
    }
}
