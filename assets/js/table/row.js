'use strict';
import Helper from './lib/helper.js';
import Cell from './cell.js';
import Link from './ae/link.js';
//10-01-2024
export default class Row {

    constructor(table, data, index = - 1, append = true) {

        if (!data) {
            return false;
        }

        this.table = table;
        this.table_instance_key = this.table.instance_key;
        this.wrapper = this.table.container;
        this.header_data = this.table.data.header;
        this.data = data;
        this.index = index;
        this.append = append;

        this.cells = [];
        this.instance_key = Helper.generate_key('r-'); //!! for attaching document events
        this.container = Helper.create_element('data-table-row', data?.extra?.id ? {
            'data-id': data?.extra?.id,
            //row additional data
        } : {});

        //cell css apply
        if (this.data?.extra?.row_css && this.data.extra.row_css) {
            for (const [key, value] of Object.entries(this.data.extra.row_css)) {
                this.container.style.setProperty(key, value);
            }
        }

        if (this.data?.extra?.attributes && this.data.extra.attributes) {
            for (const [att, att_value] of Object.entries(this.data.extra.attributes)) {
                this.container.setAttribute(att, att_value);
            }
        }

        this.draw();

        //+++

        Helper.addSingleEventListener('data-table-remove-row', this, e => {
            if (e.detail.id === data?.extra?.id && this.table_instance_key === e.detail.table_instance_key) {
                this.delete();
            }
        });
    }

    draw() {
        Object.values(this.header_data).forEach(col_d => {
            this.cells[this.cells.length] = new Cell(this.table, this.container, this.header_data, col_d.key, this.data[col_d.key], this.cells.length, this.index, this.data?.extra);
        });

        this.append
                ? this.wrapper.querySelector('data-table-head').after(this.container)
                : this.wrapper.querySelector('data-table-foot').before(this.container);

    }

    redraw(data = null) {
        if (data && data.length > 0) {
            data.forEach((value, index) => {
                this.redraw_cell(index, value);
            });
    }
    }

    redraw_cell(index, value) {
        this.cells[index].set_value(value, false); //no cast
    }

    get_cell_by_key(key) {
        let res = null;

        if (this.cells.length > 0) {
            this.cells.forEach(c => {
                if (c.key === key) {
                    res = c;
                    return;
                }
            });
        }

        return res;
    }

    delete() {
        this.container.remove();
    }

    display(state) {
        if (Boolean(state)) {
            //show
            this.container.style.removeProperty('display');
            this.container.removeAttribute('hidden');
        } else {
            //hide
            this.container.style.display = 'none';
            this.container.setAttribute('hidden', '');
        }
    }
}

