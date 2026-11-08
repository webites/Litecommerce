<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Media\Contracts\MediaData;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;

final class MediaPickerController extends Controller
{
    private const PAGE_SIZE = 24;

    public function index(Request $request, MediaLibrary $media): JsonResponse
    {
        $data = $request->validate([
            'before' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $items = $media->listImages(self::PAGE_SIZE + 1, isset($data['before']) ? (int) $data['before'] : null, $data['search'] ?? null);
        $hasMore = count($items) > self::PAGE_SIZE;
        $items = array_slice($items, 0, self::PAGE_SIZE);

        return response()->json([
            'items' => array_map($this->present(...), $items),
            'next_before' => $hasMore && $items !== [] ? $items[array_key_last($items)]->id : null,
        ]);
    }

    public function store(Request $request, MediaLibrary $media): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.(int) config('media.max_size_kb')],
        ]);
        $file = $request->file('image');
        $uploaded = $media->store(
            contents: $file->get(),
            originalFilename: $file->getClientOriginalName(),
            mimeType: (string) $file->getMimeType(),
            uploadedByStaffId: (string) $request->user()?->getAuthIdentifier(),
        );

        return response()->json($this->present($uploaded), 201);
    }

    /** @return array<string, mixed> */
    private function present(MediaData $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url,
            'thumbnail_url' => $media->variants === [] ? $media->url : $media->variants[min(array_keys($media->variants))],
            'srcset' => $media->srcset(),
            'alt' => $media->altText,
            'filename' => $media->originalFilename,
            'width' => $media->width,
            'height' => $media->height,
        ];
    }
}
