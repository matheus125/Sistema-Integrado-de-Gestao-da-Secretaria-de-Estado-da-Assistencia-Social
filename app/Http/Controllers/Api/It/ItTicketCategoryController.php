<?php

namespace App\Http\Controllers\Api\It;

use App\Http\Controllers\Controller;
use App\Models\ItTicketCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItTicketCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.categorias.visualizar'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', Rule::in(['0', '1'])],
            'parent_id' => ['nullable', 'integer', 'exists:it_ticket_categories,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $categories = ItTicketCategory::query()
            ->with('parent:id,name')
            ->withCount(['children', 'tickets'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when(array_key_exists('active', $filters), fn ($query) => $query->where('active', (bool) $filters['active']))
            ->when(array_key_exists('parent_id', $filters), fn ($query) => $query->where('parent_id', $filters['parent_id']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.categorias.cadastrar'), 403);

        $data = $this->validatedData($request);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $category = ItTicketCategory::create($data);

        activity('informatica')->causedBy($request->user())->performedOn($category)->log('categoria de chamado cadastrada');

        return response()->json($category->load('parent:id,name')->loadCount(['children', 'tickets']), 201);
    }

    public function update(Request $request, ItTicketCategory $category): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.categorias.editar'), 403);

        $data = $this->validatedData($request, $category);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $this->ensureParentIsNotSelfOrDescendant($category, $data['parent_id'] ?? null);

        $category->update($data);

        activity('informatica')->causedBy($request->user())->performedOn($category)->log('categoria de chamado atualizada');

        return response()->json($category->load('parent:id,name')->loadCount(['children', 'tickets']));
    }

    public function destroy(Request $request, ItTicketCategory $category): JsonResponse
    {
        abort_if($request->user()->cannot('informatica.categorias.excluir'), 403);

        if ($category->tickets()->exists()) {
            $category->update(['active' => false]);

            activity('informatica')->causedBy($request->user())->performedOn($category)->log('categoria de chamado desativada');

            return response()->json(['message' => 'Categoria em uso por chamados. Ela foi desativada.']);
        }

        abort_if($category->children()->exists(), 422, 'Esta categoria possui subcategorias.');

        $category->delete();

        activity('informatica')->causedBy($request->user())->performedOn($category)->log('categoria de chamado removida');

        return response()->json(['message' => 'Categoria removida.']);
    }

    private function validatedData(Request $request, ?ItTicketCategory $category = null): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:it_ticket_categories,id'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('it_ticket_categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
            'color' => ['nullable', 'string', 'max:40'],
            'icon' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);
    }

    private function ensureParentIsNotSelfOrDescendant(ItTicketCategory $category, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $category->id) {
            throw ValidationException::withMessages(['parent_id' => ['A categoria pai nao pode ser a propria categoria.']]);
        }

        $descendantIds = $this->descendantIds($category);

        if (in_array($parentId, $descendantIds, true)) {
            throw ValidationException::withMessages(['parent_id' => ['A categoria pai nao pode ser uma subcategoria dela mesma.']]);
        }
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(ItTicketCategory $category): array
    {
        $children = $category->children()->get(['id']);
        $ids = $children->pluck('id')->all();

        foreach ($children as $child) {
            $ids = [...$ids, ...$this->descendantIds($child)];
        }

        return $ids;
    }
}
