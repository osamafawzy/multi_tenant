<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ForceJsonNoCacheForOAuthCallback
{
    public function __construct(
        private readonly ExceptionHandler $exceptionHandler,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        try {
            $response = $next($request);
        } catch (Throwable $throwable) {
            $this->exceptionHandler->report($throwable);
            $response = $this->exceptionHandler->render($request, $throwable);
        }

        return $this->appendNoCacheHeaders($response);
    }

    private function appendNoCacheHeaders(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
