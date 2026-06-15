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
        $website = $licenseKey === ''
            ? null
            : Website::query()->where('license_key', $licenseKey)->first();

        RequestLog::create([
            'route' => $request->getPathInfo(),
            'method' => $request->method(),
            'request' => $request->all(),
            'status' => $status,
            'website_id' => $website?->id,
            'license_id' => $license?->id,
        ]);
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
