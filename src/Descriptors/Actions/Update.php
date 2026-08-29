<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Actions;

class Update extends ActionDescriptor
{
    protected function summary(): string
    {
        return "Update {$this->withArticle($this->route->name(true))}";
    }
}
