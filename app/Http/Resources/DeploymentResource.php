<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Deployment
 */
class DeploymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'website_id' => $this->website_id,
            'provider' => $this->provider->value,
            'branch' => $this->branch,
            'commit_sha' => $this->commit_sha,
            'status' => $this->status->value,
            'triggered_by' => $this->triggered_by->value,
            'triggered_by_user_id' => $this->triggered_by_user_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
