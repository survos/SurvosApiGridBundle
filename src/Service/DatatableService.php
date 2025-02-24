<?php

declare(strict_types=1);

namespace Survos\ApiGrid\Service;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\ORM\Mapping\Id;
use Survos\ApiGrid\Api\Filter\FacetsFieldSearchFilter;
use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;
use Survos\ApiGrid\Attribute\Facet;
use Survos\ApiGrid\Attribute\MeiliId;
use Survos\ApiGrid\Filter\MeiliSearch\SortFilter;
use Survos\ApiGrid\Model\Column;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\Attribute\Groups;
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
            // if

            if (is_string($c)) {
                $c = [
                    'order' => ($idx+1) * 10,
                    'name' => $c,
                ];
            }
            if (is_object($c)) {
                assert($c::class == Column::class);
                $column = $c;
                $columnName = $column->name;
                // ugh, duplicated.  need to separate and have application-specific templates
                if (array_key_exists($columnName, $customColumnTemplates)) {
                    $c->twigTemplate = $customColumnTemplates[$columnName];
                }
            } else {
                if (!array_key_exists('name', $c)) {
                    continue;
                    dd("mssing name in " . join('|', array_keys($c)), $columns, $idx, $c);
                }
                assert(array_key_exists('name', $c), json_encode($c));
                $columnName = $c['name'];
                if (!$block = $c['block'] ?? false) {
                    $block = $columnName;
                }
//                if ($columnName <> $block) { dd($block, $columnName); }
                $fixDotColumnName = str_replace('.', '_', $block);
                if (array_key_exists($fixDotColumnName, $customColumnTemplates)) {
                    $c['twigTemplate'] = $customColumnTemplates[$fixDotColumnName];
                }
                assert(is_array($c));
                unset($c['propertyConfig']);
//            dd($c);

                $column = new Column(...$c);

            }
            $existingSettings = $settings[$columnName]??null;
            if ($existingSettings) {
                $options = (new OptionsResolver())
                    ->setDefaults([
                        'name' => null,
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
    public function getSettingsFromAttributes(string $class): array
    {
        assert(class_exists($class), $class);
        $reflectionClass = new \ReflectionClass($class);
        $settings = [];
        // class attributes first.
        foreach ($reflectionClass->getAttributes() as $attribute) {

            //
            if (!u($attribute->getName())->endsWith('ApiFilter')) {
                continue;
            }

            // $filter = $attribute->getArguments()[0];
            // if (u($filter)->endsWith('OrderFilter')) {
            //     $orderProperties = $attribute->getArguments()['properties'];
            //     return $orderProperties;
            //            dd($attribute);
            /** @var FilterInterface $filter */
            $arguments = $attribute->getArguments();

            /** @var ApiFilter $attrInstance */
            $attrInstance = $attribute->newInstance();
//            dump($attribute->newInstance(), $arguments, $attribute->getName(), $attribute);
            $filter = $attrInstance->filterClass;
            if (!$filter) {
                dd($arguments, $attribute->getName(), $attribute);
                continue;
                return [];
            }
            $properties = $attrInstance->properties;
            foreach ($properties as $property) {
                if (!array_key_exists($property, $settings)) {
                    $settings[$property] = [
                        'name' => $property,
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
                        assert(false, "why not OrderFilter?");
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
                if (in_array($attribute->getName(), [MeiliId::class, Id::class])) {
                    $settings[$fieldname]['is_primary'] = true;
                }
                if ($attribute->getName() == ApiProperty::class) {
                    if ($attribute->getArguments()['identifier']??false) {
                        $settings[$fieldname]['is_primary'] = true;
                    }
                }
                if ($attribute->getName() == Facet::class) {
                    $settings[$fieldname]['browsable'] = true;
                }

                if ($attribute->getName() == ApiFilter::class) {
                    $attrInstance = $attribute->newInstance();
                    switch ($filterClass = $attrInstance->filterClass) {
                        case OrderFilter::class:
                            $settings[$fieldname]['sortable'] = true;
                            break;
                        case SearchFilter::class:
                            $settings[$fieldname]['searchable'] = true;
                            break;
                        default:
                            dd("@todo: handle " . $filterClass);
                    }
                }
            }
        }

        // now go through each property, including getting the primary key
        // something's still not right here!  Like setting browsable on getRp.
        foreach ($reflectionClass->getMethods() as $method) {
            $fieldname = $method->getName();
            foreach ($method->getAttributes() as $attribute) {
                if ($attribute->getName() == Facet::class) {
//                if ($attribute->getName() == Groups::class) {
//                    dump($settings, $fieldname, $attribute);
                    $settings[$fieldname]['browsable'] = true;
                }
            }
        }
        // @todo: methods
        return $settings;
    }


    public function sortableFields(?string $class): array
    {

        assert(class_exists($class), $class);
        $reflector = new \ReflectionClass($class);
        foreach ($reflector->getAttributes() as $attribute) {
            $filter = $attribute->getName();
            if (!u($filter)->endsWith('ApiFilter')) {
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
            $attrInstance = $attribute->newInstance();
            $filter = $attrInstance->filterClass;
//            if (u($filter)->endsWith('MultiFieldSearchFilter')) {
//                $fields = $attribute->getArguments()['properties'];
//                $searchableFields = array_merge($searchableFields,$fields );
//            }
            if (in_array($filter, [RangeFilter::class, SearchFilter::class, MultiFieldSearchFilter::class])) {
                $fields = $attrInstance->properties;
                $searchableFields = array_merge($searchableFields, $fields);
            }
        }
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
            $attrInstance = $attribute->newInstance();
            $filter = $attrInstance->filterClass;
// @todo: handle other filters
            if (in_array($filter, [RangeFilter::class, SearchFilter::class])) {
                $searchFields = $attrInstance->properties;
                foreach ($normalizedColumns as $idx => $column) {
                    if (in_array($column->name, $searchFields)) {
                        $columnNumbers[] = $idx;
                    }
                }
            }
        }


        return $columnNumbers;
    }


}

