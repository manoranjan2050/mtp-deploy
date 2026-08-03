<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\CreateApiTokenAction;
use App\Enums\ApiTokenAbility;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApiTokenResource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiTokenResource::collection($user->tokens)->response();
    }

    public function store(Request $request, CreateApiTokenAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', 'in:'.implode(',', array_map(fn (ApiTokenAbility $a): string => $a->value, ApiTokenAbility::cases()))],
        ]);

        if (! password_verify($data['password'], $user->password)) {
            throw new AuthorizationException('The provided password is incorrect.');
        }

        $token = $action->handle($user, $data['name'], $data['abilities']);

        return response()->json([
            'token' => $token->plainTextToken,
            'ability' => new ApiTokenResource($token->accessToken),
        ], 201);
    }

    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $user->tokens()->findOrFail($tokenId);
        $token->delete();

        return response()->json(null, 204);
    }
}
