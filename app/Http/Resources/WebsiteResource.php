<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Website
 */
class WebsiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'name' => $this->name,
            'domain' => $this->domain,
            'aliases' => $this->aliases,
            'php_version' => $this->php_version,
            'framework' => $this->framework->value,
            'status' => $this->status->value,
            'ssl_status' => $this->ssl_status->value,
            'repository_url' => $this->repository_url,
            'git_branch' => $this->git_branch,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
