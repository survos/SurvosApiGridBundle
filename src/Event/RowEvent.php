<?php


namespace Survos\PixieBundle\Event;

use Survos\PixieBundle\Command\IterateCommand;
use Survos\PixieBundle\Model\Config;
use Survos\PixieBundle\Model\Item;
use Survos\PixieBundle\Service\PixieImportService;
use Survos\PixieBundle\StorageBox;
use Symfony\Contracts\EventDispatcher\Event;

class RowEvent extends Event
{

    public const string PRE_LOAD='pre_load';
    public const string LOAD='load';
    public const string SAVE='save';
    public const string POST_LOAD='post_load';
    public const string DISCARD='discard'; // do not import the row

    public function __construct(
        public string $configCode, // same as pixieCode?
        public string $tableName,
        public ?array $row=null, // return null to not add to database
        public ?Item $item=null, // perhaps it should evolve to this!
        public int|string|null $key=null,
        public ?int $index=null, // numeric index so callback can stop after a limit or show progress
        public ?int $total=null, // so we can act on the first or last row, add a progressBar, etc.
        public ?string $type=self::LOAD, // defaults to regular row load
        public ?string $action=null,
        public ?StorageBox $storageBox=null, // so we can update other tables
        public ?Config $config=null, // to loop through tables and such
        public array $context = []
    )
    {
        if ($type) {
            assert(in_array($type, [self::PRE_LOAD, self::LOAD, self::POST_LOAD, self::DISCARD]));
        }
        if ($this->action) {
            assert(class_exists($this->action));
        }
    }

    public function isPreLoad(): bool
    {
        return $this->type === self::PRE_LOAD;
    }
    public function isRowLoad(): bool
    {
        return $this->type === self::LOAD;
    }
    public function isPostLoad(): bool
    {
        return $this->type === self::POST_LOAD;
    }

    public function isImport(): bool
    {
        return $this->action === PixieImportService::class;
    }
    public function isIterate(): bool
    {
        return $this->action === IterateCommand::class;
    }
}
