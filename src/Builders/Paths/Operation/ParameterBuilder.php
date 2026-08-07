<?php

namespace LaravelJsonApi\OpenApiSpec\Builders\Paths\Operation;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Example;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema as OASchema;
use LaravelJsonApi\Contracts\Schema\Filter;
use LaravelJsonApi\Eloquent\Fields\Relations\ToMany;
use LaravelJsonApi\OpenApiSpec\Builders\Builder;
use LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Schema;
use LaravelJsonApi\OpenApiSpec\Route;

class ParameterBuilder extends Builder
{
    /**
     * @return Parameter[]
     */
    public function build(Route $route): array
    {
        // @todo Build a schema resolver with reusable instances
        $schemaDescriptor = new Schema($this->generator);

        /*
         * Which query parameters apply is a property of the action, not just of
         * whether the action happens to be `index`.
         *
         * A single-resource fetch accepts the parameters that shape how a resource
         * is rendered (`fields`, `include`) but not those that select among many
         * of them (`page`, `sort`, `filter`).
         *
         * Relationship routes must be detected by whether they carry a relation, not
         * by their action name. The action is taken from the last segment of the
         * route name, so a relationships endpoint reports `show` exactly like a
         * plain resource fetch does, and matching on the name alone silently
         * resolves it against the wrong schema.
         */
        /*
         * A route that describes itself has no schema, so there is nothing to
         * derive query parameters from. Its path parameters still have to be
         * declared, because OpenAPI refuses a path that holds an undeclared one.
         */
        if ($route->action() === Route::CUSTOM_ACTION) {
            return self::pathParameters($route);
        }

        $parameters = $route->isRelation()
            ? $this->relationParameters($route, $schemaDescriptor)
            : match ($route->action()) {
                'index' => [
                    ...$schemaDescriptor->pagination($route),
                    ...$schemaDescriptor->sortables($route),
                    ...$schemaDescriptor->filters($route),
                    ...$schemaDescriptor->sparseFieldsets($route),
                    ...$schemaDescriptor->includes($route),
                ],
                'show' => [
                    ...$schemaDescriptor->sparseFieldsets($route),
                    ...$schemaDescriptor->includes($route),
                ],
                default => [],
            };

        /*
         * Add id path parameter
         */
        if (isset($route->route()->defaults[\LaravelJsonApi\Laravel\Routing\Route::RESOURCE_ID_NAME])) {
            $id = $route->route()->defaults[\LaravelJsonApi\Laravel\Routing\Route::RESOURCE_ID_NAME];
            $examples = collect(
                $this->generator->resources()->resources($route->schema()::model()),
            )->map(function ($resource) {
                $id = $resource->id();

                return Example::create($id)->value($id);
            })->toArray();

            $parameters[] = Parameter::path($id)
                ->name($id)
                ->required(true)
                ->allowEmptyValue(false)
                ->examples(...$examples)
                ->schema(OASchema::string());
        }

        return $parameters;
    }

    /**
     * The path parameters that a route's URI holds.
     *
     * Laravel writes a path parameter as `{name}`, and marks an optional one with
     * `?`. A parameter in a path is always required in OpenAPI, so an optional one
     * belongs to a different path and is not declared here.
     *
     * The type comes from the constraint on the route, because that is the only
     * place a custom route states one.
     *
     * @return Parameter[]
     */
    private static function pathParameters(Route $route): array
    {
        preg_match_all('/\{(\w+)}/', $route->route()->uri(), $matches);

        return array_map(
            static function (string $name) use ($route): Parameter {
                $pattern = $route->route()->wheres[$name] ?? null;

                return Parameter::path($name)
                    ->name($name)
                    ->required(true)
                    ->allowEmptyValue(false)
                    ->schema($pattern === '[0-9]+' ? OASchema::integer() : OASchema::string());
            },
            $matches[1],
        );
    }

    /**
     * Combine a schema's own filters with those a parent attaches to the relation.
     *
     * A relation may re-scope a filter the inverse schema already declares, in which
     * case the relation's version is the one that actually applies and emitting both
     * would produce a duplicate parameter.
     *
     * @param  iterable<Filter>  $schemaFilters
     * @param  iterable<Filter>  $relationFilters
     * @return Filter[]
     */
    private static function mergeFilters(iterable $schemaFilters, iterable $relationFilters): array
    {
        $merged = [];

        foreach ($schemaFilters as $filter) {
            $merged[$filter->key()] = $filter;
        }

        foreach ($relationFilters as $filter) {
            $merged[$filter->key()] = $filter;
        }

        return array_values($merged);
    }

    /**
     * Query parameters for a relationship endpoint.
     *
     * These must be resolved against the schema of the resource the relation
     * *returns*, not the one it hangs off. `/posts/{post}/comments` is filtered and
     * sorted as comments, so deriving those from the post schema documents fields
     * that do not exist on the response.
     *
     * @return Parameter[]
     */
    protected function relationParameters(Route $route, Schema $schemaDescriptor): array
    {
        $relation = $route->relation();

        if ($relation === null) {
            return [];
        }

        /*
         * A polymorphic relation returns more than one resource type, so there is no
         * single schema whose fields, sorts, and filters describe the response.
         */
        if ($route->isPolymorphic()) {
            return [];
        }

        $inverseSchema = $route->inversSchema();
        $inverseResource = $relation->inverse();

        if ($inverseSchema === null) {
            return [];
        }

        /*
         * `showRelated` returns the related resources themselves. A relation route
         * whose action is `show` is the *relationships* endpoint, which returns bare
         * resource identifiers. Every other action mutates the relation and takes no
         * query parameters at all.
         */
        $isRelated = $route->action() === 'showRelated';
        $isRelationship = $route->action() === 'show';

        if (! $isRelated && ! $isRelationship) {
            return [];
        }

        /*
         * A to-one relation returns a single resource, so it takes the same
         * parameters as a `show`, not those of a collection.
         */
        if (! $relation instanceof ToMany) {
            return $isRelated ? [
                ...$schemaDescriptor->sparseFieldsets($route, $inverseSchema, $inverseResource),
                ...$schemaDescriptor->includes($route, $inverseSchema, $inverseResource),
            ] : [];
        }

        $parameters = [
            ...$schemaDescriptor->pagination($route, $inverseSchema),
            ...$schemaDescriptor->sortables($route, $inverseSchema),
            ...$schemaDescriptor->filters(
                $route,
                self::mergeFilters($inverseSchema->filters(), $relation->filters()),
                $inverseSchema,
            ),
        ];

        /*
         * Identifiers have no fields to select and nothing to include alongside.
         */
        if ($isRelationship) {
            return $parameters;
        }

        return [
            ...$parameters,
            ...$schemaDescriptor->sparseFieldsets($route, $inverseSchema, $inverseResource),
            ...$schemaDescriptor->includes($route, $inverseSchema, $inverseResource),
        ];
    }
}
