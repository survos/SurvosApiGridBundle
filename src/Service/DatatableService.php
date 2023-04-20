<?php

declare(strict_types=1);

namespace Survos\ApiGrid\Service;

use ApiPlatform\Api\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorDynamicHTML;
use Picqer\Barcode\BarcodeGeneratorHTML;
use Picqer\Barcode\BarcodeGeneratorJPG;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;
use Survos\ApiGrid\Model\Column;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function Symfony\Component\String\u;

class DatatableService
{
    public function __construct()
    {
    }

    /**
     * @param array $columns
     * @return array<int, Column>
     */
    public function normalizedColumns(string $class, array $columns, array $customColumnTemplates): iterable
    {
        //        $normalizedColumns = parent::normalizedColumns();

        //        dd($customColumnTemplates);
        //        dd($template->getBlockNames());
        //        dd($template->getSourceContext());
        //        dd($template->getBlockNames());
        $normalizedColumns = [];

        $settings = $this->getSettingsFromAttributes($class);
//        $sortableFields = $this->sortableFields($class);
//        $searchableFields = $this->searchableFields($class);


        foreach ($columns as $idx => $c) {
            if (empty($c)) {
                continue;
            }
            if (is_string($c)) {
                $c = [
                    'name' => $c,
                ];
            }
            $columnName = $c['name'];
            if (!$block = $c['block'] ?? false) {
                $block = $columnName;
            }
            $fixDotColumnName = str_replace('.', '_', $block);
            if (array_key_exists($fixDotColumnName, $customColumnTemplates)) {
                $c['twigTemplate'] = $customColumnTemplates[$fixDotColumnName];
            }
            assert(is_array($c));
            $column = new Column(...$c);
            if (in_array($columnName, $settings)) {
                $options = (new OptionsResolver())
                    ->setDefaults([
                        'searchable' => false,
                        'sortable' => false,
                        'browsable' => false
                    ])->resolve($settings);
                $column->searchable = $options['searchable'];
                $column->sortable = $options['sortable'];
                $column->browsable = $options['browsable'];
            }
            $normalizedColumns[] = $column;
            //            $normalizedColumns[$column->name] = $column;
        }
        return $normalizedColumns;
    }

    public function getSettingsFromAttributes(string $class)
    {
        assert(class_exists($class), $class);
        $reflector = new \ReflectionClass($class);
        foreach ($reflector->getAttributes() as $attribute) {
            if (!u($attribute->getName())->endsWith('ApiFilter')) {
                continue;
            }
            // dd($attribute);

            // $filter = $attribute->getArguments()[0];
            // if (u($filter)->endsWith('OrderFilter')) {
            //     $orderProperties = $attribute->getArguments()['properties'];
            //     return $orderProperties;
            //            dd($attribute);
            /** @var FilterInterface $filter */
            $arguments = $attribute->getArguments();
            $filter = $arguments[0];
            $settings = [];
            if (!array_key_exists('properties', $arguments)) {
                continue;
//                dd($arguments);
            }
            foreach ($arguments['properties'] as $fieldname) {
                if (in_array($filter, [RangeFilter::class, SearchFilter::class])) {
                    $settings[$fieldname]['searchable'] = true;
                }
                if (in_array($filter, [SearchFilter::class])) {
                    $settings[$fieldname]['browsable'] = true;
                }
                if (in_array($filter, [MultiFieldSearchFilter::class])) {
                    $settings[$fieldname]['searchable'] = true;
                }
                if (in_array($filter, [OrderFilter::class])) {
                    $settings[$fieldname]['sortable'] = true;
                }
            }
        }

        return $settings;
    }


    public function sortableFields(string $class): array
    {
        assert(class_exists($class), $class);
        $reflector = new \ReflectionClass($class);
        foreach ($reflector->getAttributes() as $attribute) {
            if (!u($attribute->getName())->endsWith('ApiFilter')) {
                continue;
            }
            if (u($filter)->endsWith('OrderFilter')) {
                $orderProperties = $attribute->getArguments()['properties'];
                return $orderProperties;
            }
        }
        return [];
    }

    public function searchableFields(string $class): array
    {
        $reflector = new \ReflectionClass($class);
        $searchableFields = [];
        foreach ($reflector->getAttributes() as $attribute) {
            if (!u($attribute->getName())->endsWith('ApiFilter')) {
                continue;
            }
            $filter = $attribute->getArguments()[0];
//            if (u($filter)->endsWith('MultiFieldSearchFilter')) {
//                $fields = $attribute->getArguments()['properties'];
//                $searchableFields = array_merge($searchableFields,$fields );
//            }
            if (in_array($filter, [RangeFilter::class, SearchFilter::class, MultiFieldSearchFilter::class])) {
                $fields = $attribute->getArguments()['properties'];
                $searchableFields = array_merge($searchableFields, $fields);
                if ($filter === SearchFilter::class) {
//                    dd($searchFields, $filter);
                }
            }
        }
//        dd($searchableFields);

        return $searchableFields;
    }

    public function searchBuilderFields(string $class, array $normalizedColumns): array
    {
        $reflector = new \ReflectionClass($class);
        $columnNumbers = [];
        foreach ($reflector->getAttributes() as $attribute) {

            if (!u($attribute->getName())->endsWith('ApiFilter')) {
                continue;
            }
            $filterClass = $attribute->getName();
            $filterReflector = new \ReflectionClass($filterClass);
// this is the Doctrine ORM interface ONLY
//            if ($reflector->implementsInterface(FilterInterface::class))
//            {
//                dd("Yep!");
//            }

            $filter = $attribute->getArguments()[0];
// @todo: handle other filters
            if (in_array($filter, [RangeFilter::class, SearchFilter::class])) {
                $searchFields = $attribute->getArguments()['properties'];
                if ($filter === SearchFilter::class) {
//                    dd($searchFields, $filter);
                }
                foreach ($normalizedColumns as $idx => $column) {
//                    dump($column->name);
                    if (in_array($column->name, $searchFields)) {
                        $columnNumbers[] = $idx;
                    }

//                    if (array_key_exists($column->name, $searchFields)) {
//                        $columnNumbers[] = $idx;
//                    }
                }
            }
            dd($columnNumbers);
        }


        return $columnNumbers;
    }


}

