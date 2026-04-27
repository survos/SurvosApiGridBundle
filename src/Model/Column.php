<?php

namespace Survos\ApiGridBundle\Model;

class Column
{
    public function __construct(
        public string $name,
        public ?string $block = null,
        public ?string $title = null,
        public ?string $twigTemplate = null,
        public ?string $route = null,
        public ?string $type = null,
        public ?string $rowName = 'row',
        public ?string $prefix = null,
        public ?string $internalCode = null,
        public ?string $className = null,
        public ?string $class_name = null,
        public ?string $width = null,
        public ?int $responsivePriority = null,
        public ?string $titleAttr = null,
        public ?bool $facet = null,
        public ?bool $grid = null,
        public ?bool $visible = null,

        // null = inherit from #[Field]; explicit bool overrides the attribute
        public ?bool $searchable = null,
        public ?bool $sortable   = null,
        public ?bool $browsable  = null,

        public bool $useDatatables = true,
        public bool $translateValue = false,
        public bool $inSearchPane = false,
        public int $browseOrder = 0,
        public ?array $actions = null,
        public bool $modal = false,
        public bool|string $locale = false,
        public int $order = 100,
        public bool $condition = true,
        public string|bool|null $domain = null,
    ) {
        if (empty($this->title)) {
            $this->title = $this->name;
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
