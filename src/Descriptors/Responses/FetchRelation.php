<?php

namespace LaravelJsonApi\OpenApiSpec\Descriptors\Responses;

use GoldSpecDigital\ObjectOrientedOAS\Exceptions\InvalidArgumentException;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;
use LaravelJsonApi\Eloquent\Fields\Relations\ToMany;

class FetchRelation extends ResponseDescriptor
{
    protected bool $hasId = true;

    /**
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException
     * @throws InvalidArgumentException
     *
     * @todo Implement
     */
    public function response(): array
    {
        return [
            $this->ok(),
            ...$this->defaults(),
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function data(): Schema
    {
        if ($this->route->relation() instanceof ToMany) {
            return Schema::array('data')
                ->items($this->schemaBuilder->build($this->route));
        }

        return $this->schemaBuilder->build($this->route)->objectId('data');
    }
}
