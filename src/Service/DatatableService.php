<?php

declare(strict_types=1);

namespace Survos\ApiGrid\Service;

use ApiPlatform\Api\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\ORM\Mapping\Id;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorDynamicHTML;
use Picqer\Barcode\BarcodeGeneratorHTML;
use Picqer\Barcode\BarcodeGeneratorJPG;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Survos\ApiGrid\Api\Filter\FacetsFieldSearchFilter;
use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;
use Survos\ApiGrid\Filter\MeiliSearch\SortFilter;
use Survos\ApiGrid\Model\Column;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Survos\ApiGrid\Filter\MeiliSearch\MultiFieldSearchFilter as MeiliMultiFieldSearchFilter;
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

            if (in_array($columnName, array_keys($settings))) {
//                $options = (new OptionsResolver())
//                    ->setDefaults([
//                        'searchable' => false,
//                        'order' => 100,
//                        'sortable' => false,
//                        'browsable' => false
//                    ])->resolve($settings);

                $column->searchable = $settings[$columnName]['searchable'];
                $column->sortable = $settings[$columnName]['sortable'];
                $column->browsable = $settings[$columnName]['browsable'];

            }

            if ($column->condition) {
                $normalizedColumns[] = $column;
            }
//                            if ($c['name'] == 'image_count') dd($c, $column);


            //            $normalizedColumns[$column->name] = $column;
        }
        return $normalizedColumns;
    }

    public function getSettingsFromAttributes(string $class)
    {
        assert(class_exists($class), $class);
        $reflectionClass = new \ReflectionClass($class);
        $settings = [];
        $filters = [];
        // the class attributes have groups of fields.  We will also go through each property and method.
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
            $filter = $arguments[0];
            if (!array_key_exists('properties', $arguments)) {
                dd($arguments);
                continue;
            }
            $properties = $arguments['properties'] ;
//            dump(props: $properties, filter: $filter);

            foreach ($properties as $fieldname) {
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

                //Meili search filters
                if (in_array($filter, [MeiliMultiFieldSearchFilter::class])) {
                    $settings[$fieldname]['searchable'] = true;
                }
                if (in_array($filter, [FacetsFieldSearchFilter::class])) {
                    $settings[$fieldname]['browsable'] = true;
                }
                if (in_array($filter, [SortFilter::class])) {
                    $settings[$fieldname]['sortable'] = true;
                }
            }
        }

        // now go through each property, including getting the primary key
        foreach ($reflectionClass->getProperties() as $property) {
            $fieldname = $property->getName();
            foreach ($property->getAttributes() as $attribute) {
                if ($attribute->getName() == Id::class) {
                    $settings[$fieldname]['is_primary'] = true;
                }
            }
        }
        return $this->addDefaultValues($settings);
    }

    public function addDefaultValues(array $settings) {
        foreach ($settings as $key => $value) {
            if(!isset($settings[$key]['browsable'])) {
                $settings[$key]['browsable'] = false;
            }
            if(!isset($settings[$key]['sortable'])) {
                $settings[$key]['sortable'] = false;
            }
            if(!isset($settings[$key]['searchable'])) {
                $settings[$key]['searchable'] = false;
            }
        }
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

