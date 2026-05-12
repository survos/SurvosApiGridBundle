# Survos API Grid Bundle

A Symfony bundle that renders a server-driven [DataTables 3.0](https://datatables.net/) grid from an API Platform collection endpoint, using Twig Components and a Stimulus controller.

> **Beta notice** — This bundle ships with DataTables 3.0 beta packages (`datatables.net-bs5 3.0.0-beta.2` and related extensions). Beta users get active upstream support from the DataTables team, which means faster bug fixes and direct access to new features.

Key features:

- Zero-config column inference from PHP attributes (`#[Field]`, `#[ApiFilter]`)
- [ColumnControl](docs/widgets.md#columncontrol) — per-column sort / search / facet dropdowns (default)
- [SearchBuilder](docs/widgets.md#searchbuilder) — modal query builder with AND/OR logic
- Responsive, multi-row select, bulk actions, offcanvas detail panel
- Custom cell rendering via inline Twig blocks (server-side Twig, zero JS templating required)
- Bootstrap 5 / Tabler-compatible out of the box

## Requirements

- PHP 8.4+
- Symfony 7.4 or 8.0
- API Platform 4.1+
- `survos/field-bundle` (optional but recommended for attribute-driven config)

## Install

```bash
composer req survos/api-grid-bundle
```

Assets are registered via Symfony UX. Add the Stimulus controller to your `importmap.php`:

```bash
php bin/console importmap:require @survos/api-grid
```

## Quick Start

This four-step setup gives you a working sortable, searchable, filterable grid.

### 1. Annotate the entity

Add API Platform filters to the entity. The bundle reads these annotations to infer which columns are sortable, searchable, and filterable without any explicit column configuration.

```php
// src/Entity/Video.php

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Survos\ApiGridBundle\Api\Filter\MultiFieldSearchFilter;
use Survos\ApiGridBundle\Api\Filter\FacetsFieldSearchFilter;

#[ApiResource]
#[GetCollection(name: self::COLLECTION_ROUTE)]
#[ApiFilter(OrderFilter::class, properties: ['title', 'year'])]
#[ApiFilter(MultiFieldSearchFilter::class, properties: ['title', 'description'])]
#[ApiFilter(FacetsFieldSearchFilter::class, properties: ['genre', 'status'])]
class Video
{
    public const COLLECTION_ROUTE = 'api-video';
    // ...
}
```

If you use `survos/field-bundle`, annotate properties instead — see [Field-Bundle Integration](docs/field-bundle.md).

### 2. Compute the collection URL in the controller

Resolve the URL server-side so the template gets a plain string. This keeps the Twig template simple and makes debugging straightforward (the URL is visible in the browser network tab).

```php
// src/Controller/VideoController.php

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use App\Entity\Video;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VideoController extends AbstractController
{
    #[Route('/videos', name: 'app_video_browse')]
    public function browse(IriConverterInterface $iriConverter): Response
    {
        return $this->render('video/browse.html.twig', [
            'class'               => Video::class,
            'apiGetCollectionUrl' => $iriConverter->getIriFromResource(
                Video::class,
                operation: new GetCollection(name: Video::COLLECTION_ROUTE)
            ),
        ]);
    }
}
```

### 3. Render the grid in Twig

```twig
{# templates/video/browse.html.twig #}

{% set columns = [
    col('id'),
    col('year', sortable: true),
    col('title', searchable: true, sortable: true),
    col('genre', browsable: true),
    col('status', browsable: true),
] %}

<twig:api_grid
    :class="class"
    :apiGetCollectionUrl="apiGetCollectionUrl"
    :columns="columns"
    :caller="_self"
/>
```

The `col()` Twig function is provided by the bundle. Each call returns a `Column` object. Arguments map directly to `Column` properties — see [Column Reference](#column-reference).

### 4. Enable client-side pagination

DataTables uses `limit`/`offset` (not `page`). The bundle ships a `SlicePaginationExtension` that handles this, but API Platform must allow client control:

```yaml
# config/packages/api_platform.yaml
api_platform:
  collection:
    pagination:
      client_items_per_page: true
      client_enabled: true
```

---

## Entity Setup

### Sorting

```php
#[ApiFilter(OrderFilter::class, properties: ['title', 'year', 'createdAt'])]
```

### Global search

The Stimulus controller sends the search box value as individual field parameters (one per searchable column). For a single unified `?search=` parameter, use `MultiFieldSearchFilter`:

```php
#[ApiFilter(MultiFieldSearchFilter::class, properties: ['title', 'description', 'tags'])]
```

### Facet filters (ColumnControl / SearchBuilder)

```php
#[ApiFilter(FacetsFieldSearchFilter::class, properties: ['genre', 'status', 'country'])]
```

Columns marked `browsable: true` use this filter. In ColumnControl mode they render as searchable dropdowns; in SearchBuilder mode they appear as criteria fields.

---

## Field-Bundle Integration

If `survos/field-bundle` is installed, add `#[Field]` to entity properties instead of `#[ApiFilter]`. The bundle reads `FieldDescriptor` objects from `FieldReader` and uses them as the authoritative source for column settings.

```php
use Survos\FieldBundle\Attribute\Field;
use Survos\FieldBundle\Enum\Widget;

class Video
{
    #[Field(searchable: true, sortable: true)]
    public string $title;

    #[Field(sortable: true)]
    public int $year;

    #[Field(filterable: true, widget: Widget::Select)]
    public string $genre;

    #[Field(filterable: true, widget: Widget::Boolean)]
    public bool $published;
}
```

`#[Field]` drives: `searchable`, `sortable`, `browsable` (via `filterable` + widget), `visible`, `width`, `widget`.

Explicit `col()` arguments always override `#[Field]` defaults. See [Field-Bundle Integration](docs/field-bundle.md) for the full layering rules.

---

## Twig Component Reference

```twig
<twig:api_grid
    :class="class"                   {# FQCN of the entity #}
    :apiGetCollectionUrl="url"       {# Collection endpoint URL #}
    :columns="columns"               {# array of col() objects or plain arrays #}
    :caller="_self"                  {# enables inline <twig:block> rendering #}
    columnControl=true               {# enable ColumnControl (default: false) #}
    searchBuilder=true               {# enable SearchBuilder (default: false) #}
    :pageLength="50"                 {# rows per page (default: 50) #}
    :defaultOrder="'year:desc'"      {# initial sort; "field:dir" or "a:asc,b:desc" #}
    :showRoute="'app_video_show'"    {# route name → opens detail in offcanvas panel #}
    select=true                      {# prepend checkbox column for multi-select #}
    :bulkActions="bulkActions"       {# array of bulk-action definitions #}
    :filter="filter"                 {# initial filter values, merged into API params #}
    :buttons="buttons"               {# extra toolbar buttons #}
    :scrollY="'70vh'"               {# table body height (CSS value) #}
    :tableId="'my-table'"           {# HTML id for the <table> element #}
/>
```

### Column Reference

```twig
col(
    'fieldName',          {# positional: property name in the serialized API response #}
    title: 'Label',       {# header label (defaults to property name) #}
    sortable: true,       {# enable column ordering #}
    searchable: true,     {# include in global search #}
    browsable: true,      {# expose as a facet/filter in ColumnControl or SearchBuilder #}
    visible: true,        {# show column by default (false = hidden, toggleable) #}
    width: '10rem',       {# CSS width hint #}
    widget: 'range',      {# widget hint: text | select | range | date | boolean #}
    route: 'app_video_show',      {# wrap cell value in <a href="..."> using FOS JS Routing #}
    responsivePriority: 1,        {# DataTables responsive priority (lower = higher priority) #}
    titleAttr: 'Tooltip text',    {# HTML title attribute on the <th> #}
    order: 10,            {# display order within the column list #}
    condition: true,      {# false removes the column entirely (useful with variables) #}
)
```

Columns can also be passed as plain arrays (backward compatible):

```twig
{% set columns = [
    'id',
    {name: 'title', sortable: true, searchable: true},
    {name: 'status', browsable: true},
] %}
```

---

## Widget Modes

The grid supports two filter UI modes. See [docs/widgets.md](docs/widgets.md) for full documentation.

### ColumnControl (recommended)

Per-column dropdowns embedded directly in the column headers. Ideal when the page already has a sidebar (e.g. EasyAdmin, Tabler).

```twig
<twig:api_grid ... columnControl=true />
```

Browsable columns (`browsable: true`) render as searchable dropdown lists.  
Columns with `widget: 'range'` render as min/max number inputs.

### SearchBuilder

A modal query builder with AND/OR logic. Browsable columns appear as criteria fields.

```twig
<twig:api_grid ... searchBuilder=true />
```

---

## Custom Cell Rendering

Pass `:caller="_self"` and add `<twig:block name="fieldName">` blocks to override rendering for specific columns. The variable `row` contains the full deserialized API response row.

```twig
{% set columns = [
    col('youtubeId'),
    col('title', sortable: true),
    col('year', sortable: true),
] %}

<twig:api_grid
    :class="class"
    :apiGetCollectionUrl="apiGetCollectionUrl"
    :columns="columns"
    :caller="_self"
>
    <twig:block name="youtubeId">
        <a target="_blank" href="https://youtube.com/watch?v={{ row.youtubeId }}">
            <img src="{{ row.thumbnailUrl }}" height="60" alt="{{ row.title }}"/>
        </a>
    </twig:block>
</twig:api_grid>
```

Blocks are compiled server-side by the `js-twig-bundle` Twig-to-JS bridge and executed in the browser by the Stimulus controller, so you can use Symfony Twig functions (`path()`, `asset()`, `trans()`, etc.) inside the blocks.

---

## Row Actions

Add a non-searchable `_actions` column and render it with a `<twig:block>`:

```twig
{% set columns = [
    col('title'),
    col('status', browsable: true),
    col(name: '_actions', title: '', sortable: false, searchable: false),
] %}

<twig:api_grid :class="class" :apiGetCollectionUrl="apiGetCollectionUrl"
               :columns="columns" :caller="_self">
    <twig:block name="_actions">
        <a class="btn btn-sm btn-outline-secondary"
           href="{{ path('app_video_show', row.rp) }}">View</a>
        <a class="btn btn-sm btn-outline-primary"
           href="{{ path('app_video_edit', row.rp) }}">Edit</a>
    </twig:block>
</twig:api_grid>
```

`row.rp` contains the route parameters exposed in the serialized API response. Include the `rp` property in the entity's serialization group.

Stimulus helpers work too:

```twig
<twig:block name="_actions">
    <button {{ stimulus_action('modal-form', 'open', 'click', {
        url: path('app_video_edit', row.rp)
    }) }} class="btn btn-sm btn-outline-primary">Edit</button>
</twig:block>
```

### Offcanvas Detail Panel

Pass `showRoute` to add a "View" button per row. Clicking it fetches the route and renders the HTML in a Bootstrap Offcanvas panel — no page navigation.

```twig
<twig:api_grid ... :showRoute="'app_video_show'" />
```

The route receives `row.rp` as parameters. Add `?_page_content_only=1` handling in the controller/template to return only the content fragment.

---

## Bulk Actions

Enable `select=true` and define `bulkActions` to let users select rows and POST their IDs to a server endpoint.

```php
// in your controller
$bulkActions = [
    [
        'id'             => 'publish',
        'label'          => 'Publish selected',
        'url'            => $this->generateUrl('app_video_bulk_publish'),
        'destructive'    => false,
        'confirm'        => true,
        'confirmMessage' => 'Publish {count} video(s)?',
    ],
    [
        'id'          => 'delete',
        'label'       => 'Delete selected',
        'url'         => $this->generateUrl('app_video_bulk_delete'),
        'destructive' => true,
        'confirm'     => true,
    ],
];
```

```twig
<twig:api_grid
    :class="class"
    :apiGetCollectionUrl="apiGetCollectionUrl"
    :columns="columns"
    select=true
    :bulkActions="bulkActions"
    :entityClass="class"
/>
```

The controller receives `ids[]` (array of entity IDs) and `className` (FQCN) as a POST form submission. A CSRF token is included automatically.

---

## Admin Browser

A generic admin browser route is registered at `/admin/browse/{code}`. The code is derived from the bundle/app prefix plus the entity short name:

- `Survos\OutreachBundle\Entity\Contact` → `/admin/browse/outreach_contact`
- `App\Entity\Video` → `/admin/browse/app_video`

`/admin/browse` lists all registered Doctrine entities.

If a route named `{code}_show` exists, the browser passes it as `showRoute`, enabling the offcanvas detail panel automatically.

---

## Backend Filters

### Repository facet counts

Facet counts (shown in ColumnControl dropdowns) require a repository method that can count distinct values per field. Install the trait from `survos/core-bundle`:

```php
use Survos\CoreBundle\Traits\QueryBuilderHelperInterface;
use Survos\CoreBundle\Traits\QueryBuilderHelperTrait;

class VideoRepository extends ServiceEntityRepository implements QueryBuilderHelperInterface
{
    use QueryBuilderHelperTrait;
}
```

### Pagination

The bundle's `SlicePaginationExtension` translates `limit`/`offset` from the DataTables request into Doctrine range queries. This runs automatically when the bundle is installed.

---

## Further Reading

- [docs/widgets.md](docs/widgets.md) — ColumnControl and SearchBuilder in depth
- [docs/field-bundle.md](docs/field-bundle.md) — Attribute-driven configuration with `#[Field]`
- [docs/implementation.md](docs/implementation.md) — Architecture: Twig component, Stimulus controller, normalizer, paginator
