// see https://javascript.plainenglish.io/5-cool-chrome-devtools-features-most-developers-dont-know-about-cf55d3b46c95


// during dev, from project_dir run
// ln -s ~/survos/bundles/api-grid-bundle/assets/src/controllers/sandbox_api_controller.js assets/controllers/sandbox_api_controller.js
import {Controller} from "@hotwired/stimulus";
import $ from 'jquery';

import {default as axios} from "axios";

import DataTable from "datatables.net-bs5";
import '../datatables-plugins.js';
// https://stackoverflow.com/questions/68084742/dropdown-doesnt-work-after-modal-of-bootstrap-imported
// import bootstrap from 'bootstrap'; // bootstrap javascript
// import * as bootstrap from 'bootstrap';

// import Modal from 'bootstrap/js/dist/modal';
// window.bootstrap = bootstrap;
// DataTable.Responsive.bootstrap( bootstrap );

import PerfectScrollbar from 'perfect-scrollbar';

import Routing from 'fos-routing';
import RoutingData from '/js/fos_js_routes.js';
Routing.setData(RoutingData);

import Twig from 'twig';
import enLanguage from 'datatables.net-plugins/i18n/en-GB.mjs'
import esLanguage from 'datatables.net-plugins/i18n/es-ES.mjs';
import deLanguage from 'datatables.net-plugins/i18n/de-DE.mjs';
// import ukLanguage from 'datatables.net-plugins/i18n/uk.mjs';
// import huLanguage from 'datatables.net-plugins/i18n/hu.mjs';
// import hilanguage from 'datatables.net-plugins/i18n/hi.mjs';
Twig.extend(function (Twig) {
    Twig._function.extend('path', (route, routeParams={}) => {
        // console.error(routeParams);
        if('_keys' in routeParams){
        // if(routeParams.hasOwnProperty('_keys')){
            delete routeParams._keys; // seems to be added by twigjs
        }
        let path = Routing.generate(route, routeParams);
        return path;
    });
});

console.assert(Routing, 'Routing is not defined');
// global.Routing = Routing;

// try {
// } catch (e) {
//     console.error(e);
//     console.warn("FOS JS Routing not loaded, so path() won't work");
// }

const contentTypes = {
    'PATCH': 'application/merge-patch+json',
    'POST': 'application/json'
};

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['table', 'modal', 'modalBody', 'fieldSearch', 'message'];
    static values = {
        apiCall: {type: String, default: ''},
        searchPanesDataUrl: {type: String, default: ''},
        columnConfiguration: {type: String, default: '[]'},
        facetConfiguration: {type: String, default: '[]'},
        globals: {type: String, default: '[]'},
        modalTemplate: {type: String, default: ''},
        locale: {type: String, default: 'no-locale!'},
        style: {type: String, default: 'spreadsheet'},
        cascadePanes: {type: Boolean, default: false},
        viewTotal: {type: Boolean, default: false},
        index: {type: String, default: ''}, // meili
        dom: {type: String, default: 'QBlfrtip'}, // use P for searchPanes
        searchBuilderFields: {type: String, default: '[]'},
        filter: String, // json, from queryString, e.g. party=dem
        buttons: String // json, from queryString, e.g. party=dem
    }

    // with searchPanes dom: {type: String, default: 'P<"dtsp-dataTable"rQfti>'},
    // sortableFields: {type: String, default: '[]'},
    // searchableFields: {type: String, default: '[]'},

    cols() {
        // see https://javascript.plainenglish.io/are-javascript-object-keys-ordered-and-iterable-5147eedb26ce
        // const map1 = new Map();
        // map1.set('a', 1);
        let columns = this.columns.sort(function(a, b) {
            return   a.order - b.order; // Sort in ascending order
        });

        let x = columns.map(c => {
            let render = null;
            c.className = c.title;
            if (c.twigTemplate) {
                // console.warn(c.twigTemplate);
                let template = Twig.twig({
                    id: c.name,
                    data: c.twigTemplate
                });
                // console.error(template.id);
                render = (data, type, row, meta) => {
                    // Object.assign(row, );
                    // row.locale = this.localeValue;

                    // console.warn(meta); // row, columns, settings
                    let rowName = 'xx';
                    let params = {
                        [c.name]: row[c.name],
                        data: data, dtType: type, row: row, globals: this.globals, column: c, field_name: c.name};
                    // params[rowName] = row;
                    // [key]: 'ES6!'
                    // params[c.name] = row[c.name];
                    params._keys = null;
                    // console.error(params);
                    return template.render(params);
                }
            }

            if (c.name === '_actions') {
                return this.actions({prefix: c.prefix, actions: c.actions})
            }

            // https://datatables.net/reference/option/columns
            let column =
                this.c({
                propertyName: c.name,
                data: c.name,
                label: c.title,
                route: c.route,
                locale: c.locale,
                render: render,
                sortable: (typeof c.sortable)?c.sortable:false,
                className: c.className
            });

            column.searchPanes = {
                show: c.browsable,
                dtOpts: {
                    info: true,
                    columnDefs: [
                        {
                            targets: 0,
                            className: 'f32'
                        }
                    ]
                }
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
        return x;

    }

    connect() {
        super.connect(); //

        this.apiParams = {}; // initialize
        const event = new CustomEvent("changeFormUrlEvent", {formUrl: 'testing formURL!'});
        window.dispatchEvent(event);
        this.globals = JSON.parse(this.globalsValue);


        this.columns = JSON.parse(this.columnConfigurationValue);
        this.facets = JSON.parse(this.facetConfigurationValue);
        console.table(this.facets);
        // "compile" the custom twig blocks
        // var columnRender = [];
        this.dom = this.domValue;
        // dom: 'Plfrtip',
        console.assert(this.dom, "Missing dom");

        this.filter = JSON.parse(this.filterValue || '[]')
        // console.error(this.buttonsValue);
        this.buttons = JSON.parse(this.buttonsValue || '[]');
        this.buttonMap = new WeakMap();
        this.x = {};
        this.buttons.forEach(button => {
            console.error(button.label);
            // this.buttonMap.set(button.label, button);
            this.x[button.label] = button;
            // this.urlByCode[button.label] = button.url;
            console.log("adding " + button.label);
            this.buttons.push({
                text: button.label,
                action:  ( e, dt, node, config ) => {
                    let key = config.text;
                    let button = this.x[key];

                    // Create a base URL
                    // const url = new URL(button.url);

// Create URLSearchParams object
                    const params = new URLSearchParams();

// Add query parameters
                    if (this.apiParams.search??false) {
                        params.append("q", this.apiParams.search);
                    }
                    params.append("ff[]", this.apiParams.facet_filter);

// Set the search property of the URL object
//                     console.error( params.toString());

// Get the final URL string

                    // @todo: add this.apiParams to the url
                    // console.log(this.apiParams);
                    window.open(button.url+'?'+params.toString(), '_blank').focus();
                    // dt.ajax.reload();
                }
                // action: {
                //     // console.log("open " + button.url);
                //     // open url,maybe in new tab
                // },
            })
        })

        // this.sortableFields = JSON.parse(this.sortableFieldsValue);
        // this.searchableFields = JSON.parse(this.searchableFieldsValue);
        this.searchBuilderFields = JSON.parse(this.searchBuilderFieldsValue);

        this.locale = this.localeValue;
        this.viewTotal = true; // this.viewTotalValue;
        this.cascadePanes = false; // never with serverSide: true! this.cascadePanesValue;
        console.log('hola from ' + this.identifier + ' locale: ' + this.localeValue);
        // console.assert(this.hasModalTarget, "Missing modal target");
        this.that = this;
        this.tableElement = false;
        if (this.hasTableTarget) {
            this.tableElement = this.tableTarget;
        } else if (this.element.tagName === 'TABLE') {
            this.tableElement = this.element;
        } else {
            this.tableElement = document.getElementsByTagName('table')[0];
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
        console.error('yay, open modal!', e, e.currentTarget, e.currentTarget.dataset);

        this.modalTarget.addEventListener('show.bs.modal', (e) => {
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
        let transitionButtons = el.querySelectorAll('button.transition');
        transitionButtons.forEach(btn => btn.addEventListener('click', (event) => {
            const isButton = event.target.nodeName === 'BUTTON';
            if (!isButton) {
                return;
            }
            console.log(event, event.target, event.currentTarget);

            let row = this.dt.row(event.target.closest('tr'));
            let data = row.data();
            console.log(row, data);
            this.notify('deleting ' + data.id);

            // console.dir(event.target.id);
        }));

    }

    requestTransition(route, entityClass, id) {

    }

// eh... not working
    get modalController() {
        return this.application.getControllerForElementAndIdentifier(this.modalTarget, "modal_form")
    }

    addButtonClickListener(dt) {
        console.log("Listening for button.transition and button .btn-modal clicks events");

        dt.on('click', 'tr td button.transition', ($event) => {
            console.log($event.currentTarget);
            let target = $event.currentTarget;
            var data = dt.row(target.closest('tr')).data();
            let transition = target.dataset['t'];
            console.log(transition, target);
            console.log(data, $event);
            this.that.modalBodyTarget.innerHTML = transition;
            this.modal = new Modal(this.modalTarget);
            this.modal.show();

        });

        // dt.on('click', 'tr td button .btn-modal',  ($event, x) => {
        dt.on('click', 'tr td button ', ($event, x) => {
            console.log($event, $event.currentTarget);
            var data = dt.row($event.currentTarget.closest('tr')).data();
            console.log(data, $event, x);
            console.warn("dispatching changeFormUrlEvent");
            const event = new CustomEvent("changeFormUrlEvent", {formUrl: 'test'});
            window.dispatchEvent(event);


            let btn = $event.currentTarget;
            let modalRoute = btn.dataset.modalRoute;
            if (modalRoute) {
                this.modalBodyTarget.innerHTML = data.code;
                this.modal = new Modal(this.modalTarget);
                this.modal.show();
                console.assert(data.rp, "missing rp, add @Groups to entity")
                let formUrl = Routing.generate(modalRoute, {...data.rp, _page_content_only: 1});
                console.warn("dispatching changeFormUrlEvent");
                const event = new CustomEvent("changeFormUrlEvent", {detail: {formUrl: formUrl}});
                window.dispatchEvent(event);
                document.dispatchEvent(event);

                console.log('getting formURL ' + formUrl);


                axios.get(formUrl)
                    .then(response => this.modalBodyTarget.innerHTML = response.data)
                    .catch(error => this.modalBodyTarget.innerHTML = error)
                ;
            }

        });
    }

    addRowClickListener(dt) {
        dt.on('click', 'tr td', ($event) => {
            let el = $event.currentTarget;
            console.log($event, $event.currentTarget);
            var data = dt.row($event.currentTarget).data();
            var btn = el.querySelector('button');
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
                console.assert(data.rp, "missing rp, add @Groups to entity")
                let formUrl = Routing.generate(modalRoute, data.rp);


                axios({
                    method: 'get', //you can set what request you want to be
                    url: formUrl,
                    // data: {id: varID},
                    headers: {
                        _page_content_only: '1' // could send blocks that we want??
                    }
                })
                    .then(response => this.modalBodyTarget.innerHTML = response.data)
                    .catch(error => this.modalBodyTarget.innerHTML = error)
                ;
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
        Object.entries(filterColumns).forEach(entry => {
            const [key, value] = entry;
            searchFieldsByColumnNumber.push(key);
        });

        this.columns.forEach((column, index) => {
            let name = "";
            if(typeof column == 'string') {
                name = column;
            } else {
                name = column.name;
            }
            if (this.filter.hasOwnProperty(name)) {
                preSelectArray.push({
                    column : index,
                    rows: this.filter[name].split("|")
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
            'Accept': 'application/ld+json',
            'Content-Type': 'application/json'
        };

        const userLocale =
            navigator.languages && navigator.languages.length
                ? navigator.languages[0]
                : navigator.language;

        // console.log('user locale: ' + userLocale); // 👉️ "en-US"
        // console.error('this.locale: ' + this.locale);
        if (this.locale !== '') {
            apiPlatformHeaders['Accept-Language'] = this.locale;
            apiPlatformHeaders['X-LOCALE'] = this.locale;
        }

        let language = enLanguage;
        if(this.locale === 'en') {
            language = enLanguage;
        } else if(this.locale === 'es') {
            language = esLanguage;
        }else if(this.locale === 'de') {
            language = deLanguage;

        // }else if(this.locale == 'uk') {
        //     language = ukLanguage;
        // }else if(this.locale == 'hu') {
        //     language = huLanguage;
        // }else if(this.locale == 'hi') {
        //     language = hilanguage;
        }

        var modalTemplate;
        var modalRenderer = DataTable.Responsive.renderer.tableAll({
            tableClass: 'ui table'
        })
        if (this.modalTemplateValue) {
            modalTemplate = Twig.twig({
                data: this.modalTemplateValue
            });
            modalRenderer = ( api, rowIdx, columns ) => {
                let data = api.row(rowIdx).data();
                let params = {data: data, columns: columns, globals: this.globals};
                // params._keys = null;
                // console.error(params);
                // return this.modalTemplateValue;
                return modalTemplate.render(params);
                console.log(rowIdx);
            }
        }

        let setup = {
            // let dt = new DataTable(el, {
            language: language,
            createdRow: this.createdRow,
            // paging: true,
            scrollY: '70vh', // vh is percentage of viewport height, https://css-tricks.com/fun-viewport-units/
            // scrollY: true,
            // displayLength: 50, // not sure how to adjust the 'length' sent to the server
            // pageLength: 15,
            orderCellsTop: true,
            fixedHeader: true,
            //cascadePanes  : true,
            deferRender: true,
            // scrollX:        true,
            // scrollCollapse: true,
            scroller: true,
            responsive: true,
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
                dt.on('searchPanes.rebuildPane', function() {
                    // This function will run after the user selects a value from the SearchPane
                    console.log('A selection has been made and the table has been updated.');
                });
                this.handleTrans(el);

                const box = document.getElementsByClassName('dtsp-title');
                if (box.length) {
                    box[0].style.display = "none";
                }

                // let xapi = new DataTable.Api(obj);
                // console.log(xapi);
                // console.log(xapi.table);
                // this.addRowClickListener(dt);
                // let searchPane = dt.searchPanes.panes[6];
                // searchPane.selected.push('inactive');
                // dt.search(dt.columns().search()).draw();
            },

            dom: this.dom,
            buttons: this.buttons,
            xxbuttons: (x) => {
                // why isn't this being called?
                console.error(x);
                let buttons =  [
                    'copy', 'csv', 'excel', 'pdf', 'print',
                    {
                        text: 'labels',
                        action:  ( e, dt, node, config ) =>  {
                            // window.open(Routing.generate('owner_labels', {
                            //     ownerId: 1,
                            //     pixieCode: pixie
                            // }))
                            console.log("open url, pass the params ", this.apiParams);
                            const event = new CustomEvent("changeSearchEvent", {detail: this.apiParams});
                            window.dispatchEvent(event);
                        }
                    }
                ];
                console.error(this.buttons);
                this.buttons.forEach((button, index) => {
                    buttons.push({
                        text: 'x',
                        action: ( e, dt, node, config ) =>  {
                            // console.log(e, config);
                            // open url,maybe in new tab
                        },
                    })
                    console.log(button, index);
                });
                return buttons;
            },
            columns: this.cols(),
            searchPanes: {
                initCollapsed: true,
                dtOpts: {
                    scrollCollapse: true,
                    // paging: true
                },
                layout: 'columns-1',
                show: true,
                cascadePanes: this.cascadePanes,
                viewTotal: true,
                preSelect: preSelectArray
            },
            searchBuilder: {
                columns: this.searchBuilderFields,
                depthLimit: 1,
                threshold: 0,
                showEmptyPanes: true
            },
            // columns:
            //     [
            //     this.c({
            //         propertyName: 'name',
            //     }),
            // ],
            columnDefs: this.columnDefs(searchFieldsByColumnNumber),
            // https://datatables.net/reference/option/ajax
            ajax: (params, callback, settings) => {
                console.error(`starting at ${params.start}`);
                this.messageTarget.innerHTML = `starting at ${params.start}`;
                this.apiParams = this.dataTableParamsToApiPlatformParams(params, searchPanesRaw);
                // this.debug &&
                // console.error(this.apiParams);
                // console.assert(params.start, `DataTables is requesting ${params.length} records starting at ${params.start}`, this.apiParams);

                Object.assign(this.apiParams, this.filter);
                // yet another locale hack
                if (this.locale !== '') {
                    this.apiParams['_locale'] = this.locale;
                }
                // check for meili index
                if (this.hasIndexValue) {
                    this.apiParams['_index'] = this.indexValue;
                }
                if (this.hasStyleValue) {
                    this.apiParams['_style'] = this.styleValue;
                }

                // console.warn(apiPlatformHeaders);

                let request = axios.get(this.apiCallValue, {
                    params: this.apiParams,
                    headers: apiPlatformHeaders
                })
                    .then((response) => {
                        // handle success
                        let hydraData = response.data;

                        var total = hydraData.hasOwnProperty('totalItems') ? hydraData['totalItems'] : 999999; // Infinity;
                        var itemsReturned = hydraData['member'].length;
                        // let first = (params.page - 1) * params.itemsPerPage;
                        if (params.search.value) {
                            console.log(`dt search: ${params.search.value}`);
                        }

                        // console.log(`dt request: ${params.length} starting at ${params.start}`);

                        // let first = (apiOptions.page - 1) * apiOptions.itemsPerPage;
                        let d = hydraData['member'];
                        if (d.length) {
                            console.table(d[0]);
                            // console.log('first result', d[0]);
                        }
                        let searchPanes = {};
                        searchPanes = {
                            initCollapsed: true,
                            layout: 'columns-1',
                            show: true,
                            cascadePanes: this.cascadePanes,
                            viewTotal: this.viewTotal,
                            showZeroCounts: true,
                            preSelect: preSelectArray
                        };

                        // options.threshold = 0.01;
                        // options.showZeroCounts = true;
                        // options.cascadePanes = true;
                        // options.viewTotal = true;
                        // options.show = true;
                        // console.error('searchpanes', searchPanes, options);

                        // if searchPanes have been sent back from the results, sort them by browseOrder
                        if(typeof hydraData['facets'] !== "undefined" && typeof hydraData['facets']['searchPanes'] !== "undefined") {
                           searchPanesRaw = hydraData['facets']['searchPanes']['options'];
                           searchPanes = this.sequenceSearchPanes(hydraData['facets']['searchPanes']['options']);
                            console.warn({searchPanes, searchPanesRaw, hydraData})
                        } else {
                           searchPanes.options = options;
                           console.error(options, 'no searchPanes returned in search');
                        }
                        // searchPanes.threshold = 0.01;
                        searchPanes.showZeroCounts = true;
                        searchPanes.cascadePanes = true;
                        searchPanes.viewTotal = true;
                        searchPanes.show = true;
                        let targetMessage = "";
                        if(typeof this.apiParams.facet_filter != 'undefined') {
                            this.apiParams.facet_filter.forEach((index) => {
                                // format is (probably) facet,value1|value2
                                if(targetMessage !== "") {
                                    targetMessage += ", ";
                                }
                                let string = index.split(',');
                                if (string.length > 0) {
                                    let firstPart = string[0];
                                let splitValue = string[2].split("|");
                                let returnValue = [];
                                splitValue.forEach((index) => {
                                        if (firstPart in searchPanes['options']) {
                                            searchPanes['options'][firstPart].forEach((array) => {
                                                if(index === array.value) {
                                            returnValue.push(array.label);
                                            return false;
                                        }
                                    });
                                        }

                                });
                                targetMessage += string[0]+ " : "+ returnValue.join('|');
                                }
                            });
                        }
                        this.messageTarget.innerHTML = targetMessage;

                        // if next page isn't working, make sure api_platform.yaml is correctly configured
                        // defaults:
                        //     pagination_client_items_per_page: true

                        // if there's a "next" page and we didn't get everything, fetch the next page and return the slice.
                        let next = hydraData["view"]['next'];
                        // we need the searchpanes options, too.



                        let callbackValues = {
                            draw: params.draw,
                            data: d,
                            searchPanes: searchPanes,
                            recordsTotal: total,
                            recordsFiltered: total, //  itemsReturned,
                        }
                        callback(callbackValues);
                    })
                    .catch((error) => {
                        console.error(error, error.request);
                        let url = error.request.responseURL;
                        var a = document.createElement('a');
                        var linkText = document.createTextNode(url);
                        a.href = url;
                        this.messageTarget.innerHTML = '<div class="bg-danger">' + error.message + '</div> ' +
                            `<a target="_blank" href="${url}">${url}</a>`;
                        console.error(error);
                    })
                ;

            },
        };
        let dt = new DataTable(el, setup);
        if (this.filter.hasOwnProperty('P')) {
        }
        dt.searchPanes();
        if (this.filter.hasOwnProperty('q')) {
            dt.search(this.filter.q).draw();
        }

        this.filter = [];
        this.columns.forEach((column, index) => {
            if(column.order == 0) {
                dt.column(index).visible(false);
            }
        });

        console.log('moving panes to div.search-panes');
        $("div.dtsp-verticalPanes").append(dt.searchPanes.container());
        // $("div.search-panes").append(dt.searchPanes.container());
        if (this.filter.hasOwnProperty('P')) {
        }
        const contentContainer = document.getElementsByClassName('search-panes');
        if (contentContainer.length > 0) {
            const ps = new PerfectScrollbar(contentContainer[0]);
        }
        return dt;
    }

    columnDefs(searchPanesColumns) {
        return [
            {
                searchPanes:
                    {show: true},
                    target: searchPanesColumns

            },
            {targets: [0, 1], visible: true},
            // {targets: '_all',  order:  },
            // defaultContent is critical! Otherwise, lots of stuff fails.
            {targets: '_all', visible: true, sortable: false, "defaultContent": "~~"}
        ];

        // { targets: [0, 1], visible: true},
        // { targets: '_all', visible: true, sortable: false,  "defaultContent": "~~" }
    }


// get columns() {
//     // if columns isn't overwritten, use the th's in the first tr?  or data-field='status', and then make the api call with _fields=...?
//     // or https://datatables.net/examples/ajax/null_data_source.html
//     return [
//         {title: '@id', data: 'id'}
//     ]
// }

    actions({prefix = null, actions = ['edit', 'show', 'qr']} = {}) {
        let icons = {
            edit: 'fas fa-edit',
            show: 'fas fa-eye text-success',
            'qr': 'fas fa-qrcode',
            'delete': 'fas fa-trash text-danger'
        };
        let buttons = actions.map(action => {
            let modal_route = prefix + action;
            let icon = icons[action];
            // return action + ' ' + modal_route;
            // Routing.generate()

            return `<button data-modal-route="${modal_route}" class="btn btn-modal btn-action-${action}" 
title="${modal_route}"><span class="action-${action} fas fa-${icon}"></span></button>`;
        });

        // console.log(buttons);
        return {
            title: 'actions',
            render: () => {
                return buttons.join(' ');
            }
        }
        actions.forEach(action => {
        })

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
          renderType = 'string',
          sortable = false,
          className = null,
      } = {}) {

        if (render === null) {
            render = (data, type, row, meta) => {
                // if (!label) {
                //     // console.log(row, data);
                //     label = data || propertyName;
                // }
                let displayData = data;
                // @todo: move some twig templates to a common library
                if (renderType === 'image') {
                    return `<img class="img-thumbnail plant-thumb" alt="${data}" src="${data}" />`;
                }

                if (route) {
                    if (locale) {
                        row.rp['_locale'] = locale;
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
                        let elements = propertyName.split('.');
                        if (elements.length === 3) {
                            let x1 = elements[0];
                            let x2 = elements[1];
                            let x3 = elements[2];
                            return row[x1][x2][x3];
                        } else if (elements.length === 2) {
                            // hack, only one level deep, etc.  ugh
                            let x1 = elements[0];
                            let x2 = elements[1];
                            return row[x1][x2];
                        } else {
                            return row[propertyName];
                        }
                    }
                }

            }
        }

        return {
            title: label,
            className: className,
            data: propertyName || '',
            render: render,
            sortable: sortable
        }
        // ...function body...
    }

    guessColumn(v) {

        let renderFunction = null;
        switch (v) {
            case 'id':
                renderFunction = (data, type, row, meta) => {
                    console.warn('id render');
                    return "<b>" + data + "!!</b>"
                }
                break;
            case 'newestPublishTime':
            case 'createTime':
                renderFunction = (data, type, row, meta) => {
                    let isoTime = data;
                    let str = isoTime ? '<time class="timeago" datetime="' + data + '">' + data + '</time>' : '';
                    return str;
                }
                break;
            // default:
            //     renderFunction = ( data, type, row, meta ) => { return data; }


        }
        let obj = {
            title: v,
            data: v,
        }
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
        // same as #[ApiFilter(MultiFieldSearchFilter::class, properties: ["label", "code"], arguments: ["searchParameterName"=>"search"])]
        if (params.search && params.search.value) {
            apiData['search'] = params.search.value;
            facetsUrl.push( 'q=' + params.search.value);
        }

        let order = {};
        if (params.searchBuilder) {
            apiData['searchBuilder'] = params.searchBuilder;
        }
        if (params.style) {
            apiData['_style'] = params.style;
        }
        // https://jardin.wip/api/projects.jsonld?page=1&itemsPerPage=14&order[code]=asc
        params.order.forEach((o, index) => {
            let c = params.columns[o.column];
            if (c.data && c.orderable && c.orderable == true) {
                order[c.data] = o.dir;
                // apiData.order = order;
                apiData['order[' + c.data + ']'] = o.dir;
            }
            // console.error(c, order, o.column, o.dir);
        });

        let currentUrl = window.location.href;

        // Remove the protocol and domain
        let urlWithoutProtocolAndDomain = currentUrl.replace(/^.*\/\/[^\/]+/, '');

        // Extract the path
        let path = urlWithoutProtocolAndDomain.split('?')[0];
        let facetsFilter = [];
        if (params.searchPanes) {
            for (const [key, value] of Object.entries(params.searchPanes)) {
                if (Object.values(value).length) {
                    facetsFilter.push(key + ',in,' + Object.values(value).join('|'));
                    facetsUrl.push(key + '=' + Object.values(value).join('|'));
                }
            }
        }

        for (const [key, value] of Object.entries(this.filter)) {
            if(key !== 'q') {
                facetsFilter.push(key + ',in,' + value);
            }
            facetsUrl.push(key + '=' + value);
        }
        if(facetsUrl.length > 0) {
            path +="?"+facetsUrl.join('&');
            history.replaceState(null, "", path);
        } else {
            history.replaceState(null, "", path);
        }

        if(facetsFilter.length > 0) {
            apiData['facet_filter'] = facetsFilter;
        }

        if (params.searchBuilder && params.searchBuilder.criteria) {
            params.searchBuilder.criteria.forEach((c, index) => {
                console.warn(c);
                apiData[c.origData + '[]'] = c.value1;
            });
        }
        // let facets = [];
        // this.facets.forEach(function (column, index) {
        //     // if ( column.browsable ) {
        //         facets.push(column.name);
        //     // }
        // });
        // console.error({facets});

        // we don't do anything with facets!  So we probably don't need the above.
        params.columns.forEach(function (column, index) {
            if (column.search && column.search.value) {
                // check the first character for a range filter operator
                // data is the column field, at least for right now.
                apiData[column.data] = column.search.value;

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

    sequenceSearchPanes(data) {
        let newOrderdata = [];
        let searchPanesOrder = this.columns.filter(function(columnConfig) {
            return columnConfig.browsable === true;
        });

        searchPanesOrder = searchPanesOrder.sort(function (a, b) {
            if(a.browseOrder != b.browseOrder) {
                return a.browseOrder - b.browseOrder;
            }
            let aData = 0;
            let bData = 0;

            if(typeof data[a.name] != 'undefined') {
                aData = data[a.name].length;
            }

            if(typeof data[b.name] != 'undefined') {
                bData = data[b.name].length;
            }

            return  bData - aData;
        });

        searchPanesOrder.forEach(function (index){
           if(typeof data[index.name] != 'undefined') {
               newOrderdata[index.name] =  data[index.name];
           } else {
               console.warn(index.name);
               // newOrderdata[index.name] =  data[index.name];
           }
        });

        let newOptionOrderData = [];
        newOptionOrderData['options'] = newOrderdata;
        console.log({newOptionOrderData, newOrderdata, searchPanesOrder});
        return newOptionOrderData;
    }

    initFooter(el) {
        return;

        let footer = el.querySelector('tfoot');
        if (footer) {
            return; // do not initiate twice
        }

        var handleInput = function (column) {
            var input = $('<input class="form-control" type="text">');
            input.attr('placeholder', column.filter.placeholder || column.data);
            return input;
        };

        this.debug && console.log('adding footer');
        footer = el.createTFoot();
        footer.classList.add('show-footer-above');

        var thead = el.querySelector('thead');
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
            }
        );
        // footer = $('<tfoot>');
        // footer.append(tr);
        // console.log(footer);
        // this.el.append(footer);

        // see http://live.datatables.net/giharaka/1/edit for moving the footer to below the header
    }

}
