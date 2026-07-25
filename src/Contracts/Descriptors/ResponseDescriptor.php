<?php

namespace LaravelJsonApi\OpenApiSpec\Contracts\Descriptors;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Response;

interface ResponseDescriptor extends Descriptor
{
    /**
     * @return Response[]
     */
    public function response(): array;
}
