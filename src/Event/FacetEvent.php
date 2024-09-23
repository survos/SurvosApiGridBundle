<?php

namespace Survos\ApiGrid\Event;

use Survos\PixieBundle\Command\IterateCommand;
use Survos\PixieBundle\Model\Config;
use Survos\PixieBundle\Model\Item;
use Survos\PixieBundle\Service\PixieImportService;
use Survos\PixieBundle\StorageBox;
use Symfony\Contracts\EventDispatcher\Event;

class FacetEvent extends Event
{
    public function __construct(
        private array $facets,
        private string $targetLocale,
        public array $context = []
    ) {
    }

    public function getTargetLocale(): string
    {
        return $this->targetLocale;
    }


    public function getFacets(): array
    {
        return $this->facets;
    }

    public function setFacets(array $facets): FacetEvent
    {
        $this->facets = $facets;
        return $this;
    }
}
