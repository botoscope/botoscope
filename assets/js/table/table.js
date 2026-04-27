'use strict';
import Helper from './lib/helper.js';
import Header from './header.js';
import Footer from './footer.js';
import Row from './row.js';
import Pagination from './lib/pagination.js';
import Form from './lib/form.js';

//26-11-2025
export default class Table {
    constructor(wrapper, data, constant_key = '', callbacks = []) {
        this.wrapper = wrapper;//where to place
        this.data = data;//what to show

        this.rows = [];//html
        this.per_page = -1;
        this.search = {};
        this.constant_key = constant_key;//for saving columns positions and cell width for this current table
        this.callbacks = callbacks;
        this.instance_key = data.instance_key??Helper.generate_key('t-');//!! for attaching single document events

        this.data.rows = [];
        if (this.data.raw_rows_data.length > 0) {
            this.data.raw_rows_data.forEach(rd => this.data.rows.push(this.data.format_data(rd)));
        }

        this.create();
    }

    async create() {
        this.container = Helper.create_element('data-table');
        if (this.data.attributes) {
            for (const [att, att_value] of Object.entries(this.data.attributes)) {
                this.container.setAttribute(att, att_value);
            }
        }

        if (this.get_attribute_value('per-page')) {
            this.per_page = parseInt(this.get_attribute_value('per-page'));
            this.records_count = parseInt(this.get_attribute_value('records-count'));
            this.pagination_1 = new Pagination(this.instance_key, this.wrapper, this.per_page, this.records_count, false);
        }

        this.wrapper.appendChild(this.container);

        //+++

        if (this.get_attribute_value('per-page')) {
            this.pagination_2 = new Pagination(this.instance_key, this.wrapper, this.per_page, this.records_count);
        }

        this.data.header = await this.prepare_cols_positions(this.data.header);
        this.draw();
        this.events();
    }

    draw_pagination() {
        if (this.per_page <= 0) {
            this.pagination_1.hide();
            this.pagination_2.hide();
        } else {
            this.pagination_1.show();
            this.pagination_2.show();
        }
    }

    get_attribute_value(attribute) {

        attribute = this.prepare_attribute_name(attribute);

        if (this.container.dataset[attribute]) {
            return this.container.dataset[attribute];
        }

        return null;
    }

    set_attribute_value(attribute, value) {
        this.container.dataset[this.prepare_attribute_name(attribute)] = value;
    }

    prepare_attribute_name(attribute) {
        attribute = attribute.split('-');
        if (attribute.length > 1) {
            let res = '';
            for (let i = 0; i < attribute.length; i++) {
                if (i === 0) {
                    res = attribute[i];
                    continue;
                }

                res += attribute[i].charAt(0).toUpperCase() + attribute[i].slice(1);
            }

            attribute = res;
        } else {
            attribute = attribute[0];
        }

        return attribute;
    }

    async draw() {

        this.container.innerHTML = '';

        this.header();
        this.footer();

        if (Object.values(this.data.rows).length > 0) {
            this.data.rows.forEach((data, index) => this.rows.push(new Row(this, data, index, false)));
        }

        if (this.data.rows.length === 0) {
            this.show_no_records();
        }

        Helper.cast('data-table-drawn', {
            data: {
                table: this
            }
        });
    }

    redraw(raw_rows_data = {}, rewrite = false) {
        this.header();
        this.footer();
        this.container.innerHTML = '';
        this.rows = [];

        if (rewrite) {
            this.data.rows = [];
            this.data.raw_rows_data = raw_rows_data;
            if (Object.values(this.data.raw_rows_data).length > 0) {
                this.data.raw_rows_data.forEach(rd => this.data.rows.push(this.data.format_data(rd)));
            }
        }

        this.draw();
        this.draw_pagination();
        try {
            this.pagination_1.redraw(this.records_count);
            this.pagination_2.redraw(this.records_count);
        } catch (e) {
            //+++
        }
        this.delete_loader();
        this.do_after_redraw();
    }

    show_no_records(redraw = true) {
        if (this.data.rows.length === 0 || (this.data.rows.length === 1 && this.data.rows[0].extra.id === -1)) {
            let width = 0;
            this.data.header.forEach(h => {
                width += parseInt(h.width);
            });

            this.data.rows = [
                {
                    [this.data.header[0].key]: botoscope_lang.no_data,
                    extra: {
                        id: -1,
                        row_css: {width: `${width}%`},
                    }
                },
            ];

            if (redraw) {
                this.redraw();
            }
    }
    }

    async set_table_col_positions(positions, key, index) {
        localStorage.setItem(`${this.constant_key}_columns_positions`, positions.join(','));

        await Helper.ajax('botoscope_set_table_col_positions', {
            what: this.constant_key,
            keys: positions.join(',')
        }, null, false);
    }

    async prepare_cols_positions(data_header) {
        let positions = localStorage.getItem(`${this.constant_key}_columns_positions`);

        if (positions === null) {
            await Helper.ajax('botoscope_get_table_col_positions', {
                what: this.constant_key,
            }, res => {
                positions = res;
                localStorage.setItem(`${this.constant_key}_columns_positions`, positions);
            }, false);
        }

        if (positions) {
            let header_new_data = [];
            positions = positions.split(',');

            for (let i = 0; i < positions.length; i++) {
                for (let y = 0; y < data_header.length; y++) {
                    if (typeof data_header[y] === 'object' && data_header[y].key === positions[i]) {
                        header_new_data.push(data_header[y]);
                        delete data_header[y];
                        break;
                    }
                }
            }

            //rest which not ordered
            for (let i = 0; i < data_header.length; i++) {
                if (typeof data_header[i] === 'object') {
                    header_new_data.push(data_header[i]);
                }
            }

            data_header = header_new_data;
        }

        return data_header;
    }

    header() {
        if (this.head) {
            this.head.container.remove();
        }

        this.head = new Header(this, this.container, this.data.header);
    }

    footer() {
        let show = 1;
        if ('footer' in this.data) {
            show = this.data.footer;
        }

        if (this.foot) {
            this.foot.container.remove();
        }

        this.foot = new Footer(this, this.container, this.data.header);

        if (!show) {
            this.foot.container.style.display = 'none';
        }

    }

    events() {
        Helper.addSingleEventListener('data-table-create-new-row', this, e => {
            if (this.instance_key === e.detail.data.table_instance_key) {
                let data = e.detail.data;
                this.create_row(data, false);
                this.data_is_mutated('create_row', data);
            }
        });

        Helper.addSingleEventListener('data-table-create-new-col', this, e => {
            if (this.instance_key === e.detail.data.table_instance_key) {
                this.add_column(e.detail.data.key, e.detail.data.value, e.detail.data.title);
            }
        });

        Helper.addSingleEventListener('data-table-delete-col', this, e => {
            if (this.instance_key === e.detail.data.table_instance_key) {
                let key = e.detail.data.key;

                this.data.header = this.data.header.filter(item => {
                    if (item.key !== key) {
                        return item;
                    }
                });

                for (let i = 0; i < this.data.rows.length; i++) {
                    delete this.data.rows[i][key];
                }

                this.redraw();
                this.data_is_mutated('delete_col', {key});
            }
        });

        Helper.addSingleEventListener('data-table-move-col-right', this, e => {
            if (this.instance_key === e.detail.data.table_instance_key) {
                let key = e.detail.data.key;
                let index = 0;
                let tmp;

                for (let i = 0; i < this.data.header.length; i++) {
                    if (this.data.header[i].key === key) {
                        index = i;
                        break;
                    }
                }

                for (let i = 0; i < this.data.header.length; i++) {
                    if (i === index) {
                        tmp = this.data.header[i];
                        this.data.header[i] = this.data.header[i + 1];
                    }

                    if (i === index + 1) {
                        this.data.header[i] = tmp;
                    }
                }

                index += 1;
                let positions = [];
                for (let i = 0; i < this.data.header.length; i++) {
                    positions.push(this.data.header[i].key);
                }

                this.data_is_mutated('move_col_right', {positions, key, index});
                this.redraw();
            }
        });

        Helper.addSingleEventListener('data-table-move-col-left', this, e => {
            if (this.instance_key === e.detail.data.table_instance_key) {
                let key = e.detail.data.key;
                let index = 0;
                let tmp;

                for (let i = 0; i < this.data.header.length; i++) {
                    if (this.data.header[i].key === key) {
                        index = i;
                        break;
                    }
                }

                for (let i = 0; i < this.data.header.length; i++) {
                    if (i === index) {
                        tmp = this.data.header[i];
                        this.data.header[i] = this.data.header[i - 1];
                        break;
                    }
                }


                for (let i = 0; i < this.data.header.length; i++) {
                    if (i === index - 1) {
                        this.data.header[i] = tmp;
                        break;
                    }
                }


                index -= 1;
                let positions = [];
                for (let i = 0; i < this.data.header.length; i++) {
                    positions.push(this.data.header[i].key);
                }

                this.data_is_mutated('move_col_left', {positions, key, index});
                this.redraw();
            }
        });

        Helper.addSingleEventListener('data-table-cell-data-changed', this, e => {
            if (this.instance_key === e.detail.data.table_instance_key) {
                let key = e.detail.data.key;
                let value = e.detail.data.value;
                let extra = e.detail.data.extra;

                this.update_rows_data(extra.id, key, value, extra);
            }
        });

        Helper.addSingleEventListener('data-table-order-col-data', this, e => {
            if (this.instance_key === e.detail.data.table_instance_key) {
                let key = e.detail.data.key;
                let order = e.detail.data.order;

                for (let i = 0; i < this.data.header.length; i++) {
                    if (this.data.header[i].key === key) {
                        this.data.header[i].order = order;
                        break;
                    }
                }

                this.container.dataset.orderBy = key;
                this.container.dataset.order = order;

                this.data_is_mutated('order_col_data', {key, order});

                if (this.pagination_1.table_instance_key) {
                    this.pagination_1.current_page = 0;
                    this.pagination_1.redraw();
                }

                if (this.pagination_2.table_instance_key) {
                    this.pagination_2.current_page = 0;
                    this.pagination_2.redraw();
                }

            }
        });

        Helper.addSingleEventListener('data-table-set-page', this, e => {
            if (this.instance_key === e.detail.data.table_instance_key) {
                const page = e.detail.data.page;
                this.set_page(page);
                this.pagination_1.current_page = this.pagination_2.current_page = page;
            }
        });
    }

    //can be overloaded on over
    data_is_mutated(operation, data) {
        Helper.cast('data-table-data-is-mutated', {
            data: {
                operation,
                data,
                table_instance_key: this.instance_key
            }
        });
    }

    //api
    create_row(data = null, append = true) {
        //what to do when new row is created
        this.records_count += 1;
        this.set_attribute_value('records-count', this.records_count);

        if (this.pagination_1.table_instance_key) {
            this.pagination_1.redraw(this.records_count);
        }

        if (this.pagination_2.table_instance_key) {
            this.pagination_2.redraw(this.records_count);
    }
    }

    remove_row(row_id) {
        row_id = parseInt(row_id);
        Helper.cast('data-table-remove-row', {id: row_id, table_instance_key: this.instance_key});

        if (this.rows.length > 0) {
            this.data.raw_rows_data = this.data.raw_rows_data.filter(item => parseInt(item.id) !== row_id);
            this.data.rows = this.data.rows.filter(item => parseInt(item.extra?.id) !== row_id);

            let new_records_count = parseInt(this.get_attribute_value('records-count')) - 1;
            this.container.dataset.recordsCount = new_records_count;
            this.data_is_mutated('delete_row', row_id);

            let reset_page = false;

            if (this.pagination_1.table_instance_key) {
                if (this.rows.length === 0 && this.pagination_1.current_page > 0) {
                    this.pagination_1.current_page = this.pagination_1.current_page - 1;
                    this.show_no_records();
                }

                if (this.rows.length === 0 && this.pagination_1.current_page === 0) {
                    this.show_no_records();
                } else {
                    if (this.pagination_1.pages_count > 1) {
                        reset_page = true;
                    }
                }

                this.pagination_1.redraw(new_records_count);
            }

            if (this.pagination_2.table_instance_key) {
                if (this.rows.length === 0 && this.pagination_2.current_page > 0) {
                    this.pagination_2.current_page = this.pagination_2.current_page - 1;
                    this.show_no_records();
                }

                if (this.rows.length === 0 && this.pagination_2.current_page === 0) {
                    this.show_no_records();
                } else {
                    if (this.pagination_2.pages_count > 1) {
                        reset_page = true;
                    }
                }

                this.pagination_2.redraw(new_records_count);
            }

            if (reset_page) {
                this.set_page(this.pagination_1.current_page);
            }

        }

        this.redraw();
    }

    //api
    get_value(key, id) {

        let rows = this.data.rows.filter(item => {
            if (item?.extra?.id === id) {
                return item;
            }
        });

        if (rows.length) {
            let row = rows[0];
            return (typeof row[key] === 'object' ? row[key].value : row[key])
        }

        return 'no data';
    }

    //api
    add_column(key, value, title, width = '20%') {

        let already_exists = (this.data.header.filter(item => {
            if (item.key === key) {
                return item;
            }
        })).length;//check if such column already exists, if yes just redraw rows

        if (!already_exists) {
            this.data.header.push({
                value: title,
                width: width,
                key: key
            });
        }

        for (let i = 0; i < this.data.rows.length; i++) {
            this.data.rows[i][key] = value;
        }

        this.redraw();

        this.show_no_records();

        if (!already_exists) {
            this.data_is_mutated('create_col', {key, width, title, value});
    }

    }

    add_row(data) {

        let indexToRemove = this.data.rows.findIndex(row => row.extra && row.extra.id === -1);
        //remove row about no-data
        if (indexToRemove !== -1) {
            this.data.rows.splice(indexToRemove, 1);
        }

        this.data.rows.push(this.data.format_data(data));
        this.data.raw_rows_data.push(data);
        this.redraw();
    }

    //api
    get_cell(id, key) {
        let cell = null;

        if (this.rows.length) {
            let row = null;
            for (let i = 0; i < this.rows.length; i++) {
                if (this.rows[i].data.extra.id === id) {
                    row = this.rows[i];
                    break;
                }
            }

            if (row && row.cells.length) {
                for (let i = 0; i < row.cells.length; i++) {
                    if (row.cells[i].key === key) {
                        cell = row.cells[i];
                        break;
                    }
                }
            }
        }

        return cell;
    }

    create_loader(text = 'Loading ...') {
        this.loader = Helper.create_element('data-table-loader');
        this.container.appendChild(this.loader);
        this.loader.innerHTML = text;
    }

    delete_loader() {
        this.container.querySelector('data-table-loader')?.remove();
    }

    update_rows_data(id, key, value, extra = null) {
        for (let i = 0; i < this.data.rows.length; i++) {
            if (id === this.data.rows[i]['extra']['id']) {
                if (typeof this.data.rows[i][key].value === 'object') {
                    this.data.rows[i][key].value.value = value;
                } else if (typeof this.data.rows[i][key] === 'object') {
                    this.data.rows[i][key].value = value;
                } else {
                    this.data.rows[i][key] = value;
                }

                const hash_key = this.data.rows[i]?.['hash_key'];

                this.data_is_mutated('edit_cell', {key, value, extra, hash_key});
                break;
            }
    }
    }

    //for custom elements influence using cell_content_drawn (for example radio buttons)
    update_raw_rows_data(id, key, value) {
        for (let i = 0; i < this.data.raw_rows_data.length; i++) {
            if (id.toString() === this.data.raw_rows_data[i]['id'].toString()) {

                if (typeof this.data.raw_rows_data[i][key].value === 'object') {
                    this.data.raw_rows_data[i][key].value.value = value;
                } else if (typeof this.data.raw_rows_data[i][key] === 'object') {
                    this.data.raw_rows_data[i][key].value = value;
                } else {
                    this.data.raw_rows_data[i][key] = value;
                }

                this.data.rows = [];
                if (this.data.raw_rows_data.length > 0) {
                    this.data.raw_rows_data.forEach(rd => this.data.rows.push(this.data.format_data(rd)));
                }

                break;
            }
        }
    }

    //api
    create_form(wrapper, data) {
        return new Form(this.instance_key, wrapper, data);
    }

    //api
    set_page(page_num) {
        //redefine to use
    }

    //api
    do_after_redraw() {
        //redefine to use
    }

}



