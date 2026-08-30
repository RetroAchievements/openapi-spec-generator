<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Responses;

use GoldSpecDigital\ObjectOrientedOAS\Contracts\SchemaContract;
use GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Response;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;
use Illuminate\Support\Collection;
use LaravelJsonApi\Contracts\Schema\Schema as JASchema;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\OpenApiSpec\Builders\Paths\Operation\ResponseBuilder;
use LaravelJsonApi\OpenApiSpec\Builders\Paths\Operation\SchemaBuilder;
use LaravelJsonApi\OpenApiSpec\ComponentsContainer;
use LaravelJsonApi\OpenApiSpec\Contracts\Descriptors\ResponseDescriptor as ResponseDescriptorContract;
use LaravelJsonApi\OpenApiSpec\Descriptors\Descriptor;
use LaravelJsonApi\OpenApiSpec\Generator;
use LaravelJsonApi\OpenApiSpec\Route;
use Neomerx\JsonApi\Contracts\Http\Headers\MediaTypeInterface;

abstract class ResponseDescriptor extends Descriptor implements ResponseDescriptorContract
{
    protected Route $route;

    protected ComponentsContainer $components;

    protected SchemaBuilder $schemaBuilder;

    protected Collection $defaults;

    protected bool $hasId = false;

    protected bool $validates = false;

    public function __construct(Generator $generator, Route $route, SchemaBuilder $schemaBuilder, Collection $defaults)
    {
        parent::__construct($generator);
        $this->route = $route;
        $this->components = $this->generator->components();
        $this->schemaBuilder = $schemaBuilder;
        $this->defaults = $defaults;
    }

    /**
     * @return Response[]
     */
    abstract public function response(): array;

    /**
     * @throws InvalidArgumentException
     */
    protected function ok(): Response
    {
        return Response::ok()->description($this->description())->content(
            MediaType::create()
                ->mediaType(MediaTypeInterface::JSON_API_MEDIA_TYPE)
                ->schema(ResponseBuilder::buildResponse($this->data(), $this->meta(), $this->links(), $this->included())),
        );
    }

    protected function noContent(): Response
    {
        return Response::create()->statusCode(204)->description('No Content');
    }

    protected function defaults(): array
    {
        $except = [];
        if (! $this->hasId && ! in_array('404', $this->alsoDocument(), true)) {
            $except[] = '404';
        }
        if (! $this->validates && ! in_array('422', $this->alsoDocument(), true)) {
            $except[] = '422';
        }
        if (! $this->route->isThrottled()) {
            $except[] = '429';
        }

        return $this->defaults->except($except)->toArray();
    }

    /**
     * Status codes this route documents beyond the ones its shape implies.
     *
     * A route without an id in its URI normally cannot report a missing record,
     * so 404 is dropped. A route that resolves its own subject can still report
     * one, and only the route itself knows that.
     *
     * @return string[]
     */
    protected function alsoDocument(): array
    {
        return [];
    }

    /**
     * Describe the response in terms of the resource it actually returns.
     *
     * A relationship response is named after the parent resource by default, so an
     * endpoint returning player games is described as returning users. Describing a
     * response as the wrong resource type is worse than leaving it undescribed.
     */
    protected function description(): string
    {
        if (! $this->route->isRelation()) {
            return ucfirst($this->route->action()).' '.$this->route->name();
        }

        $parent = $this->route->name(true);
        $inverse = $this->route->inverseName() ?? $this->route->relationName();

        return $this->route->action() === 'showRelated'
            ? sprintf('The %s related to a %s', $inverse, $parent)
            : sprintf('Identifiers of the %s related to a %s', $inverse, $parent);
    }

    abstract protected function data(): SchemaContract;

    protected function meta(): ?Schema
    {
        return null;
    }

    protected function links(): ?Schema
    {
        return null;
    }

    protected function pageMeta(?JASchema $schema): ?Schema
    {
        if ($schema === null || ! method_exists($schema, 'pagination') || ! $schema->pagination() instanceof PagePagination) {
            return null;
        }

        return Schema::object('meta')->properties(
            Schema::object('page')
                ->properties(
                    Schema::integer('currentPage'),
                    Schema::integer('from')->nullable(),
                    Schema::integer('lastPage'),
                    Schema::integer('perPage'),
                    Schema::integer('to')->nullable(),
                    Schema::integer('total'),
                )
                ->required('currentPage', 'lastPage', 'perPage', 'total'),
        )->required('page');
    }

    protected function pageLinks(): Schema
    {
        return Schema::object('links')
            ->properties(
                Schema::string('first'),
                Schema::string('last'),
                Schema::string('prev'),
                Schema::string('next'),
            )
            ->required('first', 'last');
    }

    protected function selfLinks(): Schema
    {
        return Schema::object('links')->properties(Schema::string('self'))->required('self');
    }

    protected function included(): ?Schema
    {
        return null;
    }
}
