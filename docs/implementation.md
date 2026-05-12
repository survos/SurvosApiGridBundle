# Implementation Details

This bundle is intentionally "thin" at the template level: the Twig component serializes configuration to HTML `data-*` attributes, and a Stimulus controller does the actual DataTables wiring.

Server-side work is handled by API Platform (Doctrine state provider and pagination) plus a custom normalizer for DataTables-friendly facet payloads.

## Main Pieces

### Twig component

- Component: `api_grid`
- Class: `Survos\ApiGridBundle\Components\ApiGridComponent` (`src/Components/ApiGridComponent.php`)
- Template: `templates/components/api_grid.html.twig`

Responsibilities:

- accept `class`, `apiGetCollectionUrl`, `columns`, `filter`, `buttons`, and other UI options
- normalize `columns` into `Survos\ApiGridBundle\Model\Column` objects via `DatatableService`
- extract inline `<twig:block name="...">` templates from the caller template (when `caller` is provided)
- render `templates/components/_datatable_with_facets.html.twig`, which attaches the Stimulus controller

`apiGetCollectionUrl` is the only supported way to define the endpoint. `apiRoute` throws a `RuntimeException` — pass the URL directly.

### Column normalization

Service: `Survos\ApiGridBundle\Service\DatatableService` (`src/Service/DatatableService.php`)

Settings are resolved in three layers (lowest to highest priority):

1. **`#[Field]` via `FieldReader`** — when `survos/field-bundle` is installed, `FieldDescriptor` objects are the authoritative source for `searchable`, `sortable`, `browsable`, `visible`, `width`, `widget`.
2. **Class-level `#[ApiFilter]`** — fallback for unannotated entities; `OrderFilter` → sortable, `MultiFieldSearchFilter` / `SearchFilter` → searchable, `FacetsFieldSearchFilter` → browsable.
3. **Explicit `col()` args in Twig** — `null` arguments fall through to layers 1/2; `true`/`false` always win.

Output is an array of `Column` objects consumed by the Stimulus controller.

### Stimulus + DataTables

Controller: `@survos/api-grid/api_grid`  
File: `assets/src/controllers/api_grid_controller.js`

Responsibilities:

- parse JSON column configuration and facet configuration from Twig `data-*` values
- initialize a DataTables 3.0 instance with `serverSide: true`
- translate DataTables parameters into API Platform query parameters:
  - `itemsPerPage`, `page`, `limit`, `offset`
  - `order[field]=asc|desc`
  - per-property search (e.g. `title=Bohr`) for searchable columns, or `search=…` as a fallback for `MultiFieldSearchFilter`
  - ColumnControl per-column list filters: `field[]=val1&field[]=val2`
  - ColumnControl range inputs: `field[gte]=…&field[lte]=…`
  - SearchBuilder criteria: forwarded as `?searchBuilder=<json>`
- fetch the API endpoint with `Accept: application/ld+json` and feed results back to DataTables
- compile and execute per-column `twigTemplate` strings via `@tacman1123/twig-browser` (the js-twig engine)

Custom rendering:

- column `twigTemplate` strings are compiled by the twig-browser engine
- the render function receives `row`, `data`, `column`, `globals`, `field_name`
- `path()` is provided via `@survos/js-twig/generated/fos_routes.js` (FOS JS Routing)

### Pagination and collection normalization

#### `limit` / `offset`

The Stimulus controller sends `itemsPerPage`, `page`, `limit`, and `offset`. The bundle provides `SlicePaginationExtension` (`src/Paginator/SlicePaginationExtension.php`) which reads `limit` and `offset` and applies them to the Doctrine query.

#### Hydra JSON-LD + facets payload

Normalizer: `Survos\ApiGridBundle\Hydra\Serializer\DataTableCollectionNormalizer` (`src/Hydra/Serializer/DataTableCollectionNormalizer.php`)

Responsibilities:

- normalize collections in a format DataTables understands (`member`, `totalItems`, etc.)
- when `facets[]` query parameters are present, compute per-field distinct value counts and return them in the response
- include a `columnControl` map (`{field: [{label, value}]}`) so ColumnControl can populate `searchList` dropdowns without a separate request

Doctrine facet counts require a repository method provided by `Survos\CoreBundle\Traits\QueryBuilderHelperTrait`.

### ColumnControl

Plugin: `datatables.net-columncontrol-bs5` (DataTables 3.0 beta)

The plugin is loaded via a dynamic `import()` in `datatables-plugins.js`. If the package is absent, `dtPlugins.columnControl` stays `false` and the layout falls back to a plain search box.

Column content is configured per-column in `columnDefs()`:

- `browsable` columns: sort button + searchable dropdown (`searchList` with `ajaxOnly` server-populated values)
- `searchable` (not browsable) columns: sort button + free-text input
- `widget: 'range'` columns: sort button + min/max number inputs (custom `numberRange` content type)
- All other columns: sort button only (global default)

ColumnControl dropdowns are portaled to `document.body` to avoid clipping in `position: relative` containers.

### SearchBuilder

Plugin: `datatables.net-searchbuilder-bs5` (DataTables 3.0 beta)

Enabled when `searchBuilder=true` on the component. The `searchBuilderColumns` array (indexes of `browsable` columns) is passed to DataTables to restrict which criteria are offered. SearchBuilder criteria are serialized as JSON and sent to the API as `?searchBuilder=…`.

## Doctrine-First Best Practice

1. Define a named `GetCollection` as a constant on the entity:

```php
#[GetCollection(name: self::COLLECTION_ROUTE)]
class Video
{
    public const COLLECTION_ROUTE = 'api-video';
}
```

2. Resolve the URL in the controller:

```php
$apiGetCollectionUrl = $iriConverter->getIriFromResource(
    Video::class,
    operation: new GetCollection(name: Video::COLLECTION_ROUTE)
);
```

3. Pass it to the component:

```twig
<twig:api_grid :class="class" :apiGetCollectionUrl="apiGetCollectionUrl" :columns="columns" :caller="_self" />
```

This keeps failures obvious — a wrong URL shows immediately in the browser network tab rather than triggering a cryptic route discovery error.
