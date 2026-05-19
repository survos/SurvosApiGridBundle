// see https://javascript.plainenglish.io/5-cool-chrome-devtools-features-most-developers-dont-know-about-cf55d3b46c95

// during dev, from project_dir run
// ln -s ~/survos/bundles/api-grid-bundle/assets/src/controllers/sandbox_api_controller.js assets/controllers/sandbox_api_controller.js
import { Controller } from "@hotwired/stimulus";
import $ from "jquery";
import { createEngine } from "@tacman1123/twig-browser";
import { installSymfonyTwigAPI } from "@tacman1123/twig-browser/adapters/symfony";



import DataTable from "datatables.net-bs5";
import { dtPlugins } from "../datatables-plugins.js";
// https://stackoverflow.com/questions/68084742/dropdown-doesnt-work-after-modal-of-bootstrap-imported
// import bootstrap from 'bootstrap'; // bootstrap javascript
// import * as bootstrap from 'bootstrap';

// import Modal from 'bootstrap/js/dist/modal';
// window.bootstrap = bootstrap;
// DataTable.Responsive.bootstrap( bootstrap );

import PerfectScrollbar from "perfect-scrollbar";

import enLanguage from "datatables.net-plugins/i18n/en-GB.mjs";
import esLanguage from "datatables.net-plugins/i18n/es-ES.mjs";
import deLanguage from "datatables.net-plugins/i18n/de-DE.mjs";
// import ukLanguage from 'datatables.net-plugins/i18n/uk.mjs';
// import huLanguage from 'datatables.net-plugins/i18n/hu.mjs';
// import hilanguage from 'datatables.net-plugins/i18n/hi.mjs';
let Routing = null;
try {
  const mod = await import("@survos/js-twig/generated/fos_routes.js");
  if (typeof mod.path === "function") {
    Routing = { generate: mod.path };
  }
} catch {
  Routing = null;
}

if (!Routing) {
  console.error("[api-grid] js-twig routing is unavailable. Ensure @survos/js-twig/generated/fos_routes.js is in importmap.");
}
// global.Routing = Routing;

// try {
// } catch (e) {
//     console.error(e);
//     console.warn("FOS JS Routing not loaded, so path() won't work");
// }

const contentTypes = {
  PATCH: "application/merge-patch+json",
  POST: "application/json",
};

if (DataTable.ColumnControl?.content) {
  DataTable.ColumnControl.content.numberRange = {
    defaults: {
      columnName: null,
    },

    init: function (config) {
      const wrapper = document.createElement("div");
      wrapper.className = "dtcc-number-range d-flex gap-1";
      wrapper.dataset.columnName = config.columnName;

      wrapper.innerHTML = `
        <input type="number" class="form-control form-control-sm dtcc-range-min" placeholder="Min" style="min-width: 72px;">
        <input type="number" class="form-control form-control-sm dtcc-range-max" placeholder="Max" style="min-width: 72px;">
      `;

      return wrapper;
    },
  };
}

// /* stimulusFetch: 'lazy' */
export default class extends Controller {
  static targets = ["table", "modal", "modalBody", "fieldSearch", "message", "offcanvas", "offcanvasBody", "offcanvasTitle"];
  static values = {
    apiCall: { type: String, default: "" },
    columnConfiguration: { type: String, default: "[]" },
    facetConfiguration: { type: String, default: "[]" },
    globals: { type: String, default: "[]" },
    modalTemplate: { type: String, default: "" },
    locale: { type: String, default: "no-locale!" },
    style: { type: String, default: "spreadsheet" },
    layout: { type: String, default: "" },
    pageLength: { type: Number, default: 50 },
    columnControl: { type: Boolean, default: false },
    searchBuilder: { type: Boolean, default: false },
    scrollX: { type: Boolean, default: false },
    searchBuilderColumns: { type: String, default: "[]" },
    filter: String, // json, from queryString, e.g. party=dem
    buttons: String, // json, from queryString, e.g. party=dem
    select: { type: Boolean, default: false },
    bulkActions: { type: String, default: "[]" }, // json: [{id,label,url,destructive,icon,confirm}]
    csrfToken: { type: String, default: "" },
    entityClass: { type: String, default: "" },
    // Comma-separated "field:dir" pairs, e.g. "id:desc" or "updatedAt:desc,id:asc".
    // Parsed into DataTables' `order` option at init time.
    defaultOrder: { type: String, default: "" },
    // Route name for the entity show page. When set, clicking a row opens an
    // off-canvas panel loaded via fetch. Route params come from row.rp.
    showRoute: { type: String, default: "" },
  };

  // sortableFields: {type: String, default: '[]'},
  // searchableFields: {type: String, default: '[]'},

  bindNumberRangeFilters(dt) {
    document.addEventListener("input", (event) => {
      if (!event.target.closest(".dtcc-number-range")) {
        return;
      }

      dt.draw();
    });
  }

  cols() {
    // see https://javascript.plainenglish.io/are-javascript-object-keys-ordered-and-iterable-5147eedb26ce
    // const map1 = new Map();
    // map1.set('a', 1);
    let columns = this.columns.sort(function (a, b) {
      return a.order - b.order; // Sort in ascending order
    });

    const visibleColumnCount = columns.filter((c) => c.visible !== false).length + this.leadingUtilityColumnCount();

    let x = columns.map((c) => {
      let render = null;
      const inferred = this.inferredColumnDefaults(c, visibleColumnCount);
      const className = [inferred.className, c.className].filter(Boolean).join(" ") || null;
      c.searchBuilderType = "num";
      if (c.twigTemplate) {
        const templateName = `column:${c.name}`;
        this.twigEngine.compileBlock(templateName, c.twigTemplate);
        render = (data, type, row, meta) => {
          // Object.assign(row, );
          // row.locale = this.localeValue;

          // console.warn(meta); // row, columns, settings
          let rowName = "xx";
          let params = {
            [c.name]: row[c.name],
            data: data,
            dtType: type,
            row: row,
            globals: this.globals,
            column: c,
            field_name: c.name,
          };
          // params[rowName] = row;
          // [key]: 'ES6!'
          // params[c.name] = row[c.name];
          params._keys = null;
          return this.twigEngine.renderBlock(templateName, params);
        };
      }

      if (c.name === "_actions" && !c.twigTemplate) {
        return this.actions({ prefix: c.prefix, actions: c.actions });
      }

      // https://datatables.net/reference/option/columns
      let column = this.c({
        propertyName: c.name,
        data: c.name,
        label: c.title,
        route: c.route,
        locale: c.locale,
        render: render,
        sortable: typeof c.sortable ? c.sortable : false,
        className: className,
        visible: typeof c.visible === 'boolean' ? c.visible : undefined,
      });

      if (c.width || inferred.width) {
        column.width = c.width || inferred.width;
      }
      if (c.titleAttr) {
        column.titleAttr = c.titleAttr;
      }
      if (Number.isInteger(c.responsivePriority)) {
        column.responsivePriority = c.responsivePriority;
      }
      if (typeof c.visible === 'boolean') {
        column.visible = c.visible;
      }

      column.searchBuilder = {
        type: "num",
      };

      return column;
    });

    if (this.showRouteValue) {
      x.push({
        data: null,
        orderable: false,
        searchable: false,
        className: "dt-view-panel",
        defaultContent: "",
        title: "",
        width: "2.5rem",
        render: () => `<button class="btn btn-sm btn-outline-secondary btn-view-panel" title="View">View</button>`,
      });
    }

    if (this.showRouteValue || true) {
      x.unshift({
        data: null,
        orderable: false,
        searchable: false,
        className: "dtr-control",
        defaultContent: "",
        title: "",
        width: "2rem",
      });
    }

    if (this.selectValue) {
      x.unshift({
        data: null,
        orderable: false,
        searchable: false,
        className: "select-checkbox",
        defaultContent: "",
        title: "",
        width: "2rem",
      });
    }

    return x;
  }

  buildBulkActionButtons() {
    if (!this.selectValue || !this.bulkActions.length) {
      return [];
    }

    return this.bulkActions.map((action) => ({
      text: action.label,
      className: `bulk-action d-none btn-sm ${action.destructive ? "btn-danger" : ""}`,
      enabled: false,
      action: (e, dt) => this.runBulkAction(action, dt),
    }));
  }

  runBulkAction(action, dt) {
    const rows = dt.rows({ selected: true }).data().toArray();
    if (!rows.length) {
      return;
    }

    const ids = rows.map((row) => row.id).filter((id) => id !== undefined && id !== null);
    if (!ids.length) {
      console.warn("[api-grid] selected rows have no 'id' field; add it to the serializer groups");
      return;
    }

    if (action.confirm !== false) {
      const prompt = action.confirmMessage
        ? action.confirmMessage.replace("{count}", ids.length)
        : `${action.label}: ${ids.length} row${ids.length === 1 ? "" : "s"}?`;
      if (!window.confirm(prompt)) {
        return;
      }
    }

    const form = document.createElement("form");
    form.method = "post";
    form.action = action.url;
    form.style.display = "none";

    const addInput = (name, value) => {
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = name;
      input.value = value;
      form.appendChild(input);
    };

    addInput("_token", this.csrfTokenValue || "");
    if (this.entityClassValue) {
      addInput("className", this.entityClassValue);
    }
    ids.forEach((id) => addInput("ids[]", id));

    document.body.appendChild(form);
    form.submit();
  }

  connect() {
    super.connect(); //

    this.apiParams = {}; // initialize
    const event = new CustomEvent("changeFormUrlEvent", {
      formUrl: "testing formURL!",
    });
    window.dispatchEvent(event);
    this.globals = JSON.parse(this.globalsValue);
    this.twigEngine = createEngine();
    installSymfonyTwigAPI(this.twigEngine, {
      pathGenerator: (route, routeParams = {}) => {
        const safeParams = { ...(routeParams ?? {}) };
        delete safeParams._keys;
        if (Routing?.generate) {
          return Routing.generate(route, safeParams);
        }
        throw new Error(`Routing is unavailable for path('${route}').`);
      },
    });

    this.columns = JSON.parse(this.columnConfigurationValue);
    this.facets = JSON.parse(this.facetConfigurationValue);
    this.bulkActions = JSON.parse(this.bulkActionsValue || "[]");
    console.table(this.facets);
    // "compile" the custom twig blocks
    // var columnRender = [];
    this.layout = this._parseLayout(this.layoutValue);

    if (!this.layout) {
      this.layout = this.defaultLayout();
    }

    this.filter = JSON.parse(this.filterValue || "[]");
    // console.error(this.buttonsValue);
    this.buttons = JSON.parse(this.buttonsValue || "[]");
    this.buttonMap = new WeakMap();
    this.x = {};
    this.buttons.forEach((button) => {
      console.error(button.label);
      // this.buttonMap.set(button.label, button);
      this.x[button.label] = button;
      // this.urlByCode[button.label] = button.url;
      console.log("adding " + button.label);
      this.buttons.push({
        text: button.label,
        action: (e, dt, node, config) => {
          let key = config.text;
          let button = this.x[key];

          // Create a base URL
          // const url = new URL(button.url);

          // Create URLSearchParams object
          const params = new URLSearchParams();

          // Add query parameters
          if (this.apiParams.search ?? false) {
            params.append("q", this.apiParams.search);
          }
          params.append("ff[]", this.apiParams.facet_filter);

          // Set the search property of the URL object
          //                     console.error( params.toString());

          // Get the final URL string

          // @todo: add this.apiParams to the url
          // console.log(this.apiParams);
          window.open(button.url + "?" + params.toString(), "_blank").focus();
          // dt.ajax.reload();
        },
        // action: {
        //     // console.log("open " + button.url);
        //     // open url,maybe in new tab
        // },
      });
    });

    // this.sortableFields = JSON.parse(this.sortableFieldsValue);
    // this.searchableFields = JSON.parse(this.searchableFieldsValue);
    this.searchBuilderColumns = JSON.parse(this.searchBuilderColumnsValue);

    this.locale = this.localeValue;

    console.log(
      "hola from " + this.identifier + " locale: " + this.localeValue,
    );
    // console.assert(this.hasModalTarget, "Missing modal target");
    this.that = this;
    this.tableElement = false;
    if (this.hasTableTarget) {
      this.tableElement = this.tableTarget;
    } else if (this.element.tagName === "TABLE") {
      this.tableElement = this.element;
    } else {
      this.tableElement = document.getElementsByTagName("table")[0];
    }
    // else {
    //     console.error('A table element is required.');
    // }
    if (this.tableElement) {
      this.dt = this.initDataTable(this.tableElement, []);
      this.initialized = true;
    }
  }

  openModal(e) {
    console.error(
      "yay, open modal!",
      e,
      e.currentTarget,
      e.currentTarget.dataset,
    );

    this.modalTarget.addEventListener("show.bs.modal", (e) => {
      console.log(e, e.relatedTarget, e.currentTarget);
      // do something...
    });

    this.modal = new Modal(this.modalTarget);
    console.log(this.modal);
    this.modal.show();
  }

  createdRow(row, data, dataIndex) {
    // we could add the thumbnail URL here.
    // console.log(row, data, dataIndex, this.identifier);
    // let aaController = 'projects';
    // row.classList.add("text-danger");
    // row.setAttribute('data-action', aaController + '#openModal');
    // row.setAttribute('data-controller', 'modal-form', {formUrl: 'test'});
  }

  notify(message) {
    this.messageTarget.innerHTML = message;
  }

  handleTrans(el) {
    let transitionButtons = el.querySelectorAll("button.transition");
    transitionButtons.forEach((btn) =>
      btn.addEventListener("click", (event) => {
        const isButton = event.target.nodeName === "BUTTON";
        if (!isButton) {
          return;
        }
        console.log(event, event.target, event.currentTarget);

        let row = this.dt.row(event.target.closest("tr"));
        let data = row.data();
        console.log(row, data);
        this.notify("deleting " + data.id);

        // console.dir(event.target.id);
      }),
    );
  }

  requestTransition(route, entityClass, id) {}

  // eh... not working
  get modalController() {
    return this.application.getControllerForElementAndIdentifier(
      this.modalTarget,
      "modal_form",
    );
  }

  addButtonClickListener(dt) {
    console.log(
      "Listening for button.transition and button .btn-modal clicks events",
    );

    dt.on("click", "button.btn-view-panel", ($event) => {
      $event.stopPropagation();
      const data = dt.row($event.currentTarget.closest("tr")).data();
      this.openShowPanel(data);
    });

    dt.on("click", "tr td button.transition", ($event) => {
      console.log($event.currentTarget);
      let target = $event.currentTarget;
      var data = dt.row(target.closest("tr")).data();
      let transition = target.dataset["t"];
      console.log(transition, target);
      console.log(data, $event);
      this.that.modalBodyTarget.innerHTML = transition;
      this.modal = new Modal(this.modalTarget);
      this.modal.show();
    });

    // dt.on('click', 'tr td button .btn-modal',  ($event, x) => {
    dt.on("click", "tr td button ", ($event, x) => {
      console.log($event, $event.currentTarget);
      var data = dt.row($event.currentTarget.closest("tr")).data();
      console.log(data, $event, x);
      console.warn("dispatching changeFormUrlEvent");
      const event = new CustomEvent("changeFormUrlEvent", { formUrl: "test" });
      window.dispatchEvent(event);

      let btn = $event.currentTarget;
      let modalRoute = btn.dataset.modalRoute;
      if (modalRoute) {
        this.modalBodyTarget.innerHTML = data.code;
        this.modal = new Modal(this.modalTarget);
        this.modal.show();
        console.assert(data.rp, "missing rp, add @Groups to entity");
        let formUrl = Routing.generate(modalRoute, {
          ...data.rp,
          _page_content_only: 1,
        });
        console.warn("dispatching changeFormUrlEvent");
        const event = new CustomEvent("changeFormUrlEvent", {
          detail: { formUrl: formUrl },
        });
        window.dispatchEvent(event);
        document.dispatchEvent(event);

        console.log("getting formURL " + formUrl);

        fetch(formUrl)
          .then((response) => response.text())
          .then((data) => (this.modalBodyTarget.innerHTML = data))
          .catch((error) => (this.modalBodyTarget.innerHTML = error));
      }
    });
  }

  addRowClickListener(dt) {
    dt.on("click", "tr td", ($event) => {
      let el = $event.currentTarget;
      console.log($event, $event.currentTarget);
      var data = dt.row($event.currentTarget).data();
      var btn = el.querySelector("button");
      console.log(btn);
      let modalRoute = null;
      if (btn) {
        console.error(btn, btn.dataset, btn.dataset.modalRoute);
        modalRoute = btn.dataset.modalRoute;
      }

      if (el.querySelector("a")) {
        return; // skip links, let it bubble up to handle
      }

      if (modalRoute) {
        this.modalBodyTarget.innerHTML = data.code;
        this.modal = new Modal(this.modalTarget);
        this.modal.show();
        console.assert(data.rp, "missing rp, add @Groups to entity");
        let formUrl = Routing.generate(modalRoute, data.rp);

        fetch(formUrl, {
          headers: {
            _page_content_only: "1",
          },
        })
          .then((response) => response.text())
          .then((data) => (this.modalBodyTarget.innerHTML = data))
          .catch((error) => (this.modalBodyTarget.innerHTML = error));
      }
    });
  }

  initDataTable(el, fields) {
    let lookup = [];
    // for (const property in fields) {
    //     lookup[property] = field;
    //     console.error(property, fields[property]);
    //     console.log(`${property}: ${fields[property]}`);
    // }
    // fields = Array.from(fields);
    // fields.forEach((field, index) => {
    //     console.error(field);
    //     lookup[field.jsonKeyCode] = field;
    // });
    // console.error(lookup);

    let searchFieldsByColumnNumber = [];
    let options = [];

    let filterColumns = this.columns;
    Object.entries(filterColumns).forEach((entry) => {
      const [key, value] = entry;
      searchFieldsByColumnNumber.push(key);
    });

    this.columns.forEach((column, index) => {
      // console.log(column);
      // if (column.browsable) {
      //     // console.error(index);
      //     if(column.browseOrder) {
      //         searchFieldsByColumnNumber[index] = column.browseOrder;
      //     } else {
      //         searchFieldsByColumnNumber[index] = 0;
      //     }
      //     // searchFieldsByColumnNumber.push(index);
      //     //rawFacets.push(column.name);
      // }
      //this.sortableFields.push(index);
      options = fields;
      // this is specific to museado, but needs to be generalized with a field structure.
      // if (column.browsable && (column.name in fields)) {
      //     let fieldName = column.name; //  lookup[column.name];
      //     // options[field.jsonKeyCode] = [];
      //     for (const label in field.valueCounts) {
      //         let count = field.valueCounts[label];
      //         //     console.log(field.valueCounts);
      //         // field.valueCounts.protoforEach( (label, count) =>
      //         // {
      //         options[fieldName].push({
      //             label: label,
      //             count: field.distinctValuesCount,
      //             value: label,
      //             total: count
      //         });
      //     }
      // } else {
      //     // console.warn("Missing " + column.name, Object.keys(lookup));
      // }
    });

    let apiPlatformHeaders = {
      Accept: "application/ld+json",
      "Content-Type": "application/json",
    };

    const userLocale =
      navigator.languages && navigator.languages.length
        ? navigator.languages[0]
        : navigator.language;

    // console.log('user locale: ' + userLocale); // 👉️ "en-US"
    // console.error('this.locale: ' + this.locale);
    if (this.locale !== "") {
      apiPlatformHeaders["Accept-Language"] = this.locale;
      apiPlatformHeaders["X-LOCALE"] = this.locale;
    }

    let language = enLanguage;
    if (this.locale === "en") {
      language = enLanguage;
    } else if (this.locale === "es") {
      language = esLanguage;
    } else if (this.locale === "de") {
      language = deLanguage;

      // }else if(this.locale == 'uk') {
      //     language = ukLanguage;
      // }else if(this.locale == 'hu') {
      //     language = huLanguage;
      // }else if(this.locale == 'hi') {
      //     language = hilanguage;
    }

    const modalTemplateName = "modal:responsive";
    var modalRenderer = DataTable.Responsive.renderer.tableAll({
      tableClass: "ui table",
    });
    if (this.modalTemplateValue) {
      this.twigEngine.compileBlock(modalTemplateName, this.modalTemplateValue);
      modalRenderer = (api, rowIdx, columns) => {
        let data = api.row(rowIdx).data();
        let params = { data: data, columns: columns, globals: this.globals };
        return this.twigEngine.renderBlock(modalTemplateName, params);
        console.log(rowIdx);
      };
    }

    // Parse defaultOrderValue ("id:desc" | "a:asc,b:desc") into DataTables'
    // [[colIdx, direction], ...] form. Field names are matched against the
    // ordered column list (same order DataTables sees).
    const initialOrder = [];
    if (this.defaultOrderValue && this.defaultOrderValue.trim() !== "") {
      const columnOffset = this.leadingUtilityColumnCount();
      const sortedCols = this.columns.slice().sort((a, b) => a.order - b.order);
      this.defaultOrderValue.split(",").forEach((pair) => {
        const [fieldRaw, dirRaw] = pair.split(":").map((s) => (s || "").trim());
        if (!fieldRaw) return;
        const dir = dirRaw && dirRaw.toLowerCase() === "desc" ? "desc" : "asc";
        const idx = sortedCols.findIndex((c) => c.name === fieldRaw);
        if (idx >= 0) initialOrder.push([idx + columnOffset, dir]);
        else console.warn(`api_grid: defaultOrder "${fieldRaw}" matches no column`);
      });
    }

    const layout = this.normalizedLayout() || this.defaultLayout();

    let setup = {
      // let dt = new DataTable(el, {

      language: language,
      createdRow: this.createdRow,
      // paging: true,
      // scrollY: true,
      // displayLength: 50, // not sure how to adjust the 'length' sent to the server
      // pageLength: 15,
      orderCellsTop: true,
      fixedHeader: false,
      //cascadePanes  : true,
      deferRender: true,
      // scrollX:        true,
      // scrollCollapse: true,
      scroller: false,
      pageLength: this.pageLengthValue || 50,
      ...(initialOrder.length ? { order: initialOrder } : {}),
      // responsive: {
      //     details: {
      //         renderer: modalRenderer,
      //         display: DataTable.Responsive.display.modal({
      //             header: function (row) {
      //                 var data = row.data();
      //                 return 'Details for ' + data.clientName;
      //             }
      //         })
      //     }
      // },

      // scroller: {
      //     // rowHeight: 90, // @WARNING: Problematic!!
      //     // displayBuffer: 10,
      //     loadingIndicator: true,
      // },
      // "processing": true,
      serverSide: true, // use grid for client-side

      initComplete: (obj, data) => {
        if (this.columnControlValue) {
          this.portalColumnControlDropdown(el);
        }

        this.handleTrans(el);

        this.applyHeaderMetadata(dt);

        this.bindNumberRangeFilters(dt);

        if (this.selectValue && this.bulkActions.length) {
          const toggleBulkButtons = () => {
            const count = dt.rows({ selected: true }).count();
            const api = dt.buttons('.bulk-action');
            const nodes = api.nodes();

            if (count > 0) {
              nodes.removeClass('d-none');
              api.enable(true);
            } else {
              nodes.addClass('d-none');
              api.enable(false);
            }
          };

          dt.on("select deselect", toggleBulkButtons);
          toggleBulkButtons();
        }

        // let xapi = new DataTable.Api(obj);
        // console.log(xapi);
        // console.log(xapi.table);
        // this.addRowClickListener(dt);
        // let searchPane = dt.searchPanes.panes[6];
        // searchPane.selected.push('inactive');
        // dt.search(dt.columns().search()).draw();
      },

      layout: layout,
      paging: true,
      info: true,
      lengthChange: true,
      ...(this.selectValue
        ? {
            select: {
              style: "multi",
              selector: "td.select-checkbox",
            },
          }
        : {}),
      ...(this.scrollXValue
        ? { scrollX: true, responsive: false }
        : {
            responsive: {
              details: {
                type: 'column',
                target: this.selectValue ? 1 : 0,
                renderer: modalRenderer,
              },
            },
          }),
      buttons: [...this.buttons, ...this.buildBulkActionButtons()],
      columns: this.cols(),
      ...((this.searchBuilderValue || this.searchBuilderColumns.length)
        ? {
            searchBuilder: {
              ...(this.searchBuilderColumns.length ? { columns: this.searchBuilderColumns } : {}),
              depthLimit: 1,
              threshold: 0,
              showEmptyPanes: true,
            },
          }
        : {}),
      xxbuttons: (x) => {
        // why isn't this being called?
        console.error(x);
        let buttons = [
          "copy",
          "csv",
          "excel",
          "pdf",
          "print",
          {
            text: "labels",
            action: (e, dt, node, config) => {
              // window.open(Routing.generate('owner_labels', {
              //     ownerId: 1,
              //     pixieCode: pixie
              // }))
              console.log("open url, pass the params ", this.apiParams);
              const event = new CustomEvent("changeSearchEvent", {
                detail: this.apiParams,
              });
              window.dispatchEvent(event);
            },
          },
        ];
        console.error(this.buttons);
        this.buttons.forEach((button, index) => {
          buttons.push({
            text: "x",
            action: (e, dt, node, config) => {
              // console.log(e, config);
              // open url,maybe in new tab
            },
          });
          console.log(button, index);
        });
        return buttons;
      },
      columnDefs: this.columnDefs(),
      // https://datatables.net/reference/option/ajax
      ajax: (params, callback, settings) => {
        this.messageTarget.innerHTML = `starting at ${params.start}`;
        this.apiParams = this.dataTableParamsToApiPlatformParams(params);
        // this.debug &&
        // console.error(this.apiParams);
        // console.assert(params.start, `DataTables is requesting ${params.length} records starting at ${params.start}`, this.apiParams);

        Object.assign(this.apiParams, this.filter);
        // yet another locale hack
        if (this.locale !== "") {
          this.apiParams["_locale"] = this.locale;
        }
        if (this.hasStyleValue) {
          this.apiParams["_style"] = this.styleValue;
        }

        // console.warn(apiPlatformHeaders);

        // Build URLSearchParams manually to handle array values as key[]=v1&key[]=v2
        const searchParams = new URLSearchParams();
        for (const [key, value] of Object.entries(this.apiParams)) {
          if (Array.isArray(value)) {
            value.forEach((v) => searchParams.append(key + "[]", v));
          } else if (value !== null && value !== undefined) {
            searchParams.append(key, value);
          }
        }
        const requestUrl = new URL(this.apiCallValue, window.location.origin);
        for (const [key, value] of searchParams.entries()) {
          requestUrl.searchParams.append(key, value);
        }
        let request = fetch(
          requestUrl.toString(),
          { headers: apiPlatformHeaders }
        )
          .then((response) => response.json())
          .then((hydraData) => {
            // handle success
            // Be resilient to different formats (.jsonld vs .json, etc.)
            let member = [];
            if (Array.isArray(hydraData)) {
              member = hydraData;
            } else if (Array.isArray(hydraData["member"])) {
              member = hydraData["member"];
            } else if (Array.isArray(hydraData["hydra:member"])) {
              member = hydraData["hydra:member"];
            } else if (Array.isArray(hydraData["items"])) {
              member = hydraData["items"];
            }

            let total =
              hydraData["totalItems"] ??
              hydraData["hydra:totalItems"] ??
              hydraData["total"] ??
              member.length;

            var itemsReturned = member.length;
            // let first = (params.page - 1) * params.itemsPerPage;
            if (params.search.value) {
              console.log(`dt search: ${params.search.value}`);
            }

            // console.log(`dt request: ${params.length} starting at ${params.start}`);

            // let first = (apiOptions.page - 1) * apiOptions.itemsPerPage;
            let d = member;

            const debugUrl = requestUrl.toString();
            this.messageTarget.innerHTML =
              ` <a class="text-muted small ms-2" target="_blank" href="${debugUrl}" title="Last API call">🔗 API</a>`;

            callback({
              draw: params.draw,
              data: d,
              columnControl:
                (hydraData.facets && hydraData.facets.columnControl) || {},
              recordsTotal: total,
              recordsFiltered: total,
            });
          })
          .catch((error) => {
            console.error(error, error?.request);
            let url =
              error?.request?.responseURL ||
              error?.response?.url ||
              (this.apiCallValue + "?" + new URLSearchParams(this.apiParams));
            this.messageTarget.innerHTML =
              '<div class="bg-danger">' +
              error.message +
              "</div> " +
              `<a target="_blank" href="${url}">${url}</a>`;
            console.error(error);
          });
      },
    };
    if (this.columnControlValue && this.hasColumnControlPlugin()) {
      setup.columnControl = this.columnControlConfig();
      // Avoid duplicate ordering UI when ColumnControl is active.
      setup.ordering = { indicators: false, handler: false };
    }

    let dt = new DataTable(el, setup);

    if (this.columns.some(c => c.group)) {
      this.prependGroupHeaderRow(dt);
    }

    this.addButtonClickListener(dt);

    if (this.filter.hasOwnProperty("q")) {
      dt.search(this.filter.q).draw();
    }

    this.filter = [];
    this.columns.forEach((column, index) => {
      if (column.order == 0) {
        dt.column(index + this.leadingUtilityColumnCount()).visible(false);
      }
    });

    return dt;
  }

  _parseLayout(raw) {
    if (!raw) return null;
    if (typeof raw === 'object') return raw;
    const value = String(raw).trim();
    if (!value) return null;
    try {
      return JSON.parse(value);
    } catch {
      return null;
    }
  }

  defaultLayout() {
    return {
      topStart: ['buttons', 'pageLength'],
      topEnd: 'search',
      bottomStart: 'info',
      bottomEnd: 'paging',
    };
  }

  hasColumnControlPlugin() {
    return !!dtPlugins.columnControl;
  }

  normalizedLayout() {
    const replaceFeature = (value) => {
      if (Array.isArray(value)) {
        return value.map(replaceFeature).filter((item) => item !== null);
      }
      if (value === "columnControl" && !this.hasColumnControlPlugin()) {
        return "search";
      }
      if (value === "searchBuilder" && !this.searchBuilderValue) {
        return null;
      }
      return value;
    };

    return this.layout ? replaceFeature(this.layout) : null;
  }

  applyHeaderMetadata(dt) {
    const headerCells = dt.table().header()?.querySelectorAll("th") || [];
    this.columns.forEach((column, index) => {
      const th = headerCells[index + this.leadingUtilityColumnCount()];
      if (!th) return;
      if (column.titleAttr) {
        th.setAttribute("title", column.titleAttr);
      }
      if (column.width) {
        th.style.width = column.width;
      }
    });
  }

  columnDefs() {
    let defs = [];
    const columnOffset = this.leadingUtilityColumnCount();

    if (this.columnControlValue) {
      // Enable ColumnControl. Text and range inputs stay inline in the second
      // header row; facet lists use a dropdown so option buttons are not clipped.
      this.columns.forEach((c, idx) => {
        const target = idx + columnOffset;

        if (String(c.widget || '').toLowerCase() === 'range') {
          defs.push({
            targets: target,
            columnControl: [
              { target: 0, content: ["order"] },
              {
                target: 1,
                content: [
                  {
                    extend: "numberRange",
                    columnName: c.name,
                  }
                ],
              },
            ],
          });

          return;
        }

        if (c.browsable) {
          defs.push({
            targets: target,
            columnControl: [
              { target: 0, content: ["order"] },
              {
                target: 1,
                content: [{
                  extend: "dropdown",
                  icon: "search",
                  iconActive: "searchActive",
                  text: "Filter",
                  content: [{ extend: "searchList", ajaxOnly: true, search: true, select: true }],
                }],
              },
            ],
          });
        } else if (c.searchable) {
          defs.push({
            targets: target,
            columnControl: [
              { target: 0, content: ["order"] },
              {
                target: 1,
                content: [{
                  extend: "search",
                  excludeLogic: [
                    "notContains",
                    "equal",
                    "notEqual",
                    "starts",
                    "ends",
                    "empty",
                    "notEmpty",
                  ],
                }],
              },
            ],
          });
        }
      });
    }

    this.columns.forEach((column, index) => {
      const def = { targets: index + this.leadingUtilityColumnCount() };
      let hasConfig = false;

      if (typeof column.visible === "boolean") {
        def.visible = column.visible;
        hasConfig = true;
      }
      if (typeof column.orderable === "boolean") {
        def.orderable = column.orderable;
        hasConfig = true;
      } else if (typeof column.sortable === "boolean") {
        def.orderable = column.sortable;
        hasConfig = true;
      }
      if (column.width) {
        def.width = column.width;
        hasConfig = true;
      }
      if (Number.isInteger(column.responsivePriority)) {
        def.responsivePriority = column.responsivePriority;
        hasConfig = true;
      }

      if (hasConfig) {
        defs.push(def);
      }
    });

    defs.push({
      targets: "_all",
      visible: true,
      defaultContent: "",
    });

    return defs;
  }

  leadingUtilityColumnCount() {
    // Keep this in sync with cols(), where responsive/select utility columns
    // are prepended before the configured entity columns.
    return 1 + (this.selectValue ? 1 : 0);
  }

  /**
   * Prepends a group label row above the header rows DataTables (and ColumnControl)
   * have already built. Called after new DataTable() so we don't interfere with
   * ColumnControl's own thead row management.
   *
   * Each cell in the group row corresponds 1:1 to a column (no rowspan tricks).
   * Grouped runs share a single colspan cell with the group label; ungrouped and
   * utility columns get an empty cell. This keeps alignment exact and requires no
   * cooperation from DataTables internals.
   *
   * Limitation: if a column is hidden via ColumnControl, the group colspan is not
   * automatically updated. Handle via a 'column-visibility' listener if needed.
   */
  prependGroupHeaderRow(dt) {
    const sorted = [...this.columns].sort((a, b) => (a.order ?? 100) - (b.order ?? 100));
    const thead = dt.table().header();
    const firstRow = thead.querySelector('tr');
    if (!firstRow) return;

    const groupRow = document.createElement('tr');
    const offset = this.leadingUtilityColumnCount();

    // Empty stubs for utility columns (responsive control + optional select checkbox)
    for (let i = 0; i < offset; i++) {
      groupRow.appendChild(document.createElement('th'));
    }

    // Walk sorted columns, collecting consecutive runs of the same group label
    let i = 0;
    while (i < sorted.length) {
      const group = sorted[i].group ?? null;

      if (!group) {
        // Ungrouped: one empty cell, aligns with the column below it
        groupRow.appendChild(document.createElement('th'));
        i++;
      } else {
        // Count the consecutive run sharing this group label
        let span = 0;
        while (i + span < sorted.length && (sorted[i + span].group ?? null) === group) {
          span++;
        }
        const th = document.createElement('th');
        th.colSpan = span;
        th.className = 'dt-column-group text-center fw-semibold';
        th.textContent = group;
        groupRow.appendChild(th);
        i += span;
      }
    }

    thead.insertBefore(groupRow, firstRow);
  }

  // The ColumnControl plugin calculates top/left as offsets from document.body,
  // but appends the dropdown inside dt-container whose offsetParent is Tabler's
  // .page-wrapper (position:relative). Portal to body so the coordinates resolve
  // correctly without touching any layout CSS.
  openShowPanel(data) {
    if (!this.showRouteValue) {
      console.error("[api-grid] Missing showRoute value.");
      return;
    }

    if (!data?.rp && !data?.["@id"]) {
      console.error("[api-grid] Missing row route params and @id.", data);
      return;
    }

    const url = data?.rp
      ? Routing.generate(this.showRouteValue, {
          ...data.rp,
          _fragment: "_show",
          _page_content_only: 1,
        })
      : data["@id"];

    if (this.hasOffcanvasTitleTarget) {
      this.offcanvasTitleTarget.textContent = data.name ?? data.title ?? data.id ?? '';
    }
    if (this.hasOffcanvasBodyTarget) {
      this.offcanvasBodyTarget.innerHTML = '<div class="text-center p-4"><div class="spinner-border"></div></div>';
    }

    if (!window.bootstrap?.Offcanvas) {
      console.error("[api-grid] Bootstrap Offcanvas is not available.");
      return;
    }

    const bsOffcanvas = window.bootstrap.Offcanvas.getOrCreateInstance(this.offcanvasTarget);
    bsOffcanvas.show();
    fetch(url)
      .then(r => r.text())
      .then(html => { if (this.hasOffcanvasBodyTarget) this.offcanvasBodyTarget.innerHTML = html; })
      .catch(e => { if (this.hasOffcanvasBodyTarget) this.offcanvasBodyTarget.innerHTML = `<div class="alert alert-danger">${e.message}</div>`; });
  }

  portalColumnControlDropdown(tableEl) {
    const observer = new MutationObserver((mutations) => {
      for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
          if (node.classList?.contains('dtcc-dropdown')) {
            document.body.appendChild(node);
          }
        }
      }
    });
    // The table is a sibling of .dt-container (both inside the .table-responsive wrapper),
    // so closest('.dt-container') from the table returns null. Observe the common parent.
    const root = tableEl.parentElement?.querySelector('.dt-container') ?? tableEl.parentElement ?? tableEl;
    observer.observe(root, { childList: true, subtree: true });
  }

  columnControlConfig() {
    // Global default: sort button + sort-options dropdown in row 0.
    // Per-column defs override this to add search inputs in row 1.
    return [{ target: 0, content: ["order", ["orderAsc", "orderDesc"]] }];
  }

  // get columns() {
  //     // if columns isn't overwritten, use the th's in the first tr?  or data-field='status', and then make the api call with _fields=...?
  //     // or https://datatables.net/examples/ajax/null_data_source.html
  //     return [
  //         {title: '@id', data: 'id'}
  //     ]
  // }

  actions({ prefix = "", actions = ["edit", "show", "qr"] } = {}) {
    const resolvedPrefix = prefix ?? "";
    const resolvedActions = Array.isArray(actions) && actions.length
      ? actions
      : ["edit", "show", "qr"];

    let icons = {
      edit: "pencil",
      show: "eye text-success",
      qr: "qr-code",
      delete: "trash text-danger",
    };
    let buttons = resolvedActions.map((action) => {
      let modal_route = `${resolvedPrefix}${action}`;
      let icon = icons[action];
      if (!icon) {
        icon = "fas fa-circle";
      }
      // return action + ' ' + modal_route;
      // Routing.generate()

      return `<button data-modal-route="${modal_route}" class="btn btn-modal btn-action-${action}"
title="${modal_route}"><i class="action-${action} bi bi-${icon}"></i></button>`;
    });

    // console.log(buttons);
    return {
      title: "actions",
      render: () => {
        return buttons.join(" ");
      },
    };
    resolvedActions.forEach((action) => {});
  }

  inferredColumnDefaults(column, visibleColumnCount = 0) {
    const name = String(column.name || "").toLowerCase();
    const title = String(column.title || column.name || "").toLowerCase();
    const widget = String(column.widget || "").toLowerCase();
    const classes = [];
    let width = null;
    const numericNameParts = [
      "year", "count", "total", "number", "qty", "quantity", "price", "amount",
      "votes", "budget", "height", "width", "length", "score", "rating",
    ];

    if (name === "id" || name.endsWith("id") || name.endsWith("_id")) {
      classes.push("dt-col-compact", "dt-col-number");
      width = "5rem";
    } else if (widget === "range" || numericNameParts.some((part) => name === part || name.endsWith(part) || name.includes(`_${part}`))) {
      classes.push("dt-col-compact", "dt-col-number");
      width = visibleColumnCount > 8 ? "7rem" : "8rem";
    } else if (column.browsable || widget === "select" || widget === "boolean") {
      classes.push("dt-col-facet", "dt-col-wrap");
      width = visibleColumnCount > 8 ? "11rem" : "13rem";
    } else if (["title", "name", "label"].includes(name) || ["title", "name", "label"].includes(title)) {
      classes.push("dt-col-title", "dt-col-wrap");
      width = visibleColumnCount > 8 ? "18rem" : "24rem";
    } else if (/(description|overview|summary|abstract|notes?|content|text)$/.test(name)) {
      classes.push("dt-col-long-text", "dt-col-wrap");
      width = visibleColumnCount > 8 ? "20rem" : "28rem";
    }

    return {
      className: classes.join(" "),
      width,
    };
  }

  c({
    propertyName = null,
    name = null,
    route = null,
    modal_route = null,
    label = null,
    modal = false,
    render = null,
    locale = null,
    renderType = "string",
    sortable = false,
    className = null,
    visible = undefined,
  } = {}) {
    if (render === null) {
      render = (data, type, row, meta) => {
        // if (!label) {
        //     // console.log(row, data);
        //     label = data || propertyName;
        // }
        let displayData = data;
        // @todo: move some twig templates to a common library
        if (renderType === "image") {
          return `<img class="img-thumbnail plant-thumb" alt="${data}" src="${data}" />`;
        }
        if (type === "display" && data && renderType === "url") {
          const href = /^https?:\/\//i.test(data) ? data : `https://${data}`;
          return `<a href="${href}" target="_blank" rel="noopener">${data}</a>`;
        }
        if (type === "display" && data && renderType === "email") {
          return `<a href="mailto:${data}">${data}</a>`;
        }

        // Auto-link bare domains (domain.edu, domain.org, http://...) even without explicit renderType
        if (type === "display" && typeof data === "string" && data && !renderType) {
          const url = /^https?:\/\//i.test(data) ? data
            : /^[\w.-]+\.(edu|org|com|net|gov|io)(\/.*)?$/i.test(data) ? `https://${data}`
            : null;
          if (url) {
            return `<a href="${url}" target="_blank" rel="noopener">${data}</a>`;
          }
        }

        if (route) {
          if (locale) {
            row.rp["_locale"] = locale;
          }
          let url = Routing.generate(route, row.rp);
          if (modal) {
            return `<button class="btn btn-primary"></button>`;
          } else {
            return `<a href="${url}">${displayData}</a>`;
          }
        } else {
          if (modal_route) {
            return `<button data-modal-route="${modal_route}" class="btn btn-success">${modal_route}</button>`;
          } else {
            // console.log(propertyName, row[propertyName], row);
            // if nested, explode...
            let elements = propertyName.split(".");
            if (elements.length === 3) {
              let x1 = elements[0];
              let x2 = elements[1];
              let x3 = elements[2];
              return row[x1]?.[x2]?.[x3] ?? null;
            } else if (elements.length === 2) {
              // @todo: replace with a proper nested-path resolver; dot-path columns
              // like "organization.id" should use a generic reduce, not hand-rolled
              // indexing. Guard with ?. so null relations don't crash (e.g. Contact
              // with no Organization).
              let x1 = elements[0];
              let x2 = elements[1];
              return row[x1]?.[x2] ?? null;
            } else {
              return row[propertyName];
            }
          }
        }
      };
    }

    return {
      title: label,
      className: className,
      data: propertyName || "",
      render: render,
      orderable: sortable,
      ...(typeof visible === "boolean" ? { visible } : {}),
    };
    // ...function body...
  }

  guessColumn(v) {
    let renderFunction = null;
    switch (v) {
      case "id":
        renderFunction = (data, type, row, meta) => {
          console.warn("id render");
          return "<b>" + data + "!!</b>";
        };
        break;
      case "newestPublishTime":
      case "createTime":
        renderFunction = (data, type, row, meta) => {
          let isoTime = data;
          let str = isoTime
            ? '<time class="timeago" datetime="' +
              data +
              '">' +
              data +
              "</time>"
            : "";
          return str;
        };
        break;
      // default:
      //     renderFunction = ( data, type, row, meta ) => { return data; }
    }
    let obj = {
      title: v,
      data: v,
    };
    if (renderFunction) {
      obj.render = renderFunction;
    }
    console.warn(obj);
    return obj;
  }

  dataTableParamsToApiPlatformParams(params) {
    let columns = params.columns; // get the columns passed back to us, sanity.
    // var apiData = {
    //     page: 1
    // };
    // console.error(params);

    // apiData.start = params.start; // ignored?s

    let apiData = {};
    if (params.length) {
      apiData.itemsPerPage = params.length;
      // Keep these for older api-grid offset/limit providers; API Platform's
      // native Doctrine paginator ignores them.
      apiData.limit = params.length;
      apiData.page = Math.floor((params.start || 0) / params.length) + 1;
    }
    // really this is the queryStringParameters
    let facetsUrl = [];
    // Map the global DataTables search to each searchable column's ApiPlatform filter parameter.
    // ApiPlatform SearchFilter uses the property name as the query param (e.g. name=Bohr),
    // not a generic "search" param. We only send to columns flagged searchable so we don't
    // accidentally hit exact-match filters with a partial search value.
    if (params.search && params.search.value) {
      const searchVal = params.search.value;
      // this.columns is our #[Field]-driven config; searchable=true means an ApiPlatform
      // SearchFilter is configured for that property.
      const searchableCols = this.columns.filter(c => c.searchable && c.name);
      if (searchableCols.length > 0) {
        searchableCols.forEach(c => { apiData[c.name] = searchVal; });
      } else {
        // Fallback for entities using a MultiFieldSearchFilter with searchParameterName=search
        apiData["search"] = searchVal;
      }
      facetsUrl.push("q=" + searchVal);
    }

    let order = {};
    if (params.searchBuilder) {
      apiData["searchBuilder"] = JSON.stringify(params.searchBuilder);
    }
    if (params.style) {
      apiData["_style"] = params.style;
    }
    // https://jardin.wip/api/projects.jsonld?page=1&itemsPerPage=14&order[code]=asc
    params.order.forEach((o, index) => {
      let c = params.columns[o.column];
      if (c.data && c.orderable && c.orderable == true) {
        order[c.data] = o.dir;
        // apiData.order = order;
        apiData["order[" + c.data + "]"] = o.dir;
      }
      // console.error(c, order, o.column, o.dir);
    });

    let currentUrl = window.location.href;

    // Remove the protocol and domain
    let urlWithoutProtocolAndDomain = currentUrl.replace(/^.*\/\/[^\/]+/, "");

    // Extract the path
    let path = urlWithoutProtocolAndDomain.split("?")[0];

    for (const [key, value] of Object.entries(this.filter)) {
      facetsUrl.push(key + "=" + value);
    }
    if (facetsUrl.length > 0) {
      path += "?" + facetsUrl.join("&");
      history.replaceState(null, "", path);
    } else {
      history.replaceState(null, "", path);
    }

    if (params.searchBuilder && params.searchBuilder.criteria) {
      params.searchBuilder.criteria.forEach((c, index) => {
        console.warn(c, c.origData);
        // apiData[c.origData + '[]'] = c.value1;
      });
    }
    // let facets = [];
    // this.facets.forEach(function (column, index) {
    //     // if ( column.browsable ) {
    //         facets.push(column.name);
    //     // }
    // });
    // console.error({facets});

    document.querySelectorAll(".dtcc-number-range[data-column-name]").forEach((wrapper) => {
      const columnName = wrapper.dataset.columnName;
      const min = wrapper.querySelector(".dtcc-range-min")?.value;
      const max = wrapper.querySelector(".dtcc-range-max")?.value;

      if (min !== undefined && min !== "") {
        apiData[`${columnName}[gte]`] = min;
      }

      if (max !== undefined && max !== "") {
        apiData[`${columnName}[lte]`] = max;
      }
    });

    // Column-specific filtering
    params.columns.forEach((column, index) => {
      // Classic DataTables column search
      if (column.search && column.search.value) {
        apiData[column.data] = column.search.value;
      }

      // ColumnControl server-side filters
      if (column.columnControl) {
        const columnControlSearch = this.columnControlSearchValue(column.columnControl.search);
        if (columnControlSearch !== null) {
          apiData[column.data] = columnControlSearch;
        }
        if (Array.isArray(column.columnControl.list) && column.columnControl.list.length) {
          // Use API Platform's native array format: field[]=val1&field[]=val2
          apiData[column.data] = column.columnControl.list;
        }
      }
    });

    apiData.offset = params.start;
    apiData.facets = [];
    // this could be replaced with sending a list of facets and skipping this =1, it'd be cleaner
    this.facets.forEach((column, index) => {
      if (column.browsable) {
        if (!apiData.facets.includes(column.name)) {
          apiData.facets.push(column.name);
        }
      }
    });

    this.facets.forEach((column, index) => {
      if (column.browsable) {
        if (!apiData.facets.includes(column.name)) {
          apiData.facets.push(column.name);
        }
      }
    });
    console.table(apiData.facets);
    // console.table(apiData);

    return apiData;
  }

  columnControlSearchValue(search) {
    if (search === null || search === undefined || search === "") {
      return null;
    }
    if (typeof search === "string" || typeof search === "number") {
      return String(search);
    }
    if (Array.isArray(search)) {
      const values = search
        .map((item) => this.columnControlSearchValue(item))
        .filter((value) => value !== null && value !== "");

      return values.length ? values : null;
    }
    if (typeof search === "object") {
      for (const key of ["value", "search", "term", "query"]) {
        if (search[key] !== undefined && search[key] !== null && search[key] !== "") {
          return String(search[key]);
        }
      }
    }

    return null;
  }

  initFooter(el) {
    return;

    let footer = el.querySelector("tfoot");
    if (footer) {
      return; // do not initiate twice
    }

    var handleInput = function (column) {
      var input = $('<input class="form-control" type="text">');
      input.attr("placeholder", column.filter.placeholder || column.data);
      return input;
    };

    this.debug && console.log("adding footer");
    footer = el.createTFoot();
    footer.classList.add("show-footer-above");

    var thead = el.querySelector("thead");
    el.insertBefore(footer, thead);

    // Create an empty <tr> element and add it to the first position of <tfoot>:
    var row = footer.insertRow(0);

    // Insert a new cell (<td>) at the first position of the "new" <tr> element:

    // Add some bold text in the new cell:
    //         cell.innerHTML = "<b>This is a table footer</b>";

    this.columns().forEach((column, index) => {
      var cell = row.insertCell(index);

      // cell.innerHTML = column.data;

      const input = document.createElement("input");
      input.setAttribute("type", "text");
      input.setAttribute("placeholder", column.data);
      cell.appendChild(input);

      // if (column.filter === true || column.filter.type === 'input') {
      //         el = handleInput(column);
      //     } else if (column.filter.type === 'select') {
      //         el = handleSelect(column);
      //     }

      // var cell = row.insertCell(index);
      // var td = $('<td>');
      // if (column.filter !== undefined) {
      //     var el;
      //     if (column.filter === true || column.filter.type === 'input') {
      //         el = handleInput(column);
      //     } else if (column.filter.type === 'select') {
      //         el = handleSelect(column);
      //     }
      //     that.handleFieldSearch(this.el, el, index);
      //
      //     td.append(el);
    });
    // footer = $('<tfoot>');
    // footer.append(tr);
    // console.log(footer);
    // this.el.append(footer);

    // see http://live.datatables.net/giharaka/1/edit for moving the footer to below the header
  }
}
