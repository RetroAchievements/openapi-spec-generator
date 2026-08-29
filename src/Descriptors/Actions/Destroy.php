<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Actions;

class Destroy extends ActionDescriptor
{
    protected function summary(): string
    {
        return "Delete {$this->withArticle($this->route->name(true))}";
    }
}
