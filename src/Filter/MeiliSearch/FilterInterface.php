<?php

namespace Survos\ApiGrid\Filter\MeiliSearch;

use ApiPlatform\Metadata\FilterInterface as BaseFilterInterface;
use ApiPlatform\Metadata\Operation;

interface FilterInterface extends BaseFilterInterface
{

    public function apply(array $clauseBody, string $resourceClass, ?Operation $operation = null, array $context = []): array;
}
