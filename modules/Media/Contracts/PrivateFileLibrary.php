<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Contracts;

use Symfony\Component\HttpFoundation\Response;

/**
 * Owns a storage disk that is never symlinked/mirrored into the public
 * web root and never exposes a Storage::url() — the opposite guarantee
 * from MediaLibrary, which always serves the public disk. Consumers that
 * need to gate access (Digital\Contracts\DigitalAccessGrantor and its
 * download endpoint) resolve bytes only through respond(), never a path.
 */
interface PrivateFileLibrary
{
    public function store(
        string $contents,
        string $originalFilename,
        string $mimeType,
        ?string $uploadedByStaffId = null,
    ): PrivateFileData;

    /**
     * Streams a file already on local disk (e.g. an uploaded temp file) into private storage
     * without loading it into memory — the way for large files.
     */
    public function storeFromPath(
        string $sourcePath,
        string $originalFilename,
        string $mimeType,
        ?string $uploadedByStaffId = null,
    ): PrivateFileData;

    public function find(int $fileId): ?PrivateFileData;

    /** False when the last integrity check found the file missing or altered; such a file must not be served. */
    public function isServable(int $fileId): bool;

    /**
     * A prepared HTTP response streaming the file as an attachment named
     * $downloadFilename. Built on Symfony's BinaryFileResponse against
     * the local disk's real path, so a Range request (resuming a large,
     * interrupted download — see docs/knowledge-base/06-fulfillment.md#dostęp-cyfrowy)
     * is served as partial content natively; the caller does not need to
     * parse Range itself.
     */
    public function respond(int $fileId, string $downloadFilename): Response;
}
