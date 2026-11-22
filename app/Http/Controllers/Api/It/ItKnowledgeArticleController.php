<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItKnowledgeArticle;
use App\Models\ItTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItKnowledgeArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        $search = $request->string('search')->toString();
        $tag = $request->string('tag')->toString();

        return response()->json(['data' => ItKnowledgeArticle::query()
            ->with(['author:id,name', 'category:id,name'])
            ->where('active', true)
            ->when(! $request->user()->can('informatica.editar'), fn ($query) => $query->where('public', true))
            ->when($request->integer('category_id'), fn ($query, $id) => $query->where('category_id', $id))
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            }))
            ->when($tag, fn ($query) => $query->whereJsonContains('tags', $tag))
            ->latest('published_at')
            ->paginate($request->integer('per_page', 20))]);
    }

    public function show(Request $request, ItKnowledgeArticle $article): JsonResponse
    {
        abort_if(! $article->public && ! $request->user()->can('informatica.editar'), 403);
        $article->increment('views_count');

        return response()->json($article->load(['author:id,name', 'category:id,name']));
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);

        return response()->json(ItKnowledgeArticle::create([
            ...$this->validatedData($request),
            'author_id' => $request->user()->id,
        ])->load(['author:id,name', 'category:id,name']), 201);
    }

    public function update(Request $request, ItKnowledgeArticle $article): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $article->update($this->validatedData($request, partial: true));

        return response()->json($article->load(['author:id,name', 'category:id,name']));
    }

    public function suggestions(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.visualizar'), 403);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'integer', 'exists:it_ticket_categories,id'],
        ]);
        $terms = collect(preg_split('/\s+/', mb_strtolower($data['q'] ?? '')))
            ->filter(fn ($term) => mb_strlen($term) >= 3)
            ->take(8);

        $articles = ItKnowledgeArticle::query()
            ->where('active', true)
            ->where('public', true)
            ->when($data['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when($terms->isNotEmpty(), fn ($query) => $query->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhere('title', 'like', "%{$term}%")
                        ->orWhere('summary', 'like', "%{$term}%")
                        ->orWhere('content', 'like', "%{$term}%");
                }
            }))
            ->limit(5)
            ->get(['id', 'title', 'summary', 'category_id', 'views_count']);

        return response()->json(['data' => $articles]);
    }

    public function fromTicket(Request $request, ItTicket $ticket): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);

        $article = ItKnowledgeArticle::create([
            'author_id' => $request->user()->id,
            'category_id' => $ticket->category_id,
            'title' => 'Solucao do chamado #'.$ticket->id,
            'summary' => $ticket->resolution_summary,
            'content' => trim(($ticket->applied_solution ?: $ticket->resolution_summary)."\n\n".$ticket->executed_procedure),
            'tags' => array_filter([$ticket->type, $ticket->priority, $ticket->category]),
            'active' => true,
            'public' => false,
            'published_at' => now(),
        ]);

        return response()->json($article, 201);
    }

    public function publish(Request $request, ItKnowledgeArticle $article): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.editar'), 403);
        $data = $request->validate(['public' => ['required', 'boolean']]);
        $article->update([
            'public' => $data['public'],
            'active' => true,
            'published_at' => $data['public'] ? ($article->published_at ?: now()) : null,
        ]);

        return response()->json($article->fresh(['author:id,name', 'category:id,name']));
    }

    private function validatedData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:it_ticket_categories,id'],
            'title' => [$required, 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:500'],
            'content' => [$required, 'string', 'max:50000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:60'],
            'tag' => ['nullable', 'string', 'max:60'],
            'active' => ['nullable', 'boolean'],
            'public' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
    }
}
