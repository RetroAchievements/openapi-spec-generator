<?php

namespace LaravelJsonApi\OpenApiSpec\Attributes;

use Closure;

// WithDescription documents a route that no schema describes. It names the class
// that the route returns, or gives an example of the response.
#[\Attribute]
class WithDescription
{
    /**
     * WithDescription constructor.
     *
     * @param  array|class-string|Closure():array  $responseClassOrExample  The class
     *                                                                      that the route returns, or an example of its response. Prefer a
     *                                                                      class. A class declares nullability and element types. An example
     *                                                                      cannot declare either.
     */
    public function __construct(
        private mixed $responseClassOrExample,
        private ?string $description = null,
    ) {}

    /**
     * Get the description as a string, or returns null if none set.
     */
    public function getDescription(): ?string
    {
        if ($this->description instanceof Closure) {
            return ($this->description)();
        }

        return $this->description;
    }

    /**
     * Returns the response class, or an example if one was given instead.
     *
     * @return array|class-string
     */
    public function getResponseClassOrExample(): mixed
    {
        if ($this->responseClassOrExample instanceof Closure) {
            return ($this->responseClassOrExample)();
        }

        return $this->responseClassOrExample;
    }
}
