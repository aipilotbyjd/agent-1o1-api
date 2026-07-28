<?php

namespace Database\Seeders;

use App\Models\Agents\AgentTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AgentTemplateSeeder extends Seeder
{
    /**
     * Seed the starter gallery of agent templates.
     */
    public function run(): void
    {
        $sort = 0;

        foreach ($this->catalog() as $template) {
            AgentTemplate::updateOrCreate(
                ['slug' => Str::slug($template['name'])],
                [...$template, 'slug' => Str::slug($template['name']), 'is_active' => true, 'sort_order' => $sort++],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            [
                'name' => 'Support Agent',
                'description' => 'Answers customer questions using your knowledge base and escalates what it can\'t resolve.',
                'category' => 'support',
                'icon' => 'life-buoy',
                'color' => '#6366f1',
                'system_prompt' => 'You are a helpful, concise customer support agent. Answer from the knowledge base '
                    .'provided to you; if you are not confident in an answer, say so and offer to escalate to a human.',
                'llm_provider' => 'anthropic',
                'llm_model' => 'claude-sonnet-4-6',
                'is_featured' => true,
            ],
            [
                'name' => 'Sales Qualifier',
                'description' => 'Chats with inbound leads, asks qualifying questions, and summarizes fit for the sales team.',
                'category' => 'sales',
                'icon' => 'trending-up',
                'color' => '#0ea5e9',
                'system_prompt' => 'You are a friendly sales development rep. Ask qualifying questions about budget, '
                    .'authority, need, and timeline, and summarize the lead\'s fit at the end of the conversation.',
                'llm_provider' => 'anthropic',
                'llm_model' => 'claude-sonnet-4-6',
                'is_featured' => true,
            ],
            [
                'name' => 'Meeting Notetaker',
                'description' => 'Turns a raw transcript into a structured summary with action items.',
                'category' => 'productivity',
                'icon' => 'clipboard-list',
                'color' => '#22c55e',
                'system_prompt' => 'You turn meeting transcripts into a short summary, a list of decisions made, '
                    .'and a list of action items with an owner for each.',
                'llm_provider' => 'anthropic',
                'llm_model' => 'claude-sonnet-4-6',
                'is_featured' => false,
            ],
        ];
    }
}
