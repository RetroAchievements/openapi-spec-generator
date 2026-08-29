<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Actions;

class Store extends ActionDescriptor
{
    protected function summary(): string
    {
        return "Create {$this->withArticle($this->route->name(true))}";
    }
}
