<?php

namespace Survos\ApiGrid\Filter\MeiliSearch;

use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

final class DataTableFilter extends AbstractSearchFilter implements FilterInterface
{
    public function __construct(PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
                                PropertyMetadataFactoryInterface $propertyMetadataFactory,
                                ?ResourceClassResolverInterfacexx $resourceClassResolverxx=null,
                                ?NameConverterInterface $nameConverter = null,
                                private readonly string $orderParameterName = 'filter',
                                ?array $properties = null)
    {
//        parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $resourceClassResolver, $nameConverter, $properties);
    }

    public function apply(array $clauseBody, string $resourceClass, ?Operation $operation = null, array $context = []): array {

        if(isset($context['filters']['attributes'])) {
            $filterAttributes = "";
            foreach($context['filters']['attributes'] as $attribute) {
                $filterAttributes .= " ".str_replace(",", " ", $attribute)." AND";
            }
            $clauseBody['filter'] = rtrim($filterAttributes, "AND");
        }

        if(isset($context['filters']['searchBuilder'])) {
            $filter = isset($clauseBody['filter'])? $clauseBody['filter'] :"";

            $searchBuilder = $context['filters']['searchBuilder'];
            if(isset($searchBuilder['logic']) && isset($searchBuilder['criteria'])) {
                $dataTableFilter = $this->criteria($searchBuilder['logic'], $searchBuilder['criteria']);
                $clauseBody['filter'] = ($filter != "")?$filter." AND ".$dataTableFilter:$filter.$dataTableFilter;
            }
        }

        return $clauseBody;
    }

    private function criteria(string $logic, array $criterias) {
        $query = " ( ";
        foreach($criterias as $criteria) {
            if(isset($criteria["condition"])) {
                $query .= " ".$this->matchConditionWithName($criteria)." ".$logic ;
            }
        }
        $query = rtrim($query, $logic)." ) ";
        return $query;
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }

    public function matchConditionWithName(array $condition) {

        switch($condition["condition"]) {
            case "=":
                return $condition["origData"]." = ".$condition["value1"];
            case "!=":
                return $condition["origData"]." != ".$condition["value1"];
            case "<" :
                return $condition["origData"]." < ".$condition["value1"];
            case "<=" :
                return $condition["origData"]." <= ".$condition["value1"];
            case ">=" :
                return $condition["origData"]." >= ".$condition["value1"];
            case ">" :
                return $condition["origData"]." > ".$condition["value1"];
            case "between" :
                return $this->betweenCondtion($condition["value1"], $condition["value2"], $condition["origData"]);
            case "!between" :
                return $this->betweenCondtion($condition["value2"], $condition["value1"], $condition["origData"]);
            case "null" :
                return $condition["origData"]." = NULL";
            case "!null" :
                return $condition["origData"]." != NULL";
            case "starts" :
//                https://www.meilisearch.com/docs/learn/filtering_and_sorting/filter_expression_reference#starts-with
                return $condition["origData"]." STARTS WITH ".$condition["value1"];
            default:
                return $condition["origData"]." ".$condition["condition"]." ".$condition[0];
        }
    }

    private function betweenCondtion(?string $value1, ?string $value2, $keyName){

        $firstPart = "";

        if($value1 > $value2) {
            $tempValue = $value1;
            $value2 = $value1;
            $value1 = $tempValue;
        }

        if($value1) {
            $firstPart = " ".$keyName." > ".$value1." AND";
        }

        $secondPart = "";
        if($value2) {
            $secondPart = " ".$keyName." < ".$value2." ";
        }

        $finalCondition = rtrim($firstPart.$secondPart, "AND");

        return ($finalCondition  != "")? " ( ".$finalCondition." ) ": " ";
    }
}
