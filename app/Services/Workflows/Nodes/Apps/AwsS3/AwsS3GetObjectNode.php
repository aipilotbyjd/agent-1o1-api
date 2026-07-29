<?php

namespace App\Services\Workflows\Nodes\Apps\AwsS3;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Storage;

class AwsS3GetObjectNode extends AppNode
{
    public function type(): string
    {
        return 'aws_s3.get_object';
    }

    public function name(): string
    {
        return 'S3: Get Object';
    }

    public function description(): string
    {
        return 'Get the content of an S3 object.';
    }

    public function icon(): string
    {
        return 'download';
    }

    public function color(): string
    {
        return '#ff9900';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_API_KEY;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object', 'required' => ['credential_id', 'key'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'key' => ['type' => 'string'], 'bucket' => ['type' => 'string']],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['content' => ['type' => 'string']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        $disk = Storage::build(['driver' => 's3', 'key' => $credential->data['access_key_id'] ?? '', 'secret' => $credential->data['secret_access_key'] ?? '', 'region' => $credential->data['region'] ?? 'us-east-1', 'bucket' => $config['bucket'] ?? ($credential->data['bucket'] ?? '')]);
        if (! $disk->exists($config['key'])) {
            throw new \RuntimeException('S3 object not found: '.$config['key']);
        }
        $result = ['content' => $disk->get($config['key']), 'key' => $config['key']];
        $this->recordMetric($run, true, $startedAt);

        return $result;
    }
}
