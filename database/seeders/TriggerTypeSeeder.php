<?php

namespace Database\Seeders;

use App\Models\TriggerType;
use Illuminate\Database\Seeder;

class TriggerTypeSeeder extends Seeder
{
    /**
     * Seed the trigger type catalog. Every entry maps onto one of the core
     * mechanisms (webhook or schedule); provider entries are webhooks with
     * preset event filters.
     */
    public function run(): void
    {
        $sort = 0;

        foreach ($this->catalog() as $type) {
            TriggerType::updateOrCreate(
                ['key' => $type['key']],
                [...$type, 'is_active' => true, 'sort_order' => $sort++],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            // Schedule presets
            ['category' => 'schedule', 'key' => 'schedule.hourly', 'name' => 'Every Hour', 'mechanism' => 'schedule',
                'description' => 'Runs at the start of every hour.',
                'preset_config' => ['cron' => '0 * * * *'], 'fields' => []],
            ['category' => 'schedule', 'key' => 'schedule.daily', 'name' => 'Daily at Specific Time', 'mechanism' => 'schedule',
                'description' => 'Runs once a day. Override the cron to change the time.',
                'preset_config' => ['cron' => '0 9 * * *'],
                'fields' => [['name' => 'cron', 'label' => 'Cron expression', 'type' => 'string', 'required' => false]]],
            ['category' => 'schedule', 'key' => 'schedule.weekly', 'name' => 'Weekly on Specific Day', 'mechanism' => 'schedule',
                'description' => 'Runs once a week. Defaults to Monday 09:00.',
                'preset_config' => ['cron' => '0 9 * * 1'],
                'fields' => [['name' => 'cron', 'label' => 'Cron expression', 'type' => 'string', 'required' => false]]],
            ['category' => 'schedule', 'key' => 'schedule.monthly', 'name' => 'Monthly on Specific Date', 'mechanism' => 'schedule',
                'description' => 'Runs once a month. Defaults to the 1st at 09:00.',
                'preset_config' => ['cron' => '0 9 1 * *'],
                'fields' => [['name' => 'cron', 'label' => 'Cron expression', 'type' => 'string', 'required' => false]]],
            ['category' => 'schedule', 'key' => 'schedule.cron', 'name' => 'Custom Cron Expression', 'mechanism' => 'schedule',
                'description' => 'Full control over the schedule with a cron expression.',
                'preset_config' => [],
                'fields' => [['name' => 'cron', 'label' => 'Cron expression', 'type' => 'string', 'required' => true]]],

            // Manual — fired on demand from the trigger's Run button, no schedule or webhook.
            ['category' => 'manual', 'key' => 'manual.run', 'name' => 'Manual Run', 'mechanism' => 'manual',
                'description' => 'Fired on demand — no schedule or webhook required.',
                'preset_config' => [], 'fields' => []],

            // Generic webhook
            ['category' => 'webhook', 'key' => 'webhook.custom', 'name' => 'Custom Webhook', 'mechanism' => 'webhook',
                'description' => 'Fire on any POST to your secret hook URL.',
                'preset_config' => [], 'fields' => []],

            // GitHub — one hook URL, filtered by the X-GitHub-Event header. Signed with
            // the repository webhook's secret (X-Hub-Signature-256) and deduped by delivery id.
            ['category' => 'github', 'key' => 'github.push', 'name' => 'GitHub: On Push', 'mechanism' => 'webhook',
                'description' => 'Fires when commits are pushed. Point a GitHub repository webhook at your hook URL.',
                'signature_scheme' => 'github', 'dedupe_header' => 'X-GitHub-Delivery',
                'preset_config' => ['filters' => [['source' => 'header', 'path' => 'X-GitHub-Event', 'operator' => 'equals', 'value' => 'push']]], 'fields' => []],
            ['category' => 'github', 'key' => 'github.pull_request', 'name' => 'GitHub: On Pull Request', 'mechanism' => 'webhook',
                'description' => 'Fires on pull request activity.',
                'signature_scheme' => 'github', 'dedupe_header' => 'X-GitHub-Delivery',
                'preset_config' => ['filters' => [['source' => 'header', 'path' => 'X-GitHub-Event', 'operator' => 'equals', 'value' => 'pull_request']]], 'fields' => []],
            ['category' => 'github', 'key' => 'github.issue', 'name' => 'GitHub: On Issue', 'mechanism' => 'webhook',
                'description' => 'Fires on issue activity.',
                'signature_scheme' => 'github', 'dedupe_header' => 'X-GitHub-Delivery',
                'preset_config' => ['filters' => [['source' => 'header', 'path' => 'X-GitHub-Event', 'operator' => 'equals', 'value' => 'issues']]], 'fields' => []],
            ['category' => 'github', 'key' => 'github.release', 'name' => 'GitHub: On Release', 'mechanism' => 'webhook',
                'description' => 'Fires when a release is published.',
                'signature_scheme' => 'github', 'dedupe_header' => 'X-GitHub-Delivery',
                'preset_config' => ['filters' => [['source' => 'header', 'path' => 'X-GitHub-Event', 'operator' => 'equals', 'value' => 'release']]], 'fields' => []],

            // Slack — Events API posts JSON with an event.type field. Signed via
            // X-Slack-Signature; retries are deduped heuristically via X-Slack-Retry-Num.
            ['category' => 'slack', 'key' => 'slack.message', 'name' => 'Slack: On New Message', 'mechanism' => 'webhook',
                'description' => 'Fires on new channel messages via the Slack Events API.',
                'signature_scheme' => 'slack',
                'preset_config' => ['filters' => [['source' => 'payload', 'path' => 'event.type', 'operator' => 'equals', 'value' => 'message']]], 'fields' => []],
            ['category' => 'slack', 'key' => 'slack.app_mention', 'name' => 'Slack: On App Mention', 'mechanism' => 'webhook',
                'description' => 'Fires when your Slack app is mentioned.',
                'signature_scheme' => 'slack',
                'preset_config' => ['filters' => [['source' => 'payload', 'path' => 'event.type', 'operator' => 'equals', 'value' => 'app_mention']]], 'fields' => []],
            ['category' => 'slack', 'key' => 'slack.reaction_added', 'name' => 'Slack: On Reaction Added', 'mechanism' => 'webhook',
                'description' => 'Fires when a reaction is added to a message.',
                'signature_scheme' => 'slack',
                'preset_config' => ['filters' => [['source' => 'payload', 'path' => 'event.type', 'operator' => 'equals', 'value' => 'reaction_added']]], 'fields' => []],

            // Stripe — event payloads carry a top-level type field. Signed via
            // Stripe-Signature; deduped by the event payload's own id.
            ['category' => 'stripe', 'key' => 'stripe.charge_succeeded', 'name' => 'Stripe: On Charge Succeeded', 'mechanism' => 'webhook',
                'description' => 'Fires when a charge succeeds.',
                'signature_scheme' => 'stripe', 'dedupe_payload_path' => 'id',
                'preset_config' => ['filters' => [['source' => 'payload', 'path' => 'type', 'operator' => 'equals', 'value' => 'charge.succeeded']]], 'fields' => []],
            ['category' => 'stripe', 'key' => 'stripe.invoice_created', 'name' => 'Stripe: On Invoice Created', 'mechanism' => 'webhook',
                'description' => 'Fires when an invoice is created.',
                'signature_scheme' => 'stripe', 'dedupe_payload_path' => 'id',
                'preset_config' => ['filters' => [['source' => 'payload', 'path' => 'type', 'operator' => 'equals', 'value' => 'invoice.created']]], 'fields' => []],
            ['category' => 'stripe', 'key' => 'stripe.customer_created', 'name' => 'Stripe: On Customer Created', 'mechanism' => 'webhook',
                'description' => 'Fires when a customer is created.',
                'signature_scheme' => 'stripe', 'dedupe_payload_path' => 'id',
                'preset_config' => ['filters' => [['source' => 'payload', 'path' => 'type', 'operator' => 'equals', 'value' => 'customer.created']]], 'fields' => []],

            // Discord — interaction/webhook payloads carry a numeric type; keep it generic on t.
            ['category' => 'discord', 'key' => 'discord.message', 'name' => 'Discord: On Message', 'mechanism' => 'webhook',
                'description' => 'Fires on gateway MESSAGE_CREATE events forwarded to your hook URL.',
                'preset_config' => ['filters' => [['source' => 'payload', 'path' => 't', 'operator' => 'equals', 'value' => 'MESSAGE_CREATE']]], 'fields' => []],
            ['category' => 'discord', 'key' => 'discord.reaction', 'name' => 'Discord: On Reaction', 'mechanism' => 'webhook',
                'description' => 'Fires on gateway MESSAGE_REACTION_ADD events forwarded to your hook URL.',
                'preset_config' => ['filters' => [['source' => 'payload', 'path' => 't', 'operator' => 'equals', 'value' => 'MESSAGE_REACTION_ADD']]], 'fields' => []],
        ];
    }
}
