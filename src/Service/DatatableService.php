<?php

declare(strict_types=1);

namespace Survos\ApiGridBundle\Service;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\ORM\Mapping\Id;
use Survos\ApiGridBundle\Api\Filter\FacetsFieldSearchFilter;
use Survos\ApiGridBundle\Api\Filter\MultiFieldSearchFilter;
use Survos\ApiGridBundle\Attribute\Facet;
use Survos\ApiGridBundle\Attribute\MeiliId;
use Survos\FieldBundle\Attribute\Field;
use Survos\FieldBundle\Service\FieldReader;
use Survos\ApiGridBundle\Filter\MeiliSearch\SortFilter;
use Survos\ApiGridBundle\Model\Column;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\Attribute\Groups;
use function Symfony\Component\String\u;
use Survos\ApiGridBundle\Filter\MeiliSearch\MultiFieldSearchFilter as MeiliMultiFieldSearchFilter;

class DatatableService
{
    public function __construct(
        private readonly ?FieldReader $fieldReader = null,
    ) {
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
            // Apply #[Field] settings as defaults — explicit col() args override via ??=.
            // col('code') inherits searchable/sortable/browsable/visible/width from #[Field].
            // col('code', searchable: false) explicitly disables even if #[Field] says true.
            if ($fieldSettings = $settings[$columnName] ?? null) {
                $column->searchable ??= $fieldSettings['searchable'] ?? false;
                $column->sortable   ??= $fieldSettings['sortable']   ?? false;
                $column->browsable  ??= $fieldSettings['browsable']  ?? false;
                $column->visible    ??= $fieldSettings['visible']    ?? null;
                $column->width      ??= $fieldSettings['width']      ?? null;
                $column->widget     ??= $fieldSettings['widget']     ?? null;
                if (empty($column->title) || $column->title === $column->name) {
                    $column->title = $fieldSettings['title'] ?? $column->name;
                }
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
        $rc = new \ReflectionClass($class);
        $settings = [];

        // ── Layer 1: #[Field] via FieldReader (authoritative when present) ───────
        if ($this->fieldReader !== null) {
            foreach ($this->fieldReader->getDescriptors($class) as $descriptor) {
                $name = $descriptor->name;
                $settings[$name] = array_merge($settings[$name] ?? [], array_filter([
                    'name'       => $name,
                    'title'      => $descriptor->getFallbackLabel(),
                    'searchable' => $descriptor->searchable ?: null,
                    'sortable'   => $descriptor->sortable   ?: null,
                    'browsable'  => ($descriptor->filterable && ($descriptor->resolvedWidget()?->isBrowsable() ?? false)) ?: null,
                    'visible'    => $descriptor->visible === false ? false : null,
                    'width'      => $descriptor->width,
                    'widget'     => $descriptor->resolvedWidget()?->value,
                    'renderType' => $descriptor->isUrl ? 'url' : ($descriptor->isEmail ? 'email' : null),
                ], fn ($v) => $v !== null));
            }
        }

        // ── Layer 2: class-level #[ApiFilter] (fallback for unannotated entities) ─
        // #[ApiFilter] on the class with a properties array is still valid in AP4/5.
        foreach ($rc->getAttributes() as $attribute) {
            if (!u($attribute->getName())->endsWith('ApiFilter')) {
                continue;
            }
            /** @var ApiFilter $apiFilter */
            $apiFilter   = $attribute->newInstance();
            $filterClass = $apiFilter->filterClass;
            if (!$filterClass) {
                continue;
            }
            foreach ($apiFilter->properties as $property => $strategy) {
                if (is_int($property)) {
                    $property = $strategy; // list-style: ['code', 'name']
                }
                $settings[$property] ??= ['name' => $property];
                match (true) {
                    is_a($filterClass, OrderFilter::class, true)                           => $settings[$property]['sortable']   = true,
                    is_a($filterClass, SearchFilter::class, true)                          => $settings[$property]['searchable'] = true,
                    is_a($filterClass, FacetsFieldSearchFilter::class, true)               => $settings[$property]['browsable']  = true,
                    str_ends_with($filterClass, '\\FacetsFieldSearchFilter')               => $settings[$property]['browsable']  = true,
                    default                                                                => null,
                };
            }
        }

        // ── Layer 3: property-level legacy attributes (#[Facet], #[MeiliId], #[ApiProperty identifier]) ─
        foreach ($rc->getProperties() as $property) {
            $name = $property->getName();
            foreach ($property->getAttributes() as $attribute) {
                match ($attribute->getName()) {
                    MeiliId::class, Id::class => $settings[$name]['is_primary'] = true,
                    Facet::class              => $settings[$name]['browsable']  = true,
                    ApiProperty::class        => $attribute->getArguments()['identifier'] ?? false
                                                    ? ($settings[$name]['is_primary'] = true)
                                                    : null,
                    default                   => null,
                };
            }
        }

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
