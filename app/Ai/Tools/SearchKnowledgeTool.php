<?php

namespace App\Ai\Tools;

use App\Models\Agents\DocumentEmbedding;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Semantic search over a workspace's document_embeddings, via cosine similarity in PHP
 * rather than a native vector column — see the note on the document_embeddings migration.
 */
class SearchKnowledgeTool implements Tool
{
    public function __construct(public int $workspaceId) {}

    public function name(): string
    {
        return 'search_knowledge';
    }

    public function description(): Stringable|string
    {
        return 'Search the workspace\'s uploaded documents for chunks relevant to a query.';
    }

    public function handle(Request $request): Stringable|string
    {
        $arguments = $request->all();
        $query = (string) $arguments['query'];
        $collection = $arguments['collection'] ?? null;
        $limit = (int) ($arguments['limit'] ?? 5);

        $queryVector = Embeddings::for([$query])->generate()->first();

        $matches = DocumentEmbedding::query()
            ->where('workspace_id', $this->workspaceId)
            ->when($collection, fn ($q) => $q->where('collection', $collection))
            ->get(['id', 'source', 'chunk_text', 'embedding'])
            ->map(fn (DocumentEmbedding $document): array => [
                'source' => $document->source,
                'chunk_text' => $document->chunk_text,
                'score' => self::cosineSimilarity($queryVector, $document->embedding),
            ])
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        if ($matches->isEmpty()) {
            return 'No matching documents found.';
        }

        return json_encode($matches, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        foreach ($a as $index => $value) {
            $other = $b[$index] ?? 0.0;
            $dotProduct += $value * $other;
            $magnitudeA += $value ** 2;
            $magnitudeB += $other ** 2;
        }

        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($magnitudeA) * sqrt($magnitudeB));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('What to search for.')->required(),
            'collection' => $schema->string()->description('Optional named collection to restrict the search to.'),
            'limit' => $schema->integer()->description('Max number of results to return (default 5).'),
        ];
    }
}
