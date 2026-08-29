<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Actions;

class FetchOne extends ActionDescriptor
{
    protected function summary(): string
    {
        return "Get {$this->withArticle($this->route->name(true))}";
    }
}
