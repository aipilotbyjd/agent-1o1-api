<?php

namespace App\Services\Workflows\Nodes\Apps\Mail;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class EmailSendNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'email.send';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'Send Email';
    }

    public function description(): string
    {
        return 'Send a plain-text email through the application mailer.';
    }

    public function category(): string
    {
        return 'actions';
    }

    public function icon(): string
    {
        return 'mail';
    }

    /**
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['to', 'subject', 'body'],
            'properties' => [
                'to' => ['type' => 'string'],
                'cc' => ['type' => 'string'],
                'subject' => ['type' => 'string'],
                'body' => ['type' => 'string'],
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
                'sent' => ['type' => 'boolean'],
                'to' => ['type' => 'string'],
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
        $to = (string) ($config['to'] ?? '');

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("[{$to}] is not a valid email address.");
        }

        $subject = (string) ($config['subject'] ?? '');
        $body = (string) ($config['body'] ?? '');
        $cc = $config['cc'] ?? null;

        Mail::raw($body, function (Message $message) use ($to, $cc, $subject): void {
            $message->to($to)->subject($subject);

            if (is_string($cc) && $cc !== '') {
                $message->cc($cc);
            }
        });

        return ['sent' => true, 'to' => $to];
    }
}
