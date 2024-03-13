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

**3) For search you need to include below**
```
use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;


#[ApiFilter(MultiFieldSearchFilter::class, properties: ['name', 'code'])]
#[ApiFilter(MultiFieldSearchFilter::class, properties: ['firstName', 'lastName', 'officialName'])]
#[ApiFilter(FacetsFieldSearchFilter::class, properties: ['gender', 'currentParty'])]

```
Here name and code are columns in which you need to search

**4) Use below for doctrine searchpane filters**

First, make sure you have the necessary counts method by adding the helper trait to the repository class

```php
class OfficialRepository extends ServiceEntityRepository implements QueryBuilderHelperInterface
{
    use QueryBuilderHelperTrait;
```

Then add the filterable properties to the entity class.  You may want to index them to speed up the count query
```php
use Survos\ApiGrid\Api\Filter\FacetsFieldSearchFilter;


#[ApiFilter(FacetsFieldSearchFilter::class, properties: ['gender','state'])]
```

**5) Use below for doctrine order filters**
```
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;


#[ApiFilter(OrderFilter::class, properties: ['id','objectCount','countryCode'])]
```

```bash
symfony new phpunit-bug --webapp && cd phpunit-bug
composer require --dev symfony/phpunit-bridge
vendor/bin/simple-phpunit
```


API Bug with doctrine

```bash
symfony new api-bug --webapp && cd api-bug
composer config extra.symfony.allow-contrib true
echo "DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db" > .env.local
composer require api
echo "title,string,80,yes," | sed "s/,/\n/g"  | bin/console -a make:entity Book
bin/console doctrine:schema:update --force --complete

symfony server:start -d
symfony open:local --path=/api
```

Now add a Book via post

```bash
curl -X 'POST' \
  'https://127.0.0.1:8000/api/books' \
  -H 'accept: application/ld+json' \
  -H 'Content-Type: application/ld+json' \
  -d '{
  "title": "Symfony Fast Track"
}'
```

## Brainstorm: jstwig component
```twig
<twig:jstwig :data="data>
<section class="containers">
        <h3>
            {{ row.s|length }} Results
        </h3>

    {% for row in row %}
            <div>
                {{- 'Overlay ' ~ i -}}
            </div>
    {% endfor %}
</section>
</twig:jstwig>
```
