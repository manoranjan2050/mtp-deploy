<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RevokeSessionAction;
use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $sessions = Session::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        return response()->json(['data' => $sessions]);
    }

    public function destroy(Request $request, string $sessionId, RevokeSessionAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->handle($user, $sessionId);

        return response()->json(null, 204);
    }
}
