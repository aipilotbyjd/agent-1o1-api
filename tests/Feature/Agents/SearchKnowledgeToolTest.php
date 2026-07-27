<?php

use App\Ai\Tools\SearchKnowledgeTool;
use App\Models\DocumentEmbedding;
use App\Models\Workspace;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request as ToolRequest;

it('computes cosine similarity correctly', function () {
    expect(SearchKnowledgeTool::cosineSimilarity([1, 0, 0], [1, 0, 0]))->toBe(1.0)
        ->and(SearchKnowledgeTool::cosineSimilarity([1, 0, 0], [0, 1, 0]))->toBe(0.0)
        ->and(SearchKnowledgeTool::cosineSimilarity([1, 0, 0], [-1, 0, 0]))->toBe(-1.0);
});

it('returns the closest matching document chunk to the query', function () {
    Embeddings::fake([[[1.0, 0.0, 0.0]]]);

    $workspace = Workspace::factory()->create();
    DocumentEmbedding::factory()->create([
        'workspace_id' => $workspace->id,
        'chunk_text' => 'How to request a refund.',
        'embedding' => [1.0, 0.0, 0.0],
    ]);
    DocumentEmbedding::factory()->create([
        'workspace_id' => $workspace->id,
        'chunk_text' => 'How to reset your password.',
        'embedding' => [0.0, 1.0, 0.0],
    ]);

    $response = (new SearchKnowledgeTool($workspace->id))->handle(new ToolRequest(['query' => 'refund policy']));

    $results = json_decode((string) $response, true);

    expect($results[0]['chunk_text'])->toBe('How to request a refund.')
        ->and((float) $results[0]['score'])->toBe(1.0)
        ->and((float) $results[1]['score'])->toBe(0.0);
});

it('reports no matches when the workspace has no documents', function () {
    Embeddings::fake([[[1.0, 0.0, 0.0]]]);
    $workspace = Workspace::factory()->create();

    $response = (new SearchKnowledgeTool($workspace->id))->handle(new ToolRequest(['query' => 'anything']));

    expect((string) $response)->toBe('No matching documents found.');
});
