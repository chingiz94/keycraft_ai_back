<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogApiRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $started = hrtime(true);
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $handler = app(ExceptionHandler::class);
            $handler->report($exception);
            $response = $handler->render($request, $exception);
        }

        $status = $response->getStatusCode();
        $level = $status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info');

        Log::channel('api')->log($level, 'api.request', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => '/'.$request->path(),
            'user_id' => $request->user()?->id,
            'status' => $status,
            'duration_ms' => round((hrtime(true) - $started) / 1_000_000, 2),
        ]);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
