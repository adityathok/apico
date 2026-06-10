<?php

namespace App\Http\Middleware;

use App\Models\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseKey
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $licenseKey = trim((string) $request->header('License', ''));

        if ($licenseKey === '') {
            return response()->json([
                'message' => 'License key is required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $license = License::query()
            ->where('code', $licenseKey)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $license instanceof License) {
            return response()->json([
                'message' => 'License key is invalid.',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('license', $license);

        return $next($request);
    }
}
