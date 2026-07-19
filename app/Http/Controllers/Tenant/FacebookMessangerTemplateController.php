<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\FacebookMessengerTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FacebookMessangerTemplateController extends Controller
{
    /** @var array<string, array{mimes: string[], max_size: int, description: string}> */
    private const FILE_VALIDATIONS = [
        'image' => [
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'max_size' => 5 * 1024 * 1024,
            'description' => 'Images (JPEG, PNG, GIF, WebP, max 5 MB)',
        ],
        'video' => [
            'mimes' => ['video/mp4', 'video/quicktime', 'video/x-msvideo'],
            'max_size' => 25 * 1024 * 1024,
            'description' => 'Videos (MP4, MOV, AVI, max 25 MB)',
        ],
        'document' => [
            'mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
            ],
            'max_size' => 25 * 1024 * 1024,
            'description' => 'Documents (PDF, DOC, DOCX, TXT, max 25 MB)',
        ],
    ];

    public function index(): \Illuminate\View\View
    {
        return view('tenant.facebook-messenger.template-create');
    }

    public function edit($subdomain, int $id): \Illuminate\View\View
    {
        $template = FacebookMessengerTemplate::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        return view('tenant.facebook-messenger.template-create', compact('template'));
    }

    /**
     * Unified store/update — creates when no $id, updates when $id is provided.
     */
    public function save($subdomain, Request $request, ?int $id = null): JsonResponse
    {
        $tenantId = tenant_id();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'content_type' => ['required', Rule::in(['text', 'image', 'video', 'document'])],
            'message_text' => 'nullable|string|max:2000',
            'media_url' => 'nullable|string|max:2048',
            'buttons' => 'nullable|array|max:16',
            'buttons.*.type' => 'required_with:buttons|in:postback,web_url',
            'buttons.*.title' => 'required_with:buttons|string|max:20',
            'buttons.*.payload' => 'nullable|string|max:1000',
            'buttons.*.url' => 'nullable|url|max:2048',
        ]);

        if (($validated['content_type'] ?? 'text') === 'text' && empty(trim($validated['message_text'] ?? ''))) {
            return response()->json([
                'success' => false,
                'errors' => ['message_text' => 'Message text is required for text templates.'],
            ], 422);
        }

        $buttons = $this->sanitizeButtons($validated['buttons'] ?? []);
        $newMediaUrl = $validated['media_url'] ?? null;

        if ($id) {
            // ── UPDATE ─────────────────────────────────────────────────────
            $template = FacebookMessengerTemplate::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($id);

            // Clean up old stored file if the URL changed
            if ($template->media_url && $template->media_url !== $newMediaUrl && $this->isOurStorageUrl($template->media_url)) {
                $this->deleteOldMediaFile($template->media_url);
            }

            $template->update([
                'name' => $validated['name'],
                'content_type' => $validated['content_type'],
                'message_text' => $validated['message_text'] ?? null,
                'media_url' => $newMediaUrl,
                'buttons' => $buttons ?: null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully.',
                'redirect_url' => tenant_route('tenant.facebook-messenger.templates'),
            ]);
        }

        // ── CREATE ─────────────────────────────────────────────────────────
        FacebookMessengerTemplate::query()->create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'content_type' => $validated['content_type'],
            'message_text' => $validated['message_text'] ?? null,
            'media_url' => $newMediaUrl,
            'buttons' => $buttons ?: null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully.',
            'redirect_url' => tenant_route('tenant.facebook-messenger.templates'),
        ], 201);
    }

    public function uploadMedia($subdomain, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|max:102400',
                'type' => 'required|in:image,video,document',
                'old_media_url' => 'nullable|string',
            ]);

            $file = $request->file('file');
            $type = $request->input('type');
            $oldMediaUrl = $request->input('old_media_url');
            $tenantId = tenant_id();

            // Validate MIME type and size
            $this->validateFileType($file, $type);

            // Replace old stored file if it belongs to our storage
            if ($oldMediaUrl && $this->isOurStorageUrl($oldMediaUrl)) {
                $this->deleteOldMediaFile($oldMediaUrl);
            }

            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = time().'_'.uniqid().'_'.Str::slug(pathinfo($originalName, PATHINFO_FILENAME)).'.'.$extension;
            $storagePath = "tenant/{$tenantId}/facebook-messenger/{$type}s";
            $filePath = $file->storeAs($storagePath, $filename, 'public');

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully.',
                'file_url' => asset('storage/'.$filePath),
                'file_path' => $filePath,
                'original_name' => $originalName,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'old_file_removed' => (bool) $oldMediaUrl,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File upload failed: '.$e->getMessage(),
            ], 500);
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Validate MIME type and file size, throws on failure.
     */
    private function validateFileType(UploadedFile $file, string $type): void
    {
        $validations = [
            'image' => [
                'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                'max_size' => 5 * 1024 * 1024,
                'description' => 'Images (JPEG, PNG, GIF, WebP, max 5 MB)',
            ],
            'video' => [
                'mimes' => ['video/mp4', 'video/quicktime', 'video/x-msvideo'],
                'max_size' => 25 * 1024 * 1024,
                'description' => 'Videos (MP4, MOV, AVI, max 25 MB)',
            ],
            'document' => [
                'mimes' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/plain',
                ],
                'max_size' => 25 * 1024 * 1024,
                'description' => 'Documents (PDF, DOC, DOCX, TXT, max 25 MB)',
            ],
        ];

        if (! isset($validations[$type])) {
            throw new \Exception('Invalid file type specified.');
        }

        $rule = $validations[$type];

        if (! in_array($file->getMimeType(), $rule['mimes'])) {
            throw new \Exception("Invalid file format. Expected: {$rule['description']}");
        }

        if ($file->getSize() > $rule['max_size']) {
            throw new \Exception("File too large. {$rule['description']}");
        }
    }

    /**
     * Returns true when the URL points to our tenant storage.
     */
    private function isOurStorageUrl(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        return str_contains($url, '/storage/tenant/'.tenant_id().'/facebook-messenger/');
    }

    /**
     * Deletes the physical file referenced by a storage URL.
     */
    private function deleteOldMediaFile(string $url): bool
    {
        try {
            $storageIndex = strpos($url, '/storage/');

            if ($storageIndex === false) {
                return false;
            }

            $relativePath = substr($url, $storageIndex + strlen('/storage/'));

            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);

                return true;
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    private function sanitizeButtons(array $buttons): array
    {
        $quickCount = 0;
        $urlCount = 0;
        $result = [];

        foreach ($buttons as $btn) {
            $type = $btn['type'] ?? '';

            if ($type === 'postback') {
                if ($quickCount >= 13) {
                    continue;
                }
                $title = substr(trim($btn['title'] ?? ''), 0, 20);
                $result[] = [
                    'type' => 'postback',
                    'title' => $title,
                    'payload' => trim($btn['payload'] ?? '') ?: strtoupper(str_replace(' ', '_', $title)),
                ];
                $quickCount++;
            } elseif ($type === 'web_url') {
                if ($urlCount >= 3) {
                    continue;
                }
                $result[] = [
                    'type' => 'web_url',
                    'title' => substr(trim($btn['title'] ?? ''), 0, 20),
                    'url' => trim($btn['url'] ?? ''),
                ];
                $urlCount++;
            }
        }

        return $result;
    }
}
