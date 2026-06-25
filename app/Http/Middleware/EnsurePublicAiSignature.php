<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use App\Models\Website;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

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
            $response = new JsonResponse([
                'status' => false,
                'message' => 'Signature header is required.',
            ], Response::HTTP_UNAUTHORIZED);

            $this->logRequest($request, $response->getStatusCode());

            return $response;
        }

        if (! hash_equals($expectedSignature, $signature)) {
            $response = new JsonResponse([
                'status' => false,
                'message' => 'Signature header is invalid.',
            ], Response::HTTP_FORBIDDEN);

            $this->logRequest($request, $response->getStatusCode());

            return $response;
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->logRequest($request, $this->statusCodeForException($exception));

            throw $exception;
        }

        $this->logRequest($request, $response->getStatusCode());

        return $response;
    }

    private function logRequest(Request $request, int $status): void
    {
        $source = trim((string) $request->header('source', ''));
        $licenseKey = trim((string) $request->header('license', ''));
        $website = $source === ''
            ? null
            : Website::query()->firstOrCreate(
                ['domain' => $source],
                [
                    'ip_address' => $request->ip(),
                    'license_key' => $this->websiteLicenseKey($source),
                    'status' => 'active',
                ],
            );

        if ($website !== null) {
            $website->fill([
                'ip_address' => $request->ip(),
                'license_key' => $licenseKey !== '' ? $licenseKey : $website->license_key,
                'wp_version' => $request->input('wp_version', $website->wp_version),
                'php_version' => $request->input('php_version', $website->php_version),
            ])->save();
        }

        RequestLog::create([
            'route' => $request->getPathInfo(),
            'method' => $request->method(),
            'request' => $request->input(),
            'status' => $status,
            'website_id' => $website?->id,
            'license_id' => null,
        ]);
    }

    private function websiteLicenseKey(string $resource): string
    {
        return 'AI-PUBLIC-'.substr(md5($resource), 0, 24);
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
