<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Support\Controllers;

use LaravelJsonApi\OpenApiSpec\Attributes\WithDescription;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Responses\SampleResponse;

/**
 * An invokable controller that describes its own response.
 *
 * This is how you document an endpoint that is not a resource route. Declare the
 * response type. The description then comes from the same source as the
 * response.
 */
class PostSummaryController extends Controller
{
    #[WithDescription(SampleResponse::class, 'Summarise posts')]
    public function __invoke(): array
    {
        return [];
    }
}
