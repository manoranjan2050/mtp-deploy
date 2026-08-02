<?php

declare(strict_types=1);

namespace App\DTOs\FileManager;

final readonly class FileEntryData
{
    public function __construct(
        public string $name,
        public string $relativePath,
        public bool $isDirectory,
        public int $sizeBytes,
        public int $modifiedAt,
    ) {}

    public function isImage(): bool
    {
        return ! $this->isDirectory && in_array(
            strtolower(pathinfo($this->name, PATHINFO_EXTENSION)),
            ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'],
            true
        );
    }

    public function isEditableText(): bool
    {
        return ! $this->isDirectory && in_array(
            strtolower(pathinfo($this->name, PATHINFO_EXTENSION)),
            ['php', 'js', 'css', 'html', 'htm', 'txt', 'md', 'json', 'yml', 'yaml', 'env', 'xml', 'sh', 'sql', 'conf', 'ini'],
            true
        );
    }
}
