<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use App\Models\Server;
use App\Models\Website;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegisteredServerIp
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ipAddress = $request->ip();

        if ($ipAddress === null || ! Server::query()->where('server_ip', $ipAddress)->exists()) {
            $response = response()->json([
                'status' => false,
                'message' => 'IP address is not registered.',
            ], Response::HTTP_FORBIDDEN);

            $this->logRequest($request, $response->getStatusCode());

            return $response;
        }

        $response = $next($request);

        $this->logRequest($request, $response->getStatusCode());

        return $response;
    }

    private function logRequest(Request $request, int $status): void
    {
        $license = $request->header('license');
        $source = $request->header('source');

        $website = Website::query()->firstOrCreate(
            ['domain' => $source],
            ['license_key' => $license ?? ''],
        );

        RequestLog::create([
            'route' => $request->getPathInfo(),
            'method' => $request->method(),
            'request' => $request->input(),
            'status' => $status,
            'website_id' => $website->id,
            'license_id' => null,
        ]);
    }
}
