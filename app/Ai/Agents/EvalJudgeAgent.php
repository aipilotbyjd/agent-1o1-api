<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class EvalJudgeAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
        You are a strict evaluation judge for AI agent responses. You are given the original
        input, a rubric describing what a correct response must do, and the agent's response.

        Decide whether the response satisfies the rubric. Reply with ONLY a JSON object,
        no markdown fences or commentary: {"passed": true|false, "reason": "one short sentence"}
        TEXT;
    }
}
