<?php

declare(strict_types=1);

namespace Survos\ApiGrid\Service;

use ApiPlatform\Api\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Survos\ApiGrid\Api\Filter\FacetsFieldSearchFilter;
use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;
use Survos\ApiGrid\Attribute\Facet;
use Survos\ApiGrid\Attribute\MeiliId;
use Survos\ApiGrid\Filter\MeiliSearch\SortFilter;
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
    public function normalizedColumns(array $settings, array $columns, array $customColumnTemplates): iterable
    {
        //        $normalizedColumns = parent::normalizedColumns();

        //        dd($customColumnTemplates);
        //        dd($template->getBlockNames());
        //        dd($template->getSourceContext());
        //        dd($template->getBlockNames());
        $normalizedColumns = [];

//        $sortableFields = $this->sortableFields($class);
//        $searchableFields = $this->searchableFields($class);


        foreach ($columns as $idx => $c) {
            if (empty($c)) {
                continue;
            }
            if (is_string($c)) {
                $c = [
                    'order' => ($idx+1) * 10,
                    'name' => $c,
                ];
            }
            if (!array_key_exists('name', $c)) {
                dd($columns, $idx, $c);
            }
            assert(array_key_exists('name', $c), json_encode($c));
            $columnName = $c['name'];
            if (!$block = $c['block'] ?? false) {
                $block = $columnName;
            }
            $fixDotColumnName = str_replace('.', '_', $block);
            if (array_key_exists($fixDotColumnName, $customColumnTemplates)) {
                $c['twigTemplate'] = $customColumnTemplates[$fixDotColumnName];
            }
            assert(is_array($c));
            unset($c['propertyConfig']);
//            dd($c);

            $column = new Column(...$c);
            $existingSettings = $settings[$columnName]??null;
            if ($existingSettings) {
                $options = (new OptionsResolver())
                    ->setDefaults([
                        'searchable' => false,
                        'order' => 100,
                        'sortable' => false,
                        'is_primary' => false,
                        'browsable' => false
                    ])->resolve($existingSettings);
                $column->searchable = $options['searchable'];
                $column->sortable = $options['sortable'];
                $column->browsable = $options['browsable'];
            }
            if ($column->condition) {
                $normalizedColumns[] = $column;
            }
//                            if ($c['name'] == 'image_count') dd($c, $column);


            //            $normalizedColumns[$column->name] = $column;
        }
//        dd($normalizedColumns, $settings);
        return $normalizedColumns;
    }

    public function getFieldsWithAttribute(array $settings, string $internalAttribute)
    {
        $fields = [];
        foreach ($settings as $fieldName => $attributes) {
            if ($attributes[$internalAttribute]??false) {
                $fields[] = $fieldName;
            }
        }
        return $fields;
    }
    public function getSettingsFromAttributes(string $class)
    {
        assert(class_exists($class), $class);
        $reflectionClass = new \ReflectionClass($class);
        $settings = [];
        foreach ($reflectionClass->getAttributes() as $attribute) {

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
            $filter = $arguments[0]??null;
            if (!$filter) {
                return [];
                dd($class);
            }
            if (!array_key_exists('properties', $arguments)) {
                continue;
//                dd($arguments);
            }
            $properties = $arguments['properties'];
            foreach ($properties as $property) {
                if (!array_key_exists($property, $settings)) {
                    $settings[$property] = [
                        'browsable' => false,
                        'sortable' => false,
                        'searchable' => false
                    ];
                }
                switch ($filter) {
                    case FacetsFieldSearchFilter::class:
                        $settings[$property]['browsable'] = true;
                        break;
                    case SortFilter::class:
                    case OrderFilter::class:
                        $settings[$property]['sortable'] = true;
                        break;

                    case SearchFilter::class:
                    case MeiliMultiFieldSearchFilter::class:
                    case RangeFilter::class:
                    case MultiFieldSearchFilter::class:
                        $settings[$property]['searchable'] = true;
                        break;
                }
            }
        }

        // now go through each property, including getting the primary key
        foreach ($reflectionClass->getProperties() as $property) {
            $fieldname = $property->getName();
            foreach ($property->getAttributes() as $attribute) {
                if ($attribute->getName() == MeiliId::class) {
                    $settings[$fieldname]['is_primary'] = true;
                }
                if ($attribute->getName() == Facet::class) {
                    $settings[$fieldname]['browsable'] = true;
                }
            }
        }

//        dd($settings);
        // @todo: methods
        return $settings;
    }


    public function sortableFields(?string $class): array
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

