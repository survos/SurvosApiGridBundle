import {Controller} from "@hotwired/stimulus";
import Routing from 'fos-routing';
import RoutingData from '/js/fos_js_routes.js';
Routing.setData(RoutingData);

import Twig from 'twig';
Twig.extend(function (Twig) {
    Twig._function.extend('path', (route, routeParams={}) => {
        // console.error(routeParams);
        delete routeParams._keys; // seems to be added by twigjs
        let path = Routing.generate(route, routeParams);
        return path;
    });
});

console.assert(Routing, 'Routing is not defined');
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['message'];
    static values = {
        blocks: {type: Object },
        data: {type: String, default: '{}'},
        globals: {type: String, default: '{}'},
        apiUrl: {type: String, default: ''},
        searchPanesDataUrl: {type: String, default: ''},
    }

    connect() {
        // console.log("Hello from " + this.identifier);
        this.render()
    }

    async fetchItem() {
        console.warn(this.apiUrlValue);
        const response = await fetch(this.apiUrlValue);

        if (!response.ok) {
            const message = `An error has occured: ${response.status}`;
            throw new Error(message);
        }

        const item = await response.json();
        return item;
    }




    render() {
        let template = Twig.twig({
            data: this.blocksValue.content
        });
        let globals = JSON.parse(this.globalsValue);

        this.fetchItem().then((item) => {
            let params = {data: item, globals: globals};
            params._keys = null;
            // console.error(params);
            let html = template.render(params);
            this.element.innerHTML = html;
        })


        // Object.assign(row, );
        // row.locale = this.localeValue;

        // let data = {'title': 'A title', 'code': 'codigo'};
        // data = fetch()
        // let data = JSON.parse(this.dataValue);
    }


}
