# Field-Bundle Integration

When `survos/field-bundle` is installed, you can drive the entire grid column configuration from PHP attributes on the entity — no explicit `col()` calls needed in Twig. This keeps the grid definition co-located with the entity and makes it automatically consistent with other tools (Meilisearch, UX-Search, inspection endpoints) that read the same attributes.

---

## The `#[Field]` Attribute

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

    #[Field(visible: false)]
    public string $internalCode;
}
```

The `#[Field]` parameters that apply to the grid:

| Parameter | Type | Default | Effect |
|---|---|---|---|
| `searchable` | bool | false | Enables global search box for this field |
| `sortable` | bool | false | Makes column orderable |
| `filterable` | bool | false | Exposes a filter control; the exact widget depends on `widget` |
| `widget` | Widget | inferred | `text`, `select`, `range`, `date`, `boolean` |
| `visible` | bool | true | Hides the column by default (still toggleable via ColumnControl) |
| `width` | string | null | CSS width hint, e.g. `'8rem'` |
| `transKey` | string | null | Translation key override (looked up in the `fields` domain) |
| `order` | int | 100 | Column display order (lower = further left) |

### Widget inference

If `widget` is null, the bundle infers it from the PHP type:

- `bool` → `Widget::Boolean`
- `int` / `float` → `Widget::Range`
- `\DateTimeInterface` → `Widget::Date`
- backed enum → `Widget::Select`
- everything else → `Widget::Text`

### Browsability

`browsable` (used by the grid) is derived from `filterable && widget->isBrowsable()`. Only `Widget::Select` and `Widget::Boolean` are browsable — they render as selectable lists in ColumnControl and SearchBuilder. `Widget::Range`, `Widget::Date`, and `Widget::Text` are filterable but not browsable.

---

## Layered Configuration

Settings are applied in three layers, from lowest to highest priority:

### Layer 1: `#[Field]` (authoritative when present)

`FieldReader` reads all `#[Field]`-annotated properties and methods and produces a `FieldDescriptor` per property. The grid service converts these into column defaults.

### Layer 2: class-level `#[ApiFilter]` (fallback for unannotated entities)

If `FieldReader` is not available or a property has no `#[Field]`, the bundle falls back to reading class-level `#[ApiFilter]` attributes:

- `OrderFilter` → `sortable: true`
- `SearchFilter` / `MultiFieldSearchFilter` → `searchable: true`
- `FacetsFieldSearchFilter` → `browsable: true`

### Layer 3: `col()` in Twig (explicit override)

Any argument passed to `col()` overrides both layers above. Use this for per-page overrides without touching the entity:

```twig
{# Suppress the browsable dropdown just for this page #}
col('genre', browsable: false)

{# Override width for a specific template #}
col('title', width: '30rem')
```

`null` arguments in `col()` do not override — they fall through to the attribute defaults. `false` and `true` always win.

---

## Zero-Config Grid

When every relevant property is annotated with `#[Field]`, the grid can be rendered without specifying any columns at all:

```twig
{# All columns, settings, and widths come from #[Field] #}
<twig:api_grid
    :class="class"
    :apiGetCollectionUrl="apiGetCollectionUrl"
/>
```

The component calls `getDefaultColumns()` → `DatatableService::getSettingsFromAttributes()` and builds the full column list automatically. Add explicit `col()` calls only when you need to override defaults or add custom rendering blocks.

---

## Example: Full Entity with Grid and Meili Alignment

```php
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Survos\FieldBundle\Attribute\Field;
use Survos\FieldBundle\Enum\Widget;

#[ApiResource]
#[GetCollection(name: self::COLLECTION_ROUTE)]
class Song
{
    public const COLLECTION_ROUTE = 'api-song';

    public int $id;

    #[Field(searchable: true, sortable: true)]
    public string $title;

    #[Field(filterable: true, widget: Widget::Select)]
    public string $genre;

    #[Field(sortable: true, widget: Widget::Range)]
    public int $year;

    #[Field(filterable: true, widget: Widget::Boolean)]
    public bool $published;

    #[Field(visible: false)]
    public string $slug;
}
```

This single `#[Field]` annotation set drives:

- **api-grid-bundle**: column sortable/searchable/browsable/visible/widget
- **meili-bundle**: Meilisearch searchable / filterable / sortable / facet attribute settings
- **inspection-bundle**: field descriptors for API documentation and admin tooling
