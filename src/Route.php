<?php

namespace LaravelJsonApi\OpenApiSpec;

use GoldSpecDigital\ObjectOrientedOAS\Objects\SecurityRequirement;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use LaravelJsonApi\Contracts\Schema\PolymorphicRelation;
use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\Eloquent\Fields\Relations\Relation;
use LaravelJsonApi\OpenApiSpec\Attributes\WithDescription;

class Route
{
    protected Server $server;

    protected ?Schema $schema = null;

    protected IlluminateRoute $route;

    protected string $resource;

    /**
     * @var string The controller class FQN
     */
    protected string $controller;

    /**
     * @var string The method name on the controller
     */
    protected string $method;

    /**
     * The last part of the route name. For the 'showRelated' method, the name
     * is manually added.
     */
    protected string $action;

    /**
     * @var string The route name without the prefix
     */
    protected string $operationId;

    protected string $uri;

    protected ?string $relation = null;

    // Security schemes applied to this route. Scheme application is guessed from which scopes lie within which schemes.
    // @var SecurityRequirement[] $securitySchemes
    protected array $securitySchemes = [];

    /**
     * The action of a route that an attribute describes.
     *
     * A schema does not describe this route.
     */
    public const CUSTOM_ACTION = 'custom';

    /**
     * @return string[]
     */
    private static function scopeCandidates(string $middleware): array
    {
        $separator = strpos($middleware, ':');

        if ($separator === false) {
            return [];
        }

        return explode(',', substr($middleware, $separator + 1));
    }

    /**
     * Scopes named by the middleware class itself.
     *
     * A middleware that takes its scope as a parameter already states it in the
     * route definition. One that resolves the scope internally has no such
     * marker, so it can declare `openApiScopes()` instead. The whole middleware
     * list is passed in because a gate that stands down when another middleware
     * names a scope must reach the same conclusion here that it does at runtime.
     *
     * @param  array<int, mixed>  $routeMiddleware
     * @return string[]
     */
    private static function declaredScopes(string $middleware, array $routeMiddleware): array
    {
        $separator = strpos($middleware, ':');
        $class = $separator === false ? $middleware : substr($middleware, 0, $separator);

        if (! class_exists($class) || ! method_exists($class, 'openApiScopes')) {
            return [];
        }

        $declared = $class::openApiScopes($routeMiddleware);

        return is_array($declared) ? array_values(array_filter($declared, 'is_string')) : [];
    }

    /**
     * Route constructor.
     */
    public function __construct(Server $server, IlluminateRoute $route)
    {
        $this->server = $server;
        $this->route = $route;

        $securitySchemes = config("openapi.servers.{$this->server->name()}.securitySchemes", []);
        $matchingMiddleware = collect($securitySchemes)->map(fn (array $m) => $m['middleware']);
        $matchingControllers = collect($securitySchemes)->map(
            fn (array $scheme) => $scheme['controllers'] ?? null,
        )->map(fn (?array $controllers) => function (?string $controller) use ($controllers): bool {
            // $controller is a controller class-name with '@<method name>' appended.
            if ($controllers === null) {
                return true;
            }

            $split = explode('@', $controller);
            if (count($split) < 2) {
                return false;
            }

            foreach ($controllers as $targetClass => $actions) {
                if (is_int($targetClass) && is_string($actions)) {
                    // no actions listed so only match class
                    $targetClass = $actions;
                    if ($split[0] == $targetClass) {
                        return true;
                    }
                } else {
                    foreach ($actions as $action) {
                        if ($split[0] == $targetClass && $split[1] == $action) {
                            return true;
                        }
                    }
                }
            }

            return false;
        });

        $scopes = [];
        $appliedSchemes = [];
        $middlewares = $this->route->gatherMiddleware();
        if (! empty($securitySchemes)) {
            foreach ($middlewares as $middleware) {
                if (! is_string($middleware)) {
                    continue;
                }

                foreach ($matchingMiddleware as $securityScheme => $middlewareToMatch) {
                    if (in_array($middleware, $middlewareToMatch)) {
                        if ($matchingControllers[$securityScheme]($this->route->action['controller'])) {
                            $appliedSchemes[$securityScheme] =
                                SecurityRequirement::create($securityScheme)->securityScheme($securityScheme);
                        }
                    }
                }

                $scopes = array_merge(
                    $scopes,
                    self::scopeCandidates($middleware),
                    self::declaredScopes($middleware, $middlewares),
                );
            }
        }

        if (! empty($scopes)) {
            $matchingSchemes = collect($securitySchemes)
                /*
                 * `scanForPassportScopes` is the former name, kept working because
                 * it is published configuration. Scanning is no longer limited to
                 * Passport, so the key now reads as scanning for scopes generally.
                 */
                ->filter(fn (array $scheme) => ($scheme['scanForScopes']
                    ?? $scheme['scanForPassportScopes']
                    ?? true) && isset($scheme['flows']))
                ->map(
                    fn (array $scheme) => collect($scheme['flows'])
                        ->map(fn (array $flow) => collect($flow['scopes'] ?? [])->keys())
                        ->flatten()
                        ->unique(),
                )
                ->map(fn (Collection $schemeScopes) => $schemeScopes->intersect($scopes))
                ->filter(fn (Collection $overlap) => $overlap->count() > 0);

            foreach ($matchingSchemes as $securityScheme => $overlap) {
                $requirement = $appliedSchemes[$securityScheme] ?? SecurityRequirement::create(
                    $securityScheme,
                )->securityScheme($securityScheme);
                $requirement = $requirement->scopes(...$overlap->toArray());
                $appliedSchemes[$securityScheme] = $requirement;
            }
        }

        // A scheme can opt out of routes that refuse it, such as an API key on an
        // endpoint that accepts only a scoped OAuth token.
        foreach ($securitySchemes as $securityScheme => $scheme) {
            if (self::usesAnyMiddleware($middlewares, $scheme['excludeMiddleware'] ?? [])) {
                unset($appliedSchemes[$securityScheme]);
            }
        }

        $this->securitySchemes = array_values($appliedSchemes);

        $segments = explode('.', $this->route->getName());
        $segments = array_slice($segments, array_search($this->server->name(), $segments) + 1);

        $this->operationId = collect($segments)->join('.');

        [$this->controller, $this->method] = self::callableFor($route);

        /**
         * An attribute describes this route. It is not a resource route. It has
         * no schema. Its name has no fixed shape. Do not do the resource steps
         * below.
         */
        if (self::describedByAttribute($route)) {
            $this->schema = null;
            $this->resource = $segments[0] ?? $this->server->name();
            $this->relation = null;
            $this->action = self::CUSTOM_ACTION;

            $this->setUriForRoute();

            return;
        }

        $relation = null;

        if (count($segments) === 2) {
            [$resource, $action] = $segments;
        } elseif (count($segments) === 3) {
            [$resource, $relation, $action] = $segments;
        } else {
            throw new \LogicException('Unable to handle action structure '.$route->getName());
        }

        $this->resource = $resource;
        $this->schema = $this->server->schemas()->schemaFor($resource);

        if ($action !== null && $relation === null && $this->schema->isRelationship($action)) {
            $this->relation = $action;
            $this->action = 'showRelated';
        } else {
            $this->relation = $relation;
            $this->action = $action;
        }

        $this->setUriForRoute();
    }

    /**
     * Gets the controller and the method of a route.
     *
     * An invokable controller has no method part in its action name. The method
     * of such a controller is `__invoke`.
     *
     * @return array{0: string, 1: string}
     */
    private static function callableFor(IlluminateRoute $route): array
    {
        $action = $route->getActionName();

        return str_contains($action, '@')
            ? explode('@', $action, 2)
            : [$action, '__invoke'];
    }

    /**
     * Tells you if the controller method of the route has a description attribute.
     */
    private static function describedByAttribute(IlluminateRoute $route): bool
    {
        [$class, $method] = self::callableFor($route);

        try {
            $reflection = new \ReflectionMethod($class, $method);
        } catch (\ReflectionException) {
            return false;
        }

        return $reflection->getAttributes(WithDescription::class) !== [];
    }

    /**
     * @return string The HTTP method
     */
    public function method(): string
    {
        return collect($this->route->methods())->filter(fn ($method) => $method !== 'HEAD')->first();
    }

    public function schema(): ?Schema
    {
        return $this->schema;
    }

    /**
     * Whether a rate limiter stands in front of this route.
     *
     * Matches Laravel's throttle middleware by name, in both its alias and its
     * class form, because either may reach the gathered list.
     */
    public function isThrottled(): bool
    {
        foreach ($this->route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if ($middleware === 'throttle' || str_starts_with($middleware, 'throttle:')) {
                return true;
            }

            if (str_contains($middleware, 'ThrottleRequests')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Route middleware entries carry their parameters after a colon, so an
     * exclusion matches on the class name alone.
     *
     * @param  array<int, mixed>  $middlewares
     * @param  array<int, string>  $classes
     */
    private static function usesAnyMiddleware(array $middlewares, array $classes): bool
    {
        foreach ($middlewares as $middleware) {
            if (is_string($middleware) && in_array(explode(':', $middleware, 2)[0], $classes, true)) {
                return true;
            }
        }

        return false;
    }

    // @return SecurityRequirement[]
    public function securitySchemes(): array
    {
        return $this->securitySchemes;
    }

    public function route(): IlluminateRoute
    {
        return $this->route;
    }

    /**
     * @return string[]
     */
    public function controllerCallable(): array
    {
        return [$this->controller, $this->method];
    }

    public function id(): string
    {
        return $this->operationId;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function relationName(): ?string
    {
        return $this->relation;
    }

    public function relation(): ?Relation
    {
        $relation = $this->relation ? $this->schema()->relationship($this->relation) : null;

        if ($relation !== null && ! $relation instanceof Relation) {
            throw new \RuntimeException('Unexpected Type');
        }

        return $relation;
    }

    public function isRelation(): bool
    {
        return $this->relation !== null;
    }

    public function isPolymorphic(): bool
    {
        return $this->relation() instanceof PolymorphicRelation;
    }

    public function invers(): ?string
    {
        return $this->relation() !== null ? $this->relation()->inverse() : null;
    }

    public function inversSchema(): ?Schema
    {
        if ($this->isRelation()) {
            if ($this->relation() instanceof PolymorphicRelation) {
                throw new \LogicException('Method is not allowed for Polymorphic relationships');
            }

            return $this->server
                ->schemas()
                ->schemaFor($this->relation() !== null ? $this->relation()->inverse() : null);
        }

        return null;
    }

    /**
     * @return Schema[]
     */
    public function inversSchemas(): array
    {
        $schemas = [];
        if ($this->isRelation()) {
            $relation = $this->relation();
            if ($relation instanceof PolymorphicRelation) {
                foreach ($relation->inverseTypes() as $type) {
                    $schemas[$type] = $this->server->schemas()->schemaFor($type);
                }
            } else {
                $schemas[$relation->inverse()] = $this->server->schemas()->schemaFor($relation->inverse());
            }
        }

        return $schemas;
    }

    public function inverseName(bool $singular = false): ?string
    {
        $relation = $this->relation() !== null ? $this->relation()->inverse() : null;
        if ($singular) {
            return Str::singular($relation);
        }

        return $relation;
    }

    /**
     * @param  false  $singular
     */
    public function name(bool $singular = false): string
    {
        if ($singular) {
            return Str::singular($this->resource);
        }

        return $this->resource;
    }

    public function resource(): string
    {
        return $this->resource;
    }

    public function action(): string
    {
        return $this->action;
    }

    /**
     * The controller methods supplied by the standard JSON:API action traits.
     *
     * A route bound to anything else cannot be described from a schema, because the
     * generator resolves each operation by which action trait the controller uses.
     */
    public const JSON_API_ACTIONS = [
        'index',
        'store',
        'show',
        'update',
        'destroy',
        'showRelated',
        'showRelationship',
        'updateRelationship',
        'attachRelationship',
        'detachRelationship',
    ];

    public static function belongsTo(IlluminateRoute $route, Server $server): bool
    {
        return self::rejectionReason($route, $server) === null;
    }

    /**
     * Why this route cannot be described, or null if it can be.
     *
     * A bare substring test on the route name is not enough. It sweeps in any route
     * whose name merely contains the server name and hands it to a parser that
     * assumes a `{server}.{resource}.{action}` shape, which then fails on ordinary
     * application routes registered under the same prefix.
     *
     * Callers report the reason rather than discarding it, so that a route dropped
     * from the document is always visible to whoever runs the generator.
     */
    public static function rejectionReason(IlluminateRoute $route, Server $server): ?string
    {
        $name = $route->getName();

        if ($name === null || ! Str::startsWith($name, $server->name().'.')) {
            return 'name is not prefixed with the server name';
        }

        /*
         * A route that describes itself does not need the checks below. The
         * checks show that a schema can describe a route. This route does not
         * use a schema.
         */
        if (self::describedByAttribute($route)) {
            return null;
        }

        $segments = explode('.', Str::after($name, $server->name().'.'));

        if (count($segments) < 2 || count($segments) > 3) {
            return sprintf('name has %d segment(s) after the server prefix, expected 2 or 3', count($segments));
        }

        $action = $route->getActionName();

        /*
         * An invokable controller has no method segment at all. Left alone it fails
         * later as an undefined array index rather than as a skipped route.
         */
        if (! Str::contains($action, '@')) {
            return 'route is bound to an invokable controller or a closure';
        }

        $method = Str::afterLast($action, '@');

        if (! in_array($method, self::JSON_API_ACTIONS, true)) {
            return sprintf('controller method [%s] is not a JSON:API action', $method);
        }

        if (! $server->schemas()->exists($segments[0])) {
            return sprintf('no schema is registered for resource type [%s]', $segments[0]);
        }

        return null;
    }

    protected function setUriForRoute(): void
    {
        $domain = URL::to('/');
        $serverBasePath = str_replace($domain, '', $this->server->url());

        $this->uri = str_replace($serverBasePath, '', '/'.$this->route->uri());
    }
}
