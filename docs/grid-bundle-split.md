# Planned Split: grid-bundle as Base Layer

`api-grid-bundle` is planned to become a thin API Platform adapter on top of `survos/grid-bundle`.
The full implementation plan lives in:

**`survos/grid-bundle` → `docs/refactor-plan.md`**

## Summary of changes that will affect this bundle

Once the plan is executed:

- `composer.json` gains `"survos/grid-bundle": "^2.0"` in `require`
- `src/Model/Column.php` is deleted; `Survos\Grid\Model\Column` is used instead
- Duplicated DataTables.net npm packages are removed from `assets/package.json`
  (they come transitively from grid-bundle's importmap)
- `src/Twig/TwigExtension.php` is evaluated for removal if it duplicates grid-bundle's extension
- `api_grid_controller.js` imports `datatables-plugins.js` from grid-bundle once asset sharing
  is stable; until then a local copy is kept with a comment pointing at the source

## What stays in api-grid-bundle

- `api_grid_controller.js` — the ajax/hydra adapter (`dataTableParamsToApiPlatformParams`,
  hydra:member parsing, ColumnControl list/range server-side filters)
- `ApiGridComponent`, `DatatableService`, `MeiliSearchStateProvider`
- Hard dependencies on `api-platform/symfony` and Doctrine
- All facet, SearchBuilder, and ColumnControl server-side wiring

## What moves to grid-bundle

- All DataTables.net npm packages and importmap entries
- `Column` model (grid-bundle's version becomes the superset)
- Shared controller helpers: `cols()`, `c()`, `inferredColumnDefaults()`, `columnDefs()`,
  `leadingUtilityColumnCount()`, `defaultLayout()`, `normalizedLayout()`, `prependGroupHeaderRow()`
- `TwigExtension` (if currently duplicated)
