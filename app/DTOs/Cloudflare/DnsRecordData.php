<?php

declare(strict_types=1);

namespace App\DTOs\Cloudflare;

final readonly class DnsRecordData
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $content,
        public int $ttl,
        public bool $proxied,
    ) {}

    /**
     * Builds a record from Cloudflare's real API v4 JSON shape - see
     * https://developers.cloudflare.com/api/operations/dns-records-for-a-zone-list-dns-records
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            type: (string) $data['type'],
            name: (string) $data['name'],
            content: (string) $data['content'],
            ttl: (int) ($data['ttl'] ?? 1),
            proxied: (bool) ($data['proxied'] ?? false),
        );
    }
}
