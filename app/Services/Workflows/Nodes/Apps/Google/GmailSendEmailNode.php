<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class GmailSendEmailNode extends HttpAppNode
{
    private const BASE_URL = 'https://gmail.googleapis.com/gmail/v1';

    public function type(): string
    {
        return 'gmail.send_email';
    }

    public function name(): string
    {
        return 'Gmail: Send Email';
    }

    public function description(): string
    {
        return 'Send an email via Gmail API.';
    }

    public function icon(): string
    {
        return 'mail';
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
        return 'https://developers.google.com/gmail/api/reference/rest/v1/users.messages/send';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'to', 'subject', 'body'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'to' => ['type' => 'string'],
                'subject' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'is_html' => ['type' => 'boolean'],
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

        $to = $config['to'];
        $subject = $config['subject'];
        $body = $config['body'];
        $isHtml = $config['is_html'] ?? false;
        $contentType = $isHtml ? 'text/html' : 'text/plain';

        $raw = base64_encode(
            "To: {$to}\r\n".
            "Subject: {$subject}\r\n".
            "Content-Type: {$contentType}; charset=utf-8\r\n\r\n".
            $body
        );

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->post(self::BASE_URL.'/users/me/messages/send', [
                'raw' => strtr($raw, '+/', '-_'),
            ]));

        return $data;
    }
}
