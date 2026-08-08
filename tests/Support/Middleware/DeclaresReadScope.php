<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Support\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * A gate that resolves its scope internally instead of taking it as a parameter,
 * and that stands down when the route already names a scope of its own.
 */
class DeclaresReadScope
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    /**
     * @param  array<int, mixed>  $routeMiddleware
     * @return string[]
     */
    public static function openApiScopes(array $routeMiddleware): array
    {
        foreach ($routeMiddleware as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'App\Api\Middleware\RequireOAuthTokenWithScope')) {
                return [];
            }
        }

        return ['data:read'];
    }
}
