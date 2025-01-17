import { Controller } from "@hotwired/stimulus";

// HTML datatable controller, works with SimpleDatatablesComponent, which generates an HTML table.
// see api_grid_controller for remote data loading use API Platform

import $ from "jquery"; // for datatables.
// // import {SurvosDataTable} from 'survos-datatables';

// import jquery from 'jquery';
// console.log('local jquery');

// import 'bootstrap';

import DataTables from "datatables.net-bs5";

import "../datatables-plugins.js";

// import {Modal} from "bootstrap"; !!
// https://stackoverflow.com/questions/68084742/dropdown-doesnt-work-after-modal-of-bootstrap-imported
// import Modal from 'bootstrap/js/dist/modal';
// import cb from "../js/app-buttons";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
  // dom: 'BPlfrtip',
  static targets = ["table", "modal", "modalBody", "fieldSearch", "message"];
  static values = {
    search: true,
    info: false,
    pageLength: 15,
    dom: { type: String, default: "QBlfrtip" }, // use P for searchPanes

    useDatatables: true,
    sortableFields: { type: String, default: "{}" },
    filter: { type: String, default: "" },
  };

  initialize() {
    this.initialized = false;
  }

  connect() {
    // super.connect();
    this.that = this; // for the modal
    let dom = this.domValue;

    if (!this.useDatatablesValue) {
      return;
    }
    return;

    this.tableElement = false;
    if (this.hasTableTarget) {
      this.tableElement = this.tableTarget;
      console.log(this.tableElement);
    } else if (this.element.tagName === "TABLE") {
      this.tableElement = this.element;
      if (!this.useDatatablesValue) {
        return;
      }
    } else {
      console.error(
        "missing table target, so Using the first table we can find in the document",
      );
      this.tableElement = document.getElementsByTagName("table")[0];
    }
    if (!this.initialized) {
      this.dt = this.initDataTable(this.tableElement, dom);
    } else {
      console.warn("no reason to initialize!");
    }

    // else {
    //     console.error('A table element is required.');
    // }
    if (this.tableElement) {
      this.buttons = ["colvis", "copy", "excel"];

      let searchString = this.searchValue ? "f" : "";
      let infoString = this.infoValue ? "i" : "";
      // let dom = ` <"dtsp-verticalContainer"<"dtsp-verticalPanes"P><"js-dt-buttons"B><"js-dt-info"${infoString}>${searchString}t`;
      // let dom = `<"dtsp-verticalContainer"<"dtsp-verticalPanes"P><"js-dt-buttons"B><"js-dt-info"${infoString}>${searchString}t`;
      // let dom = `<"dtsp-verticalContainer"<"dtsp-verticalPanes"P><"js-dt-buttons"B><"js-dt-info"${infoString}>${searchString}`;
      // dom = '<"dtsp-dataTable"frtip>';

      // let dom = `<"js-dt-buttons"B><"js-dt-info"${infoString}>${searchString}t`;
      // let dom = `t`;

      // console.error(this.useDatatablesValue);
    }
    this.initialized = true;
  }

  disconnect() {
    super.disconnect();
  }

  openModal(e) {
    // console.error('yay, open modal!', e, e.currentTarget, e.currentTarget.dataset);

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
    console.log(message);
    this.messageTarget.innerHTML = message;
  }

  handleTrans(el) {
    let transitionButtons = el.querySelectorAll("button.transition");
    // console.log(transitionButtons);
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

  addButtonClickListener(dt) {
    console.log("Listening for transition events");

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

    dt.on("click", "tr td button .modal", ($event, x) => {
      console.log($event, $event.currentTarget);
      var data = dt.row($event.currentTarget.closest("tr")).data();
      console.log(data, $event, x);

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

        console.assert(false, "axios has been removed.");

        // axios({
        //     method: 'get', //you can set what request you want to be
        //     url: formUrl,
        //     // data: {id: varID},
        //     // headers: {
        //     //     _page_content_only: '1' // could send blocks that we want??
        //     // }
        // })
        //     .then(response => this.modalBodyTarget.innerHTML = response.data)
        //     .catch(error => this.modalBodyTarget.innerHTML = error)
        // ;
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

      console.error(el.dataset, data, $event.currentTarget);
      console.log(this.identifier + " received an tr->click event", data, el);

      if (el.querySelector("a")) {
        return; // skip links, let it bubble up to handle
      }

      if (modalRoute) {
        this.modalBodyTarget.innerHTML = data.code;
        this.modal = new Modal(this.modalTarget);
        this.modal.show();
        console.assert(
          data.uniqueIdentifiers,
          "missing uniqueIdentifiers, add @Groups to entity",
        );
        let formUrl = Routing.generate(modalRoute, data.uniqueIdentifiers);

        axios({
          method: "get", //you can set what request you want to be
          url: formUrl,
          // data: {id: varID},
          headers: {
            _page_content_only: "1", // could send blocks that we want??
          },
        })
          .then((response) => (this.modalBodyTarget.innerHTML = response.data))
          .catch((error) => (this.modalBodyTarget.innerHTML = error));
      }
    });
  }

  initDataTable(el, dom) {
    let setup = {
      // let dt = new DataTable(el, {
      retrieve: true,
      createdRow: this.createdRow,
      autoWidth: false,
      // paging: true,
      // scrollY: this.scrollY, // vh is percentage of viewport height, https://css-tricks.com/fun-viewport-units/
      scrollY: true,
      // displayLength: 10, // not sure how to adjust the 'length' sent to the server
      pageLength: 15,
      columnDefs: this.columnDefs,
      orderCellsTop: true,
      fixedHeader: true,
      deferRender: true,
      select: true,
      // scrollCollapse: true,
      scrollX: true,
      scroller: {
        // rowHeight: 90, // @WARNING: Problematic!!
        displayBuffer: 10,
        loadingIndicator: true,
      },
      dom: dom,
      // dom: 'pBfrti',
      // dom: 'Bfrtip',
      buttons: [
        "copy",
        "excel",
        {
          extend: "colvis",
          columns: "th:nth-child(n+2)",
        },
      ],

      // buttons: this.buttons,
    };

    // dom = `<"dtsp-verticalContainer"<"dtsp-verticalPanes"P><"dtsp-dataTable"frtip>>`;
    // dom = '<"dtsp-dataTable"frtip>';
    console.log("DOM: " + dom);
    setup = {
      retrieve: true, // avoid datatable has been initialized
      dom: dom,
      select: true,
      scrollY: "70vh",
      scrollX: true,
      scrollCollapse: true,
      scroller: true,
      // responsive: true,
      responsive: {
        details: {
          // display: DataTables.Responsive.display.modal({
          //     // display: $.fn.dataTable.Responsive.display.modal({
          //     header: function (row) {
          //         var data = row.data();
          //         return 'Details for ' + data.clientName;
          //     }
          // })
        },
      },

      pageLength: this.pageLengthValue,
      columnDefs: this.columnDefs(),
      searchBuilder: {
        columns: [1, 2], // this.searchBuilderFields,
        depthLimit: 1,
        threshold: 0,
        showEmptyPanes: true,
      },

      searchPanes: {
        layout: "columns-" + 1, // this.searchPanesColumns,
        showTotals: true,
        cascadePanes: true,
        showZeroCounts: true,
        viewTotal: true,
      },
      buttons: ["colvis", "csvHtml5", "copy"],
    };

    // var table = $('#example').DataTable({
    //     searchPanes: {
    //         layout: 'columns-1'
    //     },
    //     dom: '<"dtsp-dataTable"frtip>',
    //     pageLength: 20
    // });

    let table = new DataTables(el, setup);
    // if (this.dom.hasOwnProperty('P'))
    {
      table.searchPanes();
      // let container = table.searchPanes.container();
      // $("div.dtsp-verticalPanes").append(container);
      $("div.dtsp-verticalPanes").append(table.searchPanes.container());

      // @todo: move to stimulus target
      // Move stuff
      //             let msg = document.querySelector('#searchPanesVerticalContainer');
      //             msg.innerHTML = 'this is where the searchpanes should be.';
      //             msg.replaceWith(table.searchPanes.container());
    }

    return table;
  }

  columnDefs() {
    // convert list to numbers

    let x = [
      { searchPanes: { show: true }, targets: "in-search-pane" },
      { searchPanes: { show: false }, targets: "_all" },
    ];
    return x;
  }
}
