<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Responses;

use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Response;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;
use Illuminate\Support\Collection;
use LaravelJsonApi\OpenApiSpec\Builders\Paths\Operation\SchemaBuilder;
use LaravelJsonApi\OpenApiSpec\Generator;
use LaravelJsonApi\OpenApiSpec\Helpers\SchemaFromClass;
use LaravelJsonApi\OpenApiSpec\Route;

/**
 * Makes a response description from the class that the route returns.
 *
 * The class declares the type of each field. It also declares if a field can be
 * null. One declaration controls the response and the documentation. Thus the
 * two always agree.
 */
class WithDescriptionClass extends ResponseDescriptor
{
    /**
     * @param  class-string  $responseClass
     */
    public function __construct(
        Generator $generator,
        Route $route,
        SchemaBuilder $schemaBuilder,
        Collection $defaults,
        private readonly string $responseClass,
        private readonly ?string $summary = null,
    ) {
        parent::__construct($generator, $route, $schemaBuilder, $defaults);
    }

    /**
     * {@inheritDoc}
     */
    public function response(): array
    {
        return [
            $this->ok(),
            ...$this->defaults(),
        ];
    }

    /**
     * The response body is the described class only.
     *
     * This route is not a resource route. It does not send a JSON:API document.
     * Do not add the `jsonapi` and `data` wrapper. The wrapper would show a body
     * that the route never sends. It would also show the wrong media type.
     */
    protected function ok(): Response
    {
        return Response::ok()
            ->description($this->description())
            ->content(
                MediaType::json()->schema($this->data()),
            );
    }

    protected function description(): string
    {
        return $this->summary ?? 'Success';
    }

    protected function data(): Schema
    {
        return SchemaFromClass::generate($this->responseClass);
    }
}
