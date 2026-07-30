<?php

namespace App\Services\Agents;

use App\Ai\Agents\EvalJudgeAgent;
use App\Enums\Agents\AssertionType;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalCase;
use App\Models\Agents\AgentEvalRun;
use App\Models\Agents\AgentEvalSuite;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs every case in a suite against its agent and grades the response with a mix of
 * deterministic assertions (contains / not_contains / equals / regex) and an LLM rubric
 * judge, producing a pass/fail report. Meant to gate publishing or catch regressions
 * after editing instructions.
 */
class AgentEvalService
{
    /**
     * @return array<int, string>
     */
    public static function assertionTypes(): array
    {
        return AssertionType::values();
    }

    public function __construct(private readonly AgentChatService $chat) {}

    /**
     * Execute a suite against its agent and persist the results.
     */
    public function run(AgentEvalSuite $suite, User $triggeredBy): AgentEvalRun
    {
        $suite->loadMissing(['agent', 'cases']);
        $agent = $suite->agent;

        $evalRun = AgentEvalRun::create([
            'suite_id' => $suite->id,
            'agent_id' => $agent->id,
            'triggered_by' => $triggeredBy->id,
            'status' => AgentEvalRun::STATUS_RUNNING,
            'total' => $suite->cases->count(),
        ]);

        $results = [];
        $passed = 0;

        try {
            foreach ($suite->cases as $case) {
                $result = $this->runCase($agent, $case, $triggeredBy);
                $results[] = $result;
                $passed += $result['passed'] ? 1 : 0;
            }

            $evalRun->update([
                'status' => AgentEvalRun::STATUS_COMPLETED,
                'passed' => $passed,
                'failed' => count($results) - $passed,
                'results' => $results,
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $evalRun->update([
                'status' => AgentEvalRun::STATUS_FAILED,
                'passed' => $passed,
                'failed' => max(0, count($results) - $passed),
                'results' => $results,
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
        }

        return $evalRun;
    }

    /**
     * @return array{case_id: int, name: string, passed: bool, output: string, failures: list<string>}
     */
    private function runCase(Agent $agent, AgentEvalCase $case, User $triggeredBy): array
    {
        $result = $this->chat->send($agent, $triggeredBy, $case->input);

        if ($result['error'] !== null || $result['reply'] === null) {
            return [
                'case_id' => $case->id,
                'name' => $case->name,
                'passed' => false,
                'output' => '',
                'failures' => ['Agent errored: '.($result['error'] ?? 'no reply')],
            ];
        }

        $output = $result['reply'];
        $failures = [];

        foreach ($case->assertions ?? [] as $assertion) {
            $failure = $this->evaluateAssertion($agent, $case->input, $output, $assertion);

            if ($failure !== null) {
                $failures[] = $failure;
            }
        }

        return [
            'case_id' => $case->id,
            'name' => $case->name,
            'passed' => $failures === [],
            'output' => $output,
            'failures' => $failures,
        ];
    }

    /**
     * Evaluate a single assertion. Returns a failure message, or null on pass.
     *
     * @param  array<string, mixed>  $assertion
     */
    private function evaluateAssertion(Agent $agent, string $input, string $output, array $assertion): ?string
    {
        $type = AssertionType::tryFrom((string) ($assertion['type'] ?? AssertionType::Contains->value));
        $value = (string) ($assertion['value'] ?? '');

        if ($type === null) {
            return 'Unknown assertion type "'.($assertion['type'] ?? '').'".';
        }

        return $type->needsJudge()
            ? $this->judgeRubric($agent, $input, $output, $value)
            : $type->check($output, $value);
    }

    private function judgeRubric(Agent $agent, string $input, string $output, string $rubric): ?string
    {
        $prompt = <<<PROMPT
        Original input:
        {$input}

        Rubric (what a correct response must do):
        {$rubric}

        Agent's response:
        {$output}
        PROMPT;

        try {
            $response = (new EvalJudgeAgent)->prompt(
                $prompt,
                provider: $agent->provider,
                model: $agent->model,
            );
        } catch (Throwable $exception) {
            return 'Rubric judge failed: '.$exception->getMessage();
        }

        $verdict = json_decode(Str::of($response->text)->between('{', '}')->wrap('{', '}')->toString(), true) ?? [];

        if ((bool) ($verdict['passed'] ?? false)) {
            return null;
        }

        $reason = trim((string) ($verdict['reason'] ?? 'did not satisfy the rubric'));

        return "Rubric not met: {$reason}";
    }
}
