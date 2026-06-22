<?php

namespace App\Http\Middleware;

use App\Models\License;
use App\Models\RequestLog;
use App\Models\Website;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

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
            $response = response()->json([
                'message' => 'License key is required.',
            ], Response::HTTP_UNAUTHORIZED);

            $this->logRequest($request, $response->getStatusCode());

            return $response;
        }

        $license = License::query()
            ->where('code', $licenseKey)
            ->first();

        if (
            ! $license instanceof License
            || ! $license->is_active
            || ($license->expires_at !== null && ! $license->expires_at->isFuture())
        ) {
            $response = response()->json([
                'status' => false,
                'message' => 'License key is invalid.',
            ], Response::HTTP_FORBIDDEN);

            $this->logRequest($request, $response->getStatusCode(), $license, $licenseKey);

            return $response;
        }

        $request->attributes->set('license', $license);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->logRequest(
                $request,
                $this->statusCodeForException($exception),
                $license,
                $licenseKey,
            );

            throw $exception;
        }

        $this->logRequest($request, $response->getStatusCode(), $license, $licenseKey);

        return $response;
    }

    private function logRequest(
        Request $request,
        int $status,
        ?License $license = null,
        string $licenseKey = '',
    ): void {
        $source = trim((string) $request->header('source', ''));
        $website = null;

        if ($source !== '') {
            $website = Website::query()->firstOrCreate(
                ['domain' => $source],
                [
                    'ip_address' => $request->ip(),
                    'license_key' => $licenseKey,
                    'status' => $license instanceof License ? 'active' : 'invalid',
                ],
            );

            $website->fill([
                'ip_address' => $request->ip(),
                'license_key' => $licenseKey !== '' ? $licenseKey : $website->license_key,
                'status' => $license instanceof License ? 'active' : 'invalid',
                'wp_version' => $request->input('wp_version', $website->wp_version),
                'php_version' => $request->input('php_version', $website->php_version),
                'plugin_version' => $request->input('velocity_addons_version', $website->plugin_version),
            ])->save();
        }

        RequestLog::create([
            'route' => $request->getPathInfo(),
            'method' => $request->method(),
            'request' => $request->input(),
            'status' => $status,
            'website_id' => $website?->id,
            'license_id' => $license?->id,
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function websiteVersionPayload(Request $request, Website $website): array
    {
        return [
            'wp_version' => $request->input('wp_version', $website->wp_version),
            'php_version' => $request->input('php_version', $website->php_version),
            'plugin_version' => $request->input(
                'velocity_addons_version',
                $website->plugin_version,
            ),
        ];
    }

    private function statusCodeForException(Throwable $exception): int
    {
        if ($exception instanceof HttpResponseException) {
            return $exception->getResponse()->getStatusCode();
        }

        if ($exception instanceof ValidationException) {
            return $exception->status;
        }

        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
