# Survos API Grid Bundle

Render a spreadsheet-like, server-driven DataTables.net grid from an API Platform collection endpoint.

This bundle used to be central across multiple projects. Today the primary goal is simple Doctrine browsing (sorting, searching, filtering) using a single Twig component.

Meilisearch support exists, but the recommended starting point is Doctrine + API Platform.

## Install

```bash
composer req survos/api-grid-bundle
```

## Quick Start (Doctrine)

The recommended approach is:

- pass the entity class from your controller (avoid class strings in Twig)
- pass an explicit API Platform collection URL (`apiGetCollectionUrl`)

### 1) Add a named collection operation constant

Define a route name constant on your ApiResource (this keeps things easy to reason about):

```php
// src/Entity/Video.php

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
#[GetCollection(name: self::DOCTRINE_ROUTE)]
class Video
{
    public const DOCTRINE_ROUTE = 'api-video';
}
```

### 2) Compute the collection URL in your controller

```php
// src/Controller/VideoController.php

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use App\Entity\Video;

#[Route(path: '/video/browse', name: 'video_browse', methods: ['GET'])]
public function browse(IriConverterInterface $iriConverter): Response
{
    $apiGetCollectionUrl = $iriConverter->getIriFromResource(
        Video::class,
        operation: new GetCollection(name: Video::DOCTRINE_ROUTE)
    );

    return $this->render('video/browse.html.twig', [
        'class' => Video::class,
        'apiGetCollectionUrl' => $apiGetCollectionUrl,
    ]);
}
```

### 3) Render the grid in Twig

```twig
{# templates/video/browse.html.twig #}

{% set columns = [
    col('youtubeId'),
    col(name: 'year', sortable: true, browsable: true),
    col(name: 'title', searchable: true, sortable: true),
] %}

<twig:api_grid
    :class="class"
    :apiGetCollectionUrl="apiGetCollectionUrl"
    :columns="columns"
    :caller="_self"
>
    <twig:block name="youtubeId">
        <a target="_blank" href="https://www.youtube.com/watch?v={{ row.youtubeId }}">
            <img src="{{ row.thumbnailUrl }}" height="60" alt="{{ row.youtubeId }}"/>
        </a>
    </twig:block>
</twig:api_grid>
```

Notes:

- `browsable: true` marks a field as a SearchPanes facet.
- `:caller="_self"` enables inline `<twig:block name="...">` templates for custom rendering.

## EasyAdmin / Sidebar Layouts: ColumnControl

SearchPanes are great, but the left-hand facet sidebar can clash with admin layouts that already have a sidebar (e.g. EasyAdmin).

Enable DataTables ColumnControl instead:

```twig
<twig:api_grid
    :class="class"
    :apiGetCollectionUrl="apiGetCollectionUrl"
    :columns="columns"
    :caller="_self"
    :searchPanes="false"
    :columnControl="true"
/>
```

How it works:

- `browsable: true` columns become ColumnControl `searchList` dropdowns.
- Selections are sent back as `facet_filter[]` so the existing Doctrine filter path is reused.

## Backend Setup

### Search (global search box)

The JS grid sends a global query as `?search=...`. Enable it with:

```php
use ApiPlatform\Metadata\ApiFilter;
use Survos\ApiGridBundle\Api\Filter\MultiFieldSearchFilter;

#[ApiFilter(MultiFieldSearchFilter::class, properties: ['title', 'description'])]
```

### Sorting

DataTables sends ordering as `order[field]=asc|desc`, so add API Platform's `OrderFilter`:

```php
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;

#[ApiFilter(OrderFilter::class, properties: ['title', 'year'])]
```

### Facets / SearchPanes (Doctrine)

SearchPanes filtering uses `facet_filter[]` (e.g. `school,in,Lincoln|Roosevelt`). Add a filter that understands that parameter.

If you use `Survos\MeiliBundle\Api\Filter\FacetsFieldSearchFilter` in your project, it works fine for Doctrine too.

To generate facet counts for Doctrine SearchPanes, your repository should support counts:

```php
use Survos\CoreBundle\Traits\QueryBuilderHelperInterface;
use Survos\CoreBundle\Traits\QueryBuilderHelperTrait;

class VideoRepository extends ServiceEntityRepository implements QueryBuilderHelperInterface
{
    use QueryBuilderHelperTrait;
}
```

## Implementation Notes

See `docs/implementation.md` for how the Twig component, Stimulus controller, API Platform normalizer, and pagination extension fit together.
