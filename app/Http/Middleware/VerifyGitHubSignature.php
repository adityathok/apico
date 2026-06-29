<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class VerifyGitHubSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = trim((string) $request->header('X-Signature', ''));

        if ($signature === '') {
            $response = new JsonResponse([
                'status' => false,
                'message' => 'X-Signature header is required.',
            ], Response::HTTP_UNAUTHORIZED);

            $this->logRequest($request, $response->getStatusCode());

            return $response;
        }

        $expectedSignature = md5((string) env('RELEASE_WEBHOOK_SECRET'));

        if (! hash_equals($expectedSignature, $signature)) {
            $response = new JsonResponse([
                'status' => false,
                'message' => 'X-Signature header is invalid.',
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
        RequestLog::create([
            'route' => $request->getPathInfo(),
            'method' => $request->method(),
            'request' => $request->input(),
            'status' => $status,
            'website_id' => null,
            'license_id' => null,
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
