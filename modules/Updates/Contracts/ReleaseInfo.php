<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Contracts;

final readonly class ReleaseInfo
{
    public function __construct(
        public SemanticVersion $version,
        public string $tag,
        public string $archiveUrl,
        public string $checksumUrl,
        public string $manifestUrl,
        public string $manifestSignatureUrl,
        public string $notesUrl,
        public ?string $publishedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => $this->version->toString(), 'tag' => $this->tag, 'archive_url' => $this->archiveUrl,
            'checksum_url' => $this->checksumUrl, 'manifest_url' => $this->manifestUrl,
            'manifest_signature_url' => $this->manifestSignatureUrl, 'notes_url' => $this->notesUrl,
            'published_at' => $this->publishedAt,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            SemanticVersion::parse((string) $data['version']), (string) $data['tag'], (string) $data['archive_url'],
            (string) $data['checksum_url'], (string) $data['manifest_url'], (string) $data['manifest_signature_url'],
            (string) $data['notes_url'], isset($data['published_at']) ? (string) $data['published_at'] : null,
        );
    }
}
