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
        public ?string $rowName = 'row', // what's passed to the column renderer
        public ?string $prefix = null,
        public ?string $internalCode = null, // e.g. label, description, type
        public ?string $className = null, // e.g. pull-right for numbers
        public bool $searchable = false,
        public bool $useDatatables = true,

        public bool $translateValue = false,
        // @todo: consolidate these two
        public bool $inSearchPane = false,
        public bool $browsable = false, // browseOrder = 100 if true unless set
        public int $browseOrder = 0, // if 0, same as false, so we can deprecate browsable
        public bool $sortable = false,
        public ?array $actions = null,
        public bool $modal = false,
        public bool|string $locale = false,
        public int $order = 100,
        public bool $condition = true,
        public string|bool|null $domain = null,
//        public ?string $propertyConfig,
    ) {
        if (empty($this->title)) {
            // the title of the column!
            $this->title = $this->name; // ucwords($this->name);
        }
    }

    public function __toString()
    {
        return $this->name;
    }
}
