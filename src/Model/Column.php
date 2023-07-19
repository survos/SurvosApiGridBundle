<?php

namespace Survos\ApiGrid\Model;

class Column
{
    public function __construct(
        public string $name,
        public ?string $block = null, // reuse the blocks even if the data changes
        public ?string $title = null,
        public ?string $twigTemplate = null,
        public ?string $route = null,
        public ?string $type = null, // this is used for searchBuilder
        public ?string $prefix = null,
        public ?string $internalCode = null, // e.g. label, description, type
        public bool $searchable = false,
        public bool $browsable = false, // browseOrder = 100 if true unless set
        public int $browseOrder = 0, // if 0, same as false, so we can deprecate browsable
        public bool $sortable = false,
        public ?array $actions = null,
        public bool $modal = false,
        public bool|string $locale = false,
        public int $order = 0,
        public bool $condition = true
//        public ?string $propertyConfig,
    ) {
        if (empty($this->title)) {
            $this->title = ucwords($this->name);
        }
    }

    public function __toString()
    {
        return $this->name;
    }
}
