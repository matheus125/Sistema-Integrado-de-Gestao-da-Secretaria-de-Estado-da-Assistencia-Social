<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        $departments = Department::query()
            ->with('parent:id,name')
            ->withCount('children')
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return response()->json($departments);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->cannot('administracao.cadastrar'), 403);

        $data = $this->validatedData($request);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $department = Department::create($data);

        activity('administracao')->causedBy($request->user())->performedOn($department)->log('setor cadastrado');

        return response()->json($department->load('parent:id,name'), 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        abort_if($request->user()->cannot('administracao.editar'), 403);

        $data = $this->validatedData($request, $department);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $department->update($data);

        activity('administracao')->causedBy($request->user())->performedOn($department)->log('setor atualizado');

        return response()->json($department->load('parent:id,name'));
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        abort_if($request->user()->cannot('administracao.excluir'), 403);
        abort_if($department->children()->exists(), 422, 'Este setor possui subsetores.');
        abort_if($department->users()->exists(), 422, 'Este setor possui usuarios vinculados.');

        $department->delete();

        activity('administracao')->causedBy($request->user())->performedOn($department)->log('setor removido');

        return response()->json(['message' => 'Setor removido.']);
    }

    private function validatedData(Request $request, ?Department $department = null): array
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('departments', 'slug')->ignore($department)],
            'active' => ['boolean'],
        ]);

        if ($department && filled($data['parent_id'] ?? null)) {
            $this->validateParentHierarchy($department, (int) $data['parent_id']);
        }

        return $data;
    }

    private function validateParentHierarchy(Department $department, int $parentId): void
    {
        $parent = Department::query()->find($parentId);
        $visited = [];

        while ($parent) {
            if ($parent->is($department) || in_array($parent->id, $visited, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Um setor não pode ser subordinado a si mesmo ou a um de seus subsetores.'],
                ]);
            }

            $visited[] = $parent->id;
            $parent = $parent->parent;
        }
    }
}
