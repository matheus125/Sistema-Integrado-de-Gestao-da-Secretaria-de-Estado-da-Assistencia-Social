<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachmentUploadRequest;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\ItTicket;
use App\Models\Product;
use App\Models\VehicleRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    private const TYPES = [
        'it-ticket' => ItTicket::class,
        'vehicle-request' => VehicleRequest::class,
        'product' => Product::class,
        'asset' => Asset::class,
    ];

    public function index(string $type, int $id): JsonResponse
    {
        $model = $this->resolveModel($type, $id);
        $this->authorizeAttachmentAccess(request(), $model);

        $attachments = Attachment::query()
            ->with('uploader:id,name')
            ->where('attachable_type', $model::class)
            ->where('attachable_id', $model->getKey())
            ->latest()
            ->get()
            ->map(fn (Attachment $attachment) => $this->serialize($attachment));

        return response()->json(['data' => $attachments]);
    }

    public function store(AttachmentUploadRequest $request, string $type, int $id): JsonResponse
    {
        $model = $this->resolveModel($type, $id);
        $this->authorizeAttachmentAccess($request, $model, write: true);

        $data = $request->validated();

        $file = $data['file'];
        $path = $file->store("attachments/{$type}/{$id}");

        $attachment = Attachment::create([
            'attachable_type' => $model::class,
            'attachable_id' => $model->getKey(),
            'uploaded_by' => $request->user()->id,
            'disk' => config('filesystems.default', 'local'),
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'metadata' => ['description' => $data['description'] ?? null],
        ]);

        activity('anexos')->causedBy($request->user())->performedOn($model)->withProperties([
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
        ])->log('anexo enviado');

        return response()->json($this->serialize($attachment->load('uploader:id,name')), 201);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorizeAttachmentAccess(request(), $attachment->attachable);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        $this->authorizeAttachmentAccess($request, $attachment->attachable, write: true);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        activity('anexos')->causedBy($request->user())->withProperties([
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
        ])->log('anexo removido');

        return response()->json(['message' => 'Anexo removido.']);
    }

    private function resolveModel(string $type, int $id): Model
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        return self::TYPES[$type]::query()->findOrFail($id);
    }

    private function serialize(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'description' => $attachment->metadata['description'] ?? null,
            'uploaded_by' => $attachment->uploader?->name,
            'created_at' => $attachment->created_at?->toISOString(),
            'download_url' => "/attachments/{$attachment->id}/download",
        ];
    }

    private function authorizeAttachmentAccess(Request $request, ?Model $model, bool $write = false): void
    {
        abort_unless($model, 404);

        $user = $request->user();
        if ($user->hasRole('Administrador Geral')) {
            return;
        }

        $departmentId = $model->department_id ?? null;

        if ($model instanceof VehicleRequest) {
            abort_if(! $user->can('transporte.visualizar'), 403);
            abort_if($write && ! $user->can('transporte.editar'), 403);
            abort_if(! $user->can('transporte.aprovar') && $departmentId !== $user->department_id, 403);
            return;
        }

        if ($model instanceof ItTicket) {
            abort_if(! $user->can('informatica.visualizar'), 403);
            abort_unless($user->can('view', $model), 403);
            abort_if($write && ! $user->can('informatica.editar') && $model->opened_by !== $user->id, 403);
            return;
        }

        if ($model instanceof Asset) {
            abort_if(! $user->can('materiais-patrimonio.visualizar'), 403);
            abort_if($write && ! $user->can('materiais-patrimonio.editar'), 403);
            abort_if(! $user->can('materiais-patrimonio.aprovar') && $departmentId !== $user->department_id, 403);
            return;
        }

        if ($model instanceof Product) {
            abort_if(! $user->can('materiais-patrimonio.visualizar'), 403);
            abort_if($write && ! $user->can('materiais-patrimonio.editar'), 403);
        }
    }
}
