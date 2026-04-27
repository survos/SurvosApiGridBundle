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
    searchPanesDataUrl: { type: String, default: "" },
    columnConfiguration: { type: String, default: "[]" },
    facetConfiguration: { type: String, default: "[]" },
    globals: { type: String, default: "[]" },
    modalTemplate: { type: String, default: "" },
    locale: { type: String, default: "no-locale!" },
    style: { type: String, default: "spreadsheet" },
    layout: { type: String, default: "" },
    cascadePanes: { type: Boolean, default: false },
    viewTotal: { type: Boolean, default: false },
    index: { type: String, default: "" }, // meili
    dom: { type: String, default: "" },
    pageLength: { type: Number, default: 50 },
    searchPanes: { type: Boolean, default: true },
    columnControl: { type: Boolean, default: false },
    searchBuilder: { type: Boolean, default: false },
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

  // with searchPanes dom: {type: String, default: 'P<"dtsp-dataTable"rQfti>'},
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

    let x = columns.map((c) => {
      let render = null;
      c.className = c.title;
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
        className: c.className,
        visible: typeof c.visible === 'boolean' ? c.visible : undefined,
      });

      if (c.width) {
        column.width = c.width;
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

      column.searchPanes = {
        show: c.browsable,
        dtOpts: {
          info: true,
          columnDefs: [
            {
              targets: 0,
              className: "f32",
            },
          ],
        },
      };

      if (c.browsable) {
        // console.warn(c.name, column);
      }
      // column.searchPanes.dtOpts = {
      //     info: true,
      //     columnDefs: [
      //             {
      //                 targets: 0,
      //                 className: 'f32'
      //             }
      //         ]
      // }
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
        render: () => `<button class="btn btn-sm btn-outline-secondary btn-view-panel" title="View"><i class="bi bi-eye"></i></button>`,
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
      className: `btn-sm ${action.destructive ? "btn-outline-danger" : "btn-outline-secondary"}`,
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
    this.dom = this.domValue;
    this.layout = this._parseLayout(this.layoutValue);
    // dom: 'Plfrtip',
    console.assert(this.dom, "Missing dom");

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
    this.viewTotal = true; // this.viewTotalValue;
    this.cascadePanes = false; // never with serverSide: true! this.cascadePanesValue;
    if (!this.layout && !this.dom) {
      this.layout = this.defaultLayout();
    }
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

    dt.on("click", "tr td button.btn-view-panel", ($event) => {
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
    let preSelectArray = [];

    let filterColumns = this.columns;
    // console.log(filterColumns, typeof filterColumns);
    // filterColumns.sort(function (a, b) {
    //     return a.browseOrder - b.browseOrder;
    // });
    // do not use foreach on object
    Object.entries(filterColumns).forEach((entry) => {
      const [key, value] = entry;
      searchFieldsByColumnNumber.push(key);
    });

    this.columns.forEach((column, index) => {
      let name = "";
      if (typeof column == "string") {
        name = column;
      } else {
        name = column.name;
      }
      if (this.filter.hasOwnProperty(name)) {
        preSelectArray.push({
          column: index,
          rows: this.filter[name].split("|"),
        });
      }
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

    let searchPanesRaw = [];

    // console.error('searchFields', searchFieldsByColumnNumber);

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
      const sortedCols = this.columns.slice().sort((a, b) => a.order - b.order);
      this.defaultOrderValue.split(",").forEach((pair) => {
        const [fieldRaw, dirRaw] = pair.split(":").map((s) => (s || "").trim());
        if (!fieldRaw) return;
        const dir = dirRaw && dirRaw.toLowerCase() === "desc" ? "desc" : "asc";
        const idx = sortedCols.findIndex((c) => c.name === fieldRaw);
        if (idx >= 0) initialOrder.push([idx, dir]);
        else console.warn(`api_grid: defaultOrder "${fieldRaw}" matches no column`);
      });
    }

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
      responsive: false,
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

        dt.on("searchPanes.rebuildPane", function () {
          // This function will run after the user selects a value from the SearchPane
          console.log(
            "A selection has been made and the table has been updated.",
          );
        });
        this.handleTrans(el);

        const box = document.getElementsByClassName("dtsp-title");
        if (box.length) {
          box[0].style.display = "none";
        }

        this.applyHeaderMetadata(dt);

        this.bindNumberRangeFilters(dt);

        if (this.selectValue && this.bulkActions.length) {
          const toggleBulkButtons = () => {
            const count = dt.rows({ selected: true }).count();
            const start = this.buttons.length;
            this.bulkActions.forEach((_action, idx) => {
              dt.button(start + idx).enable(count > 0);
            });
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

      ...(this.normalizedLayout() ? { layout: this.normalizedLayout() } : {}),
      ...(!this.layout && this.dom ? { dom: this.dom } : {}),
      ...(this.selectValue
        ? {
            select: {
              style: "multi",
              selector: "td.select-checkbox",
            },
          }
        : {}),
      buttons: [...this.buttons, ...this.buildBulkActionButtons()],
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
      columns: this.cols(),
      ...(this.searchPanesValue
        ? {
            searchPanes: {
              initCollapsed: true,
              dtOpts: {
                scrollCollapse: true,
              },
              layout: "columns-1",
              show: true,
              cascadePanes: this.cascadePanes,
              viewTotal: true,
              preSelect: preSelectArray,
            },
          }
        : {}),
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
      // columns:
      //     [
      //     this.c({
      //         propertyName: 'name',
      //     }),
      // ],
      columnDefs: this.columnDefs(searchFieldsByColumnNumber),
      // https://datatables.net/reference/option/ajax
      ajax: (params, callback, settings) => {
        this.messageTarget.innerHTML = `starting at ${params.start}`;
        this.apiParams = this.dataTableParamsToApiPlatformParams(
          params,
          searchPanesRaw,
        );
        // this.debug &&
        // console.error(this.apiParams);
        // console.assert(params.start, `DataTables is requesting ${params.length} records starting at ${params.start}`, this.apiParams);

        Object.assign(this.apiParams, this.filter);
        // yet another locale hack
        if (this.locale !== "") {
          this.apiParams["_locale"] = this.locale;
        }
        // check for meili index
        if (this.hasIndexValue) {
          this.apiParams["_index"] = this.indexValue;
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
            if (d.length) {
              // console.table(d[0]);
              // console.log('first result', d[0]);
            }
            let searchPanes = null;
            searchPanes = {
              initCollapsed: true,
              layout: "columns-1",
              show: true,
              cascadePanes: this.cascadePanes,
              viewTotal: this.viewTotal,
              showZeroCounts: true,
              preSelect: preSelectArray,
            };

            // options.threshold = 0.01;
            // options.showZeroCounts = true;
            // options.cascadePanes = true;
            // options.viewTotal = true;
            // options.show = true;
            // console.error('searchpanes', searchPanes, options);

            // if searchPanes have been sent back from the results, sort them by browseOrder
            if (
              typeof hydraData["facets"] !== "undefined" &&
              typeof hydraData["facets"]["searchPanes"] !== "undefined"
            ) {
              searchPanesRaw = hydraData["facets"]["searchPanes"]["options"];
              searchPanes = this.sequenceSearchPanes(
                hydraData["facets"]["searchPanes"]["options"],
              );
              console.warn({ searchPanes, searchPanesRaw, hydraData });
            } else if (this.searchPanesValue) {
              const fallbackOptions = this.buildSearchPaneOptionsFromRows(member);
              if (Object.keys(fallbackOptions).length > 0) {
                searchPanesRaw = fallbackOptions;
                searchPanes = this.sequenceSearchPanes(fallbackOptions);
              } else {
                searchPanes = null;
              }
            } else {
              searchPanes = null;
            }
            // searchPanes.threshold = 0.01;
            if (searchPanes) {
              searchPanes.showZeroCounts = true;
              searchPanes.cascadePanes = true;
              searchPanes.viewTotal = true;
              searchPanes.show = true;
            }
            let targetMessage = "";
            if (typeof this.apiParams.facet_filter != "undefined") {
              this.apiParams.facet_filter.forEach((index) => {
                // format is (probably) facet,value1|value2
                if (targetMessage !== "") {
                  targetMessage += ", ";
                }
                let string = index.split(",");
                if (string.length > 0) {
                  let firstPart = string[0];
                  let splitValue = string[2].split("|");
                  let returnValue = [];
                  splitValue.forEach((index) => {
                    if (searchPanes?.options && firstPart in searchPanes["options"]) {
                      searchPanes["options"][firstPart].forEach((array) => {
                        if (index === array.value) {
                          returnValue.push(array.label);
                          return false;
                        }
                      });
                    }
                  });
                  targetMessage += string[0] + " : " + returnValue.join("|");
                }
              });
            }
            const debugUrl = requestUrl.toString();
            this.messageTarget.innerHTML =
              targetMessage +
              ` <a class="text-muted small ms-2" target="_blank" href="${debugUrl}" title="Last API call">🔗 API</a>`;

            // if next page isn't working, make sure api_platform.yaml is correctly configured
            // defaults:
            //     pagination_client_items_per_page: true

            // if there's a "next" page and we didn't get everything, fetch the next page and return the slice.
            let next = hydraData?.view?.next ?? hydraData?.["hydra:view"]?.["hydra:next"] ?? null;
            // we need the searchpanes options, too.

            let callbackValues = {
              draw: params.draw,
              data: d,
              columnControl:
                (hydraData.facets && hydraData.facets.columnControl) || {},
              recordsTotal: total,
              recordsFiltered: total, //  itemsReturned,
            };
            if (searchPanes?.options && Object.keys(searchPanes.options).length > 0) {
              callbackValues.searchPanes = searchPanes;
            }
            callback(callbackValues);
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
    if (this.filter.hasOwnProperty("P")) {
    }
    if (this.searchPanesValue && dt.searchPanes) {
      dt.searchPanes();
    }
    if (this.filter.hasOwnProperty("q")) {
      dt.search(this.filter.q).draw();
    }

    this.filter = [];
    this.columns.forEach((column, index) => {
      if (column.order == 0) {
        dt.column(index).visible(false);
      }
    });

    if (this.searchPanesValue && dt.searchPanes) {
      console.log("moving panes to div.search-panes");
      const paneContainer = dt.searchPanes.container?.();
      if (paneContainer) {
        $("div.dtsp-verticalPanes").append(paneContainer);
      }
    }
    // $("div.search-panes").append(dt.searchPanes.container());
    if (this.filter.hasOwnProperty("P")) {
    }
    const contentContainer = document.getElementsByClassName("search-panes");
    if (contentContainer.length > 0) {
      const ps = new PerfectScrollbar(contentContainer[0]);
    }
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
      topStart: ["pageLength"],
      topEnd: [
        ...(this.searchBuilderValue ? ["searchBuilder"] : []),
        this.hasColumnControlPlugin() && this.columnControlValue ? "columnControl" : "search",
      ],
      bottomStart: ["info"],
      bottomEnd: ["paging"],
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
      const th = headerCells[index];
      if (!th) return;
      if (column.titleAttr) {
        th.setAttribute("title", column.titleAttr);
      }
      if (column.width) {
        th.style.width = column.width;
      }
    });
  }

  columnDefs(searchPanesColumns) {
    let defs = [];

    if (this.searchPanesValue) {
      defs.push({
        searchPanes: { show: true },
        targets: searchPanesColumns,
      });
    }

    if (this.columnControlValue) {
      // Enable ColumnControl — search/searchList placed OUTSIDE the dropdown array
      // so ColumnControl renders them as an inline second header row (not a popup).
      this.columns.forEach((c, idx) => {
        if (String(c.widget || '').toLowerCase() === 'range') {
          defs.push({
            targets: idx,
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
            targets: idx,
            columnControl: [
              { target: 0, content: ["order"] },
              { target: 1, content: [{ extend: "searchList", ajaxOnly: true, search: true, select: true }] },
            ],
          });
        } else if (c.searchable) {
          defs.push({
            targets: idx,
            columnControl: [
              { target: 0, content: ["order"] },
              { target: 1, content: ["search"] },
            ],
          });
        }
      });
    }

    this.columns.forEach((column, index) => {
      const def = { targets: index };
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

  // The ColumnControl plugin calculates top/left as offsets from document.body,
  // but appends the dropdown inside dt-container whose offsetParent is Tabler's
  // .page-wrapper (position:relative). Portal to body so the coordinates resolve
  // correctly without touching any layout CSS.
  openShowPanel(data) {
    if (!this.showRouteValue || !data?.rp) return;
    const url = Routing.generate(this.showRouteValue, { ...data.rp, _fragment: '_show' });
    if (this.hasOffcanvasTitleTarget) {
      this.offcanvasTitleTarget.textContent = data.name ?? data.title ?? data.id ?? '';
    }
    if (this.hasOffcanvasBodyTarget) {
      this.offcanvasBodyTarget.innerHTML = '<div class="text-center p-4"><div class="spinner-border"></div></div>';
    }
    const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(this.offcanvasTarget);
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

  dataTableParamsToApiPlatformParams(params, searchPanesRaw) {
    let columns = params.columns; // get the columns passed back to us, sanity.
    // var apiData = {
    //     page: 1
    // };
    // console.error(params);

    // apiData.start = params.start; // ignored?s

    let apiData = {};
    if (params.length) {
      // was apiData.itemsPerPage = params.length;
      apiData.limit = params.length;
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
    if (params.searchPanes) {
      for (const [key, value] of Object.entries(params.searchPanes)) {
        const values = Object.values(value);
        if (values.length) {
          // Use API Platform's native array format: role[]=TENANT_ADMIN
          apiData[key] = values;
          facetsUrl.push(values.map((v) => `${key}[]=${encodeURIComponent(v)}`).join("&"));
        }
      }
    }

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
        apiData[`${columnName}Min`] = min;
      }

      if (max !== undefined && max !== "") {
        apiData[`${columnName}Max`] = max;
      }
    });

    // Column-specific filtering
    params.columns.forEach(function (column, index) {
      // Classic DataTables column search
      if (column.search && column.search.value) {
        apiData[column.data] = column.search.value;
      }

      // ColumnControl server-side filters
      if (column.columnControl) {
        if (column.columnControl.search && column.columnControl.search.value) {
          apiData[column.data] = column.columnControl.search.value;
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

  buildSearchPaneOptionsFromRows(rows) {
    const options = {};
    const browsableColumns = this.columns.filter((column) => column.browsable === true);

    browsableColumns.forEach((column) => {
      const counts = new Map();
      rows.forEach((row) => {
        const value = row?.[column.name];
        const normalized = this.normalizeFacetValue(value);
        if (normalized === null || normalized === "") {
          return;
        }
        counts.set(normalized, (counts.get(normalized) || 0) + 1);
      });

      if (counts.size === 0) {
        return;
      }

      options[column.name] = Array.from(counts.entries())
        .sort((a, b) => b[1] - a[1])
        .map(([value, count]) => ({
          label: String(value),
          value: String(value),
          total: count,
          count: count,
        }));
    });

    return options;
  }

  normalizeFacetValue(value) {
    if (value === null || value === undefined) {
      return null;
    }
    if (Array.isArray(value)) {
      return value.length ? String(value[0]) : null;
    }
    if (typeof value === "object") {
      if (typeof value.name === "string") return value.name;
      if (typeof value.label === "string") return value.label;
      if (typeof value.id === "string" || typeof value.id === "number") return String(value.id);
      return null;
    }
    return String(value);
  }

  sequenceSearchPanes(data) {
    let newOrderdata = [];
    let searchPanesOrder = this.columns.filter(function (columnConfig) {
      return columnConfig.browsable === true;
    });

    searchPanesOrder = searchPanesOrder.sort(function (a, b) {
      if (a.browseOrder != b.browseOrder) {
        return a.browseOrder - b.browseOrder;
      }
      let aData = 0;
      let bData = 0;

      if (typeof data[a.name] != "undefined") {
        aData = data[a.name].length;
      }

      if (typeof data[b.name] != "undefined") {
        bData = data[b.name].length;
      }

      return bData - aData;
    });

    searchPanesOrder.forEach(function (index) {
      if (typeof data[index.name] != "undefined") {
        newOrderdata[index.name] = data[index.name];
      } else {
        console.warn(index.name);
        // newOrderdata[index.name] =  data[index.name];
      }
    });

    let newOptionOrderData = [];
    newOptionOrderData["options"] = newOrderdata;
    console.log({ newOptionOrderData, newOrderdata, searchPanesOrder });
    return newOptionOrderData;
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
