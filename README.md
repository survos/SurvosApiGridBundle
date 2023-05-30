# Survos API Grid Bundle

Use the DataTables.net javascript library with Symfony, Twig and API Platform.
requires survos/grid-bundle, which loads the datatables libraries.

```bash
composer req survos/api-grid-bundle
```

## 

## Ideas

Import the datasets at https://domohelp.domo.com/hc/en-us/articles/360043931814-Fun-Sample-DataSets
https://www.mytechylife.com/2015/09/29/next-and-previous-row-with-jquery-datatables/
https://github.com/lerocha/chinook-database
http://2016.padjo.org/tutorials/sqlite-data-starterpacks/#more-info-simplefolks-for-simple-sql

# Dev only...

composer config repositories.survos_grid_bundle '{"type": "vcs", "url": "git@github.com:survos/SurvosApiGridBundle.git"}'

# setup
Inorder to use api-grid we need to follow below:

**1) Add Survos\CoreBundle\Traits\QueryBuilderHelperTrait in your repository class of the entity for which you want apiGrid**

**2) you need to set columns variable like below:**
```
        {% set columns = [
        'code',
        'description',
        {name: 'code', sortable: true},
        'description',
        {name: 'countrycode', sortable: true,  browsable: true, searchable: true},
        {name: 'privacyPolicy', browsable: true},
        {name: 'projectLocale', browsable: true},
        ] %}
```
By default, sortable, browsable, searchable are false.

For those columns you want sortable add sortable: true

For those columns you want to add searchPanes add browsable: true

For those columns you want to add searchable: true

**3) For search you need to inclide below**
```
use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;


#[ApiFilter(MultiFieldSearchFilter::class, properties: ['name', 'code'])]
```
Here name and code are columns in which you need to search

**4) Use below for doctrine searchpane filters**
```
use Survos\ApiGrid\Api\Filter\FacetsFieldSearchFilter;


#[ApiFilter(FacetsFieldSearchFilter::class, properties: ['facet_filter'])]
```

**5) Use below for doctrine order filters**
```
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;


#[ApiFilter(OrderFilter::class, properties: ['id','objectCount','countryCode'])]
```
