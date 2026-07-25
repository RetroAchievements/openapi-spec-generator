<?php

namespace LaravelJsonApi\OpenApiSpec\Contracts\Descriptors\Schema;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter;
use LaravelJsonApi\OpenApiSpec\Contracts\Descriptors\Descriptor;
use LaravelJsonApi\OpenApiSpec\Route;

interface SortablesDescriptor extends Descriptor
{
    /**
     * @return Parameter[]
     */
    public function sortables(Route $route): array;
}
