<?php

namespace LaravelJsonApi\OpenApiSpec\Contracts\Descriptors;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter;

interface FilterDescriptor extends Descriptor
{
    /**
     * @return Parameter[]
     */
    public function filter(): array;
}
