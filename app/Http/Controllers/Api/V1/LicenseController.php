<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function index(Request $request, License $license): JsonResponse
    {
        $validated = $request->validate([
            'wp_version' => ['nullable', 'string', 'max:255'],
            'php_version' => ['nullable', 'string', 'max:255'],
            'velocity_addons_version' => ['nullable', 'string', 'max:255'],
        ]);

        $license = License::where('code', $request->header('license'))->first();

        if (! $license instanceof License) {
            return response()->json([
                'status' => false,
                'message' => 'License not found',
            ], 404);
        }

        $source = $request->header('source');
        $website = $source ? Website::where('domain', $source)->first() : null;

        if ($website) {
            $website->fill([
                'license_key' => $license->code,
                'wp_version' => $validated['wp_version'] ?? $website->wp_version,
                'php_version' => $validated['php_version'] ?? $website->php_version,
                'plugin_version' => $validated['velocity_addons_version'] ?? $website->plugin_version,
            ])->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => [
                'status' => $license->toArray()['is_active'],
                'is_active' => $license->toArray()['is_active'],
                'code' => $license->toArray()['code'],
                'website' => $website ? $website->toArray()['domain'] : null,
            ],
        ]);
    }

    public function getAutoLicense(): JsonResponse
    {
        $license = License::query()
            ->latest('created_at')
            ->first();

        if (! $license instanceof License) {
            return response()->json([
                'status' => false,
                'message' => 'License not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => [
                'status' => $license->is_active,
                'is_active' => $license->is_active,
                'code' => $license->code,
            ],
        ]);
    }
}
