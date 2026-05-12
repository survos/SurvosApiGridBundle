# Filter Widgets

The API Grid bundle supports two filtering UI modes. Both are powered by the same server-side `FacetsFieldSearchFilter`, so switching between them is a single Twig attribute change.

> **SearchPanes has been removed.** If you are upgrading from an older version, replace `browsable: true` columns with ColumnControl or SearchBuilder — the backend filter path is unchanged.

---

## ColumnControl

ColumnControl embeds filter controls directly in a second header row, column by column. It is the recommended mode because it does not need a dedicated sidebar area and works well with admin layouts that already have a left panel (e.g. Tabler, EasyAdmin).

Provided by `datatables.net-columncontrol-bs5` (DataTables 3.0 beta extension).

### Enable

```twig
<twig:api_grid
    :class="class"
    :apiGetCollectionUrl="apiGetCollectionUrl"
    :columns="columns"
    columnControl=true
/>
```

The extension is loaded dynamically. If the npm package is not present the grid falls back gracefully to a plain search box.

### What each column gets

| Column config | ColumnControl content |
|---|---|
| `browsable: true` | Sort button + searchable dropdown list of distinct values |
| `searchable: true` (not browsable) | Sort button + free-text search input |
| `widget: 'range'` | Sort button + numeric Min / Max inputs |
| No filter config | Sort button only |

### Range widget

Mark a numeric column with `widget: 'range'` (or use `#[Field(filterable: true, widget: Widget::Range)]`). ColumnControl renders a Min/Max pair of number inputs. Values are sent to the API as `field[gte]=…&field[lte]=…`.

```twig
col('price', sortable: true, widget: 'range')
col('year', sortable: true, widget: 'range')
```

### Facet lists

`browsable: true` columns render a dropdown containing all distinct values for that column, with a search box and multi-select. Values are sent as `field[]=val1&field[]=val2` using API Platform's native array filter format. Counts are populated from the repository's facet-count method (see `QueryBuilderHelperTrait`).

### Layout

ColumnControl is injected into the DataTables layout automatically when `columnControl=true`. The dropdown portals to `document.body` to avoid clipping inside relatively-positioned containers.

---

## SearchBuilder

SearchBuilder opens a modal query builder where the user constructs AND/OR filter criteria. It is a good fit for power users who need complex multi-field queries.

Provided by `datatables.net-searchbuilder-bs5` (DataTables 3.0 beta extension).

### Enable

```twig
<twig:api_grid
    :class="class"
    :apiGetCollectionUrl="apiGetCollectionUrl"
    :columns="columns"
    searchBuilder=true
/>
```

### Which columns appear in SearchBuilder

Only columns marked `browsable: true` are offered as criteria in the SearchBuilder modal. Columns that are only `searchable: true` do not appear there (use the global search box for those).

```twig
{% set columns = [
    col('title', searchable: true, sortable: true),
    col('genre', browsable: true),   {# appears in SearchBuilder #}
    col('year', sortable: true),
    col('status', browsable: true),  {# appears in SearchBuilder #}
] %}
```

SearchBuilder criteria are forwarded to the API as a JSON blob via `?searchBuilder=…`. The server-side filter must parse this format — currently the bundle passes it through to API Platform as a raw query parameter, so you need a filter that understands the SearchBuilder protocol if you want server-side evaluation. For simple cases, combine SearchBuilder with `browsable`-backed `FacetsFieldSearchFilter` columns.

### Combining modes

You can enable both at the same time:

```twig
<twig:api_grid ... columnControl=true searchBuilder=true />
```

In this configuration the SearchBuilder button appears in the toolbar and ColumnControl dropdowns appear in the column headers. Each mode handles its own filter parameters independently.

---

## Widget Types

These are the valid `widget` values, sourced from `Survos\FieldBundle\Enum\Widget`. Set them via `col()` or `#[Field]`.

| Value | Description | ColumnControl | SearchBuilder |
|---|---|---|---|
| `text` | Free-text search | text input | string criteria |
| `select` | Distinct-value dropdown (enum, foreign key) | searchList dropdown | select criteria |
| `range` | Numeric Min/Max | number range inputs | numeric criteria |
| `date` | Date / datetime range | _(not yet implemented)_ | date criteria |
| `boolean` | True / False toggle | searchList dropdown | boolean criteria |

Widget is inferred automatically from the PHP type when `#[Field]` is used:

- `bool` → `boolean`
- `int` / `float` → `range`
- `\DateTimeInterface` → `date`
- backed `enum` → `select`
- `string` → `text`
