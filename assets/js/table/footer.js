'use strict';
import Helper from './lib/helper.js';
import Cell from './cell.js';

//26-07-2024
export default class Footer {
    constructor(table, wrapper, data) {
        this.table = table;
        this.table_instance_key = this.table.instance_key;
        this.wrapper = wrapper;
        this.data = data;
        this.cells = [];
        this.instance_key = Helper.generate_key('f-');//!! for attaching document events
        this.container = Helper.create_element('data-table-foot');
        this.draw();
    }

    draw() {
        Object.values(this.data).forEach(data => this.cells[this.cells.length] = new Cell(this, this.container, this.data, 'title', data.value, this.cells.length));
        this.wrapper.appendChild(this.container);
    }
}


