<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Actions;

class FetchMany extends ActionDescriptor
{
    protected function summary(): string
    {
        return "List {$this->humanize($this->route->name())}";
    }
}
