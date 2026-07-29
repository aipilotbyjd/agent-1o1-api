<?php

namespace App\Services\Workflows\Nodes\Apps\AwsS3;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Storage;

class AwsS3ListObjectsNode extends AppNode
{
    public function type(): string
    {
        return 'aws_s3.list_objects';
    }

    public function name(): string
    {
        return 'S3: List Objects';
    }

    public function description(): string
    {
        return 'List objects in an S3 bucket.';
    }

    public function icon(): string
    {
        return 'folder';
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
            'type' => 'object', 'required' => ['credential_id'],
            'properties' => ['credential_id' => ['type' => 'integer'], 'prefix' => ['type' => 'string'], 'bucket' => ['type' => 'string']],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['files' => ['type' => 'array']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        $disk = Storage::build(['driver' => 's3', 'key' => $credential->data['access_key_id'] ?? '', 'secret' => $credential->data['secret_access_key'] ?? '', 'region' => $credential->data['region'] ?? 'us-east-1', 'bucket' => $config['bucket'] ?? ($credential->data['bucket'] ?? '')]);
        $result = ['files' => $disk->files($config['prefix'] ?? '')];
        $this->recordMetric($run, true, $startedAt);

        return $result;
    }
}
