# Implementation Details

This bundle is intentionally "thin" at the template level: the Twig component mostly serializes configuration to HTML `data-*` attributes, and a Stimulus controller does the actual DataTables wiring.

The server-side work is handled by API Platform (Doctrine state provider and pagination) plus a custom normalizer for DataTables-friendly facet payloads.

## Main Pieces

### Twig component

- Component: `api_grid`
- Class: `Survos\ApiGridBundle\Components\ApiGridComponent` (`src/Components/ApiGridComponent.php`)
- Template: `templates/components/api_grid.html.twig`

Responsibilities:

- accept `class`, `apiGetCollectionUrl`, `columns`, `filter`, `buttons` and other UI options
- normalize `columns` into `Survos\ApiGridBundle\Model\Column`
- extract inline `<twig:block name="...">` templates from the caller template (when `caller` is provided)
- render `templates/components/_datatable_with_facets.html.twig`, which attaches the Stimulus controller

Important runtime behavior:

- `apiGetCollectionUrl` is the preferred way to define the endpoint (Doctrine-first).
- `apiRoute` exists only for backward compatibility and requires a route discovery layer; avoid it.

### Column normalization

Service: `Survos\ApiGridBundle\Service\DatatableService` (`src/Service/DatatableService.php`)

Inputs:

- explicit column config from Twig (`columns`, `facet_columns`)
- inferred settings from PHP attributes on the resource class (e.g. `#[ApiFilter(OrderFilter::class,...)]`)
- twig block templates extracted from the caller

Output:

- an array of `Column` objects used to build the DataTables column list.

### Stimulus + DataTables

Controller: `@survos/api-grid-bundle/api_grid`

- file: `assets/src/controllers/api_grid_controller.js`

Responsibilities:

- parse the JSON column configuration and facet configuration passed by Twig
- create a DataTables instance with `serverSide` behavior
- translate DataTables parameters into API Platform query parameters:
  - `limit` and `offset`
  - `order[field]=asc|desc`
  - global `search` (for `MultiFieldSearchFilter`)
  - `facet_filter[]` for SearchPanes selections
  - ColumnControl per-column list filters are also mapped to `facet_filter[]`
- call the API endpoint using `axios` (default `Accept: application/ld+json`)

Custom rendering:

- if a column has a `twigTemplate` string, the controller compiles it with `twig` (twig.js)
- the compiled render function receives `row`, `data`, `column`, `globals`, etc.

### Pagination and collection normalization

#### `limit`/`offset`

`assets/src/controllers/api_grid_controller.js` sends `limit` and `offset`.

The bundle provides `Survos\ApiGridBundle\Paginator\SlicePaginationExtension` (`src/Paginator/SlicePaginationExtension.php`) which reads `limit` and `offset` from the request and applies them to the Doctrine query.

#### Hydra JSON-LD + facets payload

Normalizer: `Survos\ApiGridBundle\Hydra\Serializer\DataTableCollectionNormalizer` (`src/Hydra/Serializer/DataTableCollectionNormalizer.php`)

Responsibilities:

- normalize collections as JSON-LD (`member`, `totalItems`, `view`, etc.)
- when `facets[]` are requested, compute facet counts and return them in a DataTables SearchPanes-friendly structure

ColumnControl integration:

- when `facets[]` are requested, the normalizer also returns a `columnControl` option payload (lists of `{label,value}`)
- the Stimulus controller forwards that payload into the DataTables Ajax JSON so ColumnControl can populate `searchList`

Doctrine facet counts depend on repository support for a `getCounts($field)` method. In Survos projects this typically comes from `Survos\CoreBundle\Traits\QueryBuilderHelperTrait`.

## Doctrine-first Best Practice

1) Define a named `GetCollection` operation as a constant (e.g. `Video::DOCTRINE_ROUTE = 'api-video'`).

2) In the controller, compute the URL explicitly:

```php
$apiGetCollectionUrl = $iriConverter->getIriFromResource(
    Video::class,
    operation: new GetCollection(name: Video::DOCTRINE_ROUTE)
);
```

3) In Twig, render:

```twig
<twig:api_grid
    :class="class"
    :apiGetCollectionUrl="apiGetCollectionUrl"
    :columns="columns"
    :caller="_self"
/>
```

This avoids route discovery layers and keeps failures obvious: if the API route is wrong, you'll see the URL directly in the browser/network tab.
