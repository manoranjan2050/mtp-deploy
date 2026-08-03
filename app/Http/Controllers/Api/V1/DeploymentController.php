<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Deployments\RollbackDeploymentAction;
use App\Actions\Deployments\TriggerDeploymentAction;
use App\Enums\DeploymentTrigger;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeploymentResource;
use App\Models\Deployment;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeploymentController extends Controller
{
    public function index(Request $request, Website $website): JsonResponse
    {
        $this->authorize('view', $website);

        return DeploymentResource::collection(
            $website->deployments()->paginate(min((int) $request->integer('per_page', 15), 100))
        )->response();
    }

    public function store(Request $request, Website $website, TriggerDeploymentAction $action): JsonResponse
    {
        $this->authorize('update', $website);

        $deployment = $action->handle($website, DeploymentTrigger::Api, $request->user());

        return (new DeploymentResource($deployment))->response()->setStatusCode(201);
    }

    public function rollback(Request $request, Deployment $deployment, RollbackDeploymentAction $action): JsonResponse
    {
        $this->authorize('update', $deployment->website);

        $rollback = $action->handle($deployment, $request->user());

        return (new DeploymentResource($rollback))->response()->setStatusCode(201);
    }
}
