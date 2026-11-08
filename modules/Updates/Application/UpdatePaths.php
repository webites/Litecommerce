<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

final readonly class UpdatePaths
{
    /** @param list<string> $protected application-relative paths an update never touches */
    public function __construct(
        public string $root,
        public string $workRoot,
        public string $backupRoot,
        public array $protected,
    ) {}

    public function workDirectory(string $operationId): string
    {
        return $this->workRoot.'/'.$operationId;
    }

    public function archive(string $operationId): string
    {
        return $this->workDirectory($operationId).'/package.zip';
    }

    public function staging(string $operationId): string
    {
        return $this->workDirectory($operationId).'/staging';
    }

    public function fileBackup(string $operationId): string
    {
        return $this->backupRoot.'/update-'.$operationId.'/files';
    }

    public function isProtected(string $relativePath): bool
    {
        foreach ($this->protected as $protected) {
            if ($relativePath === $protected || str_starts_with($relativePath, $protected.'/')) {
                return true;
            }
        }

        return false;
    }
}
