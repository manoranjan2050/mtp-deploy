<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Websites\CloneWebsiteAction;
use App\Actions\Websites\CreateWebsiteAction;
use App\Actions\Websites\DeleteWebsiteAction;
use App\Actions\Websites\SuspendWebsiteAction;
use App\DTOs\Websites\CreateWebsiteData;
use App\Enums\WebsiteFramework;
use App\Http\Controllers\Controller;
use App\Http\Resources\WebsiteResource;
use App\Models\User;
use App\Models\Website;
use App\Services\Websites\WebsiteProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Website::class);

        /** @var User $user */
        $user = $request->user();

        $query = Website::query();

        if ($user->hasRole('developer')) {
            $query->where('created_by', $user->id);
        }

        return WebsiteResource::collection(
            $query->paginate(min((int) $request->integer('per_page', 15), 100))
        )->response();
    }

    public function show(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        return (new WebsiteResource($website))->response();
    }

    public function store(Request $request, CreateWebsiteAction $action): JsonResponse
    {
        $this->authorize('create', Website::class);

        $data = $request->validate([
            'server_id' => ['required', 'integer', 'exists:servers,id'],
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', 'unique:websites,domain'],
            'php_version' => ['required', 'string'],
            'framework' => ['required', 'string', 'in:'.implode(',', array_map(fn (WebsiteFramework $f): string => $f->value, WebsiteFramework::cases()))],
            'aliases' => ['sometimes', 'array'],
        ]);

        $result = $action->handle(new CreateWebsiteData(
            serverId: $data['server_id'],
            name: $data['name'],
            domain: $data['domain'],
            phpVersion: $data['php_version'],
            framework: WebsiteFramework::from($data['framework']),
            aliases: $data['aliases'] ?? [],
            createdBy: $request->user()->id,
        ));

        return (new WebsiteResource($result['website']))->response()->setStatusCode(201);
    }

    public function update(Request $request, Website $website, WebsiteProvisioningService $provisioning): JsonResponse
    {
        $this->authorize('update', $website);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'php_version' => ['sometimes', 'string'],
            'aliases' => ['sometimes', 'array'],
        ]);

        $website->update($data);
        $provisioning->republish($website->fresh());

        return (new WebsiteResource($website->fresh()))->response();
    }

    public function destroy(Request $request, Website $website, DeleteWebsiteAction $action): JsonResponse
    {
        $this->authorize('delete', $website);

        $action->handle($website);

        return response()->json(null, 204);
    }

    public function suspend(Request $request, Website $website, SuspendWebsiteAction $action): JsonResponse
    {
        $this->authorize('suspend', $website);

        $action->handle($website);

        return (new WebsiteResource($website->fresh()))->response();
    }

    public function clone(Request $request, Website $website, CloneWebsiteAction $action): JsonResponse
    {
        $this->authorize('create', Website::class);

        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'unique:websites,domain'],
        ]);

        $result = $action->handle($website, $data['domain'], $request->user()->id);

        return (new WebsiteResource($result['website']))->response()->setStatusCode(201);
    }
}
