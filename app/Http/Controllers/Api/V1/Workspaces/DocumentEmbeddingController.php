<?php

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workspaces\StoreDocumentEmbeddingRequest;
use App\Http\Resources\V1\DocumentEmbeddingResource;
use App\Http\Responses\ApiResponse;
use App\Models\DocumentEmbedding;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Embeddings;

class DocumentEmbeddingController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        $collection = $request->query('collection');

        return ApiResponse::success(
            DocumentEmbeddingResource::collection(
                $workspace->documentEmbeddings()
                    ->when($collection, fn ($query) => $query->where('collection', $collection))
                    ->latest()
                    ->paginate(25),
            ),
        );
    }

    public function store(StoreDocumentEmbeddingRequest $request, Workspace $workspace): JsonResponse
    {
        $vector = Embeddings::for([$request->validated('chunk_text')])->generate()->first();

        $embedding = $workspace->documentEmbeddings()->create([
            ...$request->validated(),
            'embedding' => $vector,
        ]);

        return ApiResponse::created(new DocumentEmbeddingResource($embedding), 'Document embedding created.');
    }

    public function destroy(Request $request, Workspace $workspace, DocumentEmbedding $documentEmbedding): JsonResponse
    {
        abort_if($documentEmbedding->workspace_id !== $workspace->id, 404);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $documentEmbedding->delete();

        return ApiResponse::success(null, 'Document embedding deleted.');
    }
}
