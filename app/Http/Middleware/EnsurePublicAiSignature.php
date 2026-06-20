<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Models\RequestLog;
use App\Models\Website;

class EnsurePublicAiSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = trim((string) $request->header('signature', ''));
        $expectedSignature = md5(now()->format('dmY'));

        if ($signature === '') {
            return new JsonResponse([
                'status' => false,
                'message' => 'Signature header is required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! hash_equals($expectedSignature, $signature)) {
            return new JsonResponse([
                'status' => false,
                'message' => 'Signature header is invalid.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
