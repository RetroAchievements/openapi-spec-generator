<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Support\Controllers;

use LaravelJsonApi\OpenApiSpec\Attributes\WithDescription;
use LaravelJsonApi\OpenApiSpec\Tests\Support\Responses\SampleResponse;

/**
 * A route that resolves its own subject, so it can report a missing record even
 * though its URI holds no resource id.
 */
class PostLookupController extends Controller
{
    #[WithDescription(SampleResponse::class, 'Find a post', ['404'])]
    public function __invoke(): array
    {
        return [];
    }
}
