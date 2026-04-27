'use strict';
import Helper from '../lib/helper.js';
import Table from '../table.js';
import Head from '../header.js';
import Foot from '../footer.js';
import Row from '../row.js';

//07-08-2023
export default class Form {
    constructor(table_instance_key, wrapper, data) {
        this.table_instance_key = table_instance_key;
        this.wrapper = wrapper;
        this.data = data;

        this.container = Helper.create_element('div', {class: 'data-table-form'});
        this.wrapper.appendChild(this.container);
        this.draw();
    }

    draw() {
        this.table = new Table(this.container, {...this.data}, 'my_form');//todo my_form
    }
}


