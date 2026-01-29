<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileBadgeGrantRequest;
use App\Models\User;
use App\Services\BadgeCatalogService;
use App\Services\BadgePayloadService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

class ProfileBadgeController extends Controller
{
    public function grant(ProfileBadgeGrantRequest $request, string $slug, BadgeCatalogService $catalogService, BadgePayloadService $payloadService): JsonResponse
    {
        $viewer = $request->user();
        if (!$viewer || !$viewer->isAdmin()) {
            abort(403);
        }
        if (!safeHasTable('users')) {
            abort(503);
        }

        $data = $request->validated();
        $badgeKey = $data['badge_key'];

        $catalog = $catalogService->find($badgeKey);
        if (!$catalog) {
            return response()->json(['message' => 'Unknown badge'], 422);
        }

        $user = User::where('slug', $slug)->firstOrFail();

        try {
            $badge = $user->grantBadge($badgeKey, [
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'reason' => $data['reason'] ?? null,
                'issued_by' => $viewer,
                'issued_at' => now(),
                'catalog' => $catalog,
                'link' => route('profile.show', $user->slug),
            ], true);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => 'Unknown badge'], 422);
        } catch (RuntimeException $exception) {
            abort(503);
        }

        $badgeCatalog = $catalogService->all();
        $badges = $payloadService->forUser($user->fresh(), $badgeCatalog);
        $badgePayload = collect($badges)->firstWhere('id', $badge->id);

        return response()->json([
            'ok' => true,
            'badge_id' => $badge->id,
            'badge' => $badgePayload,
            'badges' => $badges,
        ]);
    }

    public function revoke(\Illuminate\Http\Request $request, string $slug, int $badgeId, BadgeCatalogService $catalogService, BadgePayloadService $payloadService): JsonResponse
    {
        $viewer = $request->user();
        if (!$viewer || !$viewer->isAdmin()) {
            abort(403);
        }
        if (!safeHasTable('user_badges')) {
            abort(503);
        }

        $user = User::where('slug', $slug)->firstOrFail();
        $deleted = $user->revokeBadge($badgeId);

        if (!$deleted) {
            return response()->json(['message' => 'Badge not found'], 404);
        }

        $badgeCatalog = $catalogService->all();
        $badges = $payloadService->forUser($user->fresh(), $badgeCatalog);

        return response()->json([
            'ok' => true,
            'badge_id' => $badgeId,
            'badges' => $badges,
        ]);
    }
}
