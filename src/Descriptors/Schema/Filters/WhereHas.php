<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Filters;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter;
use LaravelJsonApi\Contracts\Schema\Filter;
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\OpenApiSpec\Descriptors\Schema\Schema;

class WhereHas extends FilterDescriptor
{
    /**
     * @var Where
     */
    protected Filter $filter;

    /**
     * @todo Pay attention to isSingular
     */
    public function filter(): array
    {
        $relation = $this->route->schema()->relationship($this->filter->key());
        $resource = $relation->inverse();
        $schema = $this->generator
            ->server()
            ->schemas()
            ->schemaFor($resource);
        $mainSchema = new Schema($this->generator);
        $out = $mainSchema->filters($this->route, $schema->filters());

        $out = array_map(fn (Parameter $param) => $param->name(preg_replace(
            '/^filter/',
            'filter['.$this->filter->key().']',
            $param->name,
        )), $out);

        return $out;
    }

    protected function description(): string
    {
        return 'Allows filtering on the related resource.';
    }
}
