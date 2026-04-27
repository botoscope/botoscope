const languages = {selected_language: null, default_language: null};
let Functions, Helper, Table, Sidebar, Switcher, SelectM23, Calendar, CalendarSelector;
import MetaPack from './meta_pack.js';
//21-03-2025
export default class Meta {
    constructor(product_id, wrapper) {

        if (botoscope_no_bot) {
            return false;
        }

        this.product_id = parseInt(product_id);
        this.wrapper = wrapper;
        this.slug = 'product_meta';
        this.init();
    }

    async init() {
        [Functions, Helper, Table, Sidebar, Switcher, SelectM23, Calendar, CalendarSelector] = await loadModules();
        this.draw();
    }

    draw() {

        this.taxonomies_buffer = {};

        Helper.ajax('botoscope_product_get_meta', {
            product_id: this.product_id
        }, raw_rows_data => {
            const data = {
                attributes: {
                    'class': this.slug,
                    'data-data-table': this.slug,
                    'data-order': 'desc',
                    'data-order-by': 'menu_order',
                    'data-per-page': -1,
                    'data-records-count': raw_rows_data.length,
                    'id': `the_table_${this.slug}`
                },
                header: [
                    {
                        value: botoscope_lang.order,
                        width: '10%',
                        key: 'menu_order',
                    },
                    {
                        value: botoscope_lang.icon,
                        width: '10%',
                        key: 'icon',
                    },
                    {
                        value: botoscope_lang.title,
                        width: '50%',
                        key: 'title'
                    },
                    {
                        value: botoscope_lang.value,
                        width: '20%',
                        key: 'value',
                    },
                    {
                        value: 'X',
                        width: '10%',
                        key: 'delete'
                    }
                ],
                raw_rows_data,
                format_data: function (rd) {
                    const {id, is_active, ...rest} = rd;

                    return {
                        ...rest,
                        is_active: {
                            //el_type: "switcher",
                            value: is_active
                        },
                        extra: {
                            editable: ['value', 'icon'],
                            id
                        }
                    };
                }
            };

            //+++

            this.table = new Table(this.wrapper, data, this.slug, {
                cell_content_drawn: async data => {

                    const cell = data.cell;

                    switch (data.key) {
                        case 'menu_order':
                            {
                                const rows_ids = Object.values(cell.table.data.raw_rows_data).map(item => parseInt(item.id));
                                const row_index = parseInt(data.cell.row_index);

                                const container = Helper.create_element('div', {
                                    class: 'botoscope_row_menu_order'
                                }, '');

                                data.cell.set_node(container);

                                if (row_index > 0) {
                                    const btn_up = Helper.create_element('a', {
                                        href: '#',
                                        class: 'arrow-button-up'
                                    }, '', {
                                        name: 'click',
                                        callback: e => this.move(this.table, data, rows_ids, row_index, 'up')
                                    });

                                    container.appendChild(btn_up);
                                }

                                if (row_index < data.cell.table.data.rows.length - 1) {
                                    const btn_down = Helper.create_element('a', {
                                        href: '#',
                                        class: 'arrow-button-down'
                                    }, '️', {
                                        name: 'click',
                                        callback: e => this.move(this.table, data, rows_ids, row_index, 'down')
                                    });

                                    container.appendChild(btn_down);
                                }

                            }
                            break;
                        case 'value':

                            switch (cell.get_sibling_value('type')) {
                                case 'number':
                                    cell.editable_type = 'number';
                                    break;
                                case 'boolean':
                                    {
                                        cell.clear();
                                        cell.avoid_click_edit = true;
                                        const switcher = new Switcher(data.extra.id, parseInt(cell.get_sibling_value('value')), cell.container);
                                        switcher.setEvent('click', (e, input) => cell.set_value(input.checked ? 1 : 0));
                                    }
                                    break;
                                case 'taxonomy':
                                    {
                                        cell.clear();
                                        cell.avoid_click_edit = true;
                                        const cell_val = cell.value ? JSON.parse(cell.value.replace(/\\"/g, '"')) : null;

                                        if (cell_val && cell_val?.taxonomy) {
                                            if (!this.taxonomies_buffer?.[cell_val.taxonomy]) {
                                                this.taxonomies_buffer[cell_val.taxonomy] = await Functions.load_taxonomies_terms(cell_val.taxonomy);
                                            }

                                            const selected_terms = cell_val.terms;
                                            const select = await Functions.draw_terms_select(this.taxonomies_buffer[cell_val.taxonomy], [], selected_terms.map(id => parseInt(id)), () => {
                                                cell.set_value(JSON.stringify({
                                                    taxonomy: cell_val.taxonomy,
                                                    terms: sm23.selected_values.map(id => parseInt(id))
                                                }));
                                            });

                                            cell.set_node(select);
                                            const sm23 = new SelectM23(select, true, botoscope_lang.select_terms);

                                            select.addEventListener('selectm23-reorder', e => {
                                                //data.value = e.detail.values;

                                                cell.set_value(JSON.stringify({
                                                    taxonomy: cell_val.taxonomy,
                                                    terms: sm23.selected_values.map(id => parseInt(id))
                                                }));
                                            });
                                        }
                                    }

                                    break;

                                case 'calendar':
                                    {
                                        cell.clear();
                                        cell.avoid_click_edit = true;
                                        const calendar = new CalendarSelector(cell.container, 0, parseInt(cell.value), '', {
                                            show_time: true
                                        });

                                        calendar.selected = () => {
                                            cell.set_value(JSON.stringify(calendar.unix_time_stamp));
                                        };
                                    }
                                    break;
                            }

                            break;

                        case 'delete':
                            {
                                let btn = Helper.create_element('a', {
                                    href: '#',
                                    class: 'button button-primary'
                                }, 'X', {
                                    name: 'click',
                                    callback: async e => {
                                        if (confirm(botoscope_lang.are_you_sure)) {
                                            Functions.message(botoscope_lang.loading, 'warning', -1);
                                            await Helper.ajax('botoscope_delete_row', {
                                                what: this.slug,
                                                row_id: data.extra.id,
                                                parent_row_id: this.product_id,
                                                meta_id: cell.get_sibling_value('meta_id')
                                            }, null, false);

                                            this.table.remove_row(data.extra.id);
                                            Functions.message(botoscope_lang.saved);
                                        }
                                    }
                                });

                                cell.set_node(btn);
                            }
                            break;
                    }
                }
            });

            new MetaPack(this.product_id, this.wrapper, this.table);

            this.table.data_is_mutated = (operation, data) => {
                switch (operation) {
                    case 'edit_cell':
                        Functions.edit_table_cell(data, this.slug);
                        break;
                    case 'move_col_right':
                    case 'move_col_left':
                        this.table.set_table_col_positions(data.positions, data.key, data.index);
                        break;
                    case 'order_col_data':
                        this.table.set_page(0);
                        break;
                }
            };


            this.table.set_page = async (page_num) => await Functions.reload_table_data(this.table, page_num, this.slug, {product_id: this.product_id});

            //+++

            const container = Helper.create_element('div', {
                class: 'botoscope-meta-pack-container'
            }, '');

            const append_btn = Helper.create_element('a', {
                href: '#',
                class: 'botoscope-button botoscope-button-small botoscope-create-meta-btn'
            }, '<span class="icon-plus-circle"></span>' + botoscope_lang.append_meta_field, {
                name: 'click',
                callback: async e => {
                    this.open_gallery_sidebar();
                }
            });

            const pos_select = Helper.create_html_select({0: botoscope_lang.meta_position_media, 1: botoscope_lang.meta_position_description}, parseInt(this.wrapper.dataset.meta_position), {
                name: 'meta_position',
                class: 'form-select',
                style: 'width: fit-content',
            }, {
                name: 'change',
                callback: e => {
                    /*
                     Helper.ajax('botoscope_edit_row', {
                     what: 'products',
                     id: this.product_id,
                     data: {meta_position: e.target.value}
                     });
                     * 
                     */
                }
            });

            container.appendChild(append_btn);
            container.appendChild(pos_select);
            this.wrapper.prepend(container);
        });
    }

    async open_gallery_sidebar() {
        let sidebar = new Sidebar(this.slug, this.product_id, botoscope_lang.products_meta_gallery, botoscope_is_mobile ? '100%' : '97%');
        sidebar.set_content('products-meta-gallery', {}, function (data_table, row_id, formProps) {
            return false;
        });

        sidebar.after_set = async () => {
            const slug = 'products_meta_gallery';
            const wrapper = document.getElementById('botoscope-products-meta-gallery-wrapper');

            const data = {
                attributes: {
                    'class': slug,
                    'data-data-table': slug,
                    'data-order': 'desc',
                    'data-order-by': 'id',
                    'data-per-page': -1,
                    'data-records-count': 9999,
                    'id': `the_table_${slug}`
                },
                header: format_custom_header(),
                raw_rows_data: JSON.parse(wrapper.textContent),
                format_data: function (rd) {
                    const {id, is_active, ...rest} = rd;

                    return {
                        ...rest,
                        is_active: {
                            //el_type: "switcher",
                            value: is_active
                        },
                        extra: {
                            editable: ['title', 'default_value', 'icon', 'unit_of_measurement'],
                            id: id
                        }
                    };
                }
            };

            //+++

            wrapper.innerHTML = '';
            const exclude = wrapper.dataset.metaExclude.split(',').map(id => parseInt(id));

            const table = new Table(wrapper, data, slug, {
                cell_content_drawn: async data => {

                    const cell = data.cell;

                    switch (data.key) {
                        case 'oid':
                            {
                                cell.draw_content(`<b>${data.extra.id}</b>`);
                            }
                            break;

                        case 'title':
                        case 'unit_of_measurement':
                            {
                                setTimeout(() => Functions.draw_translatable_cell(table, slug, data, languages), 999);
                            }
                            break;

                        case 'default_value':
                            {
                                switch (cell.get_sibling_value('type')) {
                                    case 'boolean':
                                        {
                                            cell.avoid_click_edit = true;
                                            cell.clear();
                                            const switcher = new Switcher(data.extra.id, parseInt(cell.get_sibling_value('default_value')), cell.container);
                                            switcher.setEvent('click', (e, input) => cell.set_value(input.checked ? 1 : 0));
                                        }
                                        break;

                                    case 'taxonomy':
                                    {
                                        cell.avoid_click_edit = true;
                                        cell.clear();

                                        const sourceSelect = document.getElementById('botoscope-taxonomies-selector1');
                                        const select = document.createElement('select');

                                        const emptyOption = document.createElement('option');
                                        emptyOption.value = '0';
                                        emptyOption.textContent = '';
                                        select.appendChild(emptyOption);

                                        [...sourceSelect.options].forEach(option => {
                                            if (!option.value.startsWith('pa_') && option.value !== 'product_cat') {
                                                const newOption = document.createElement('option');
                                                newOption.value = option.value;
                                                newOption.textContent = option.textContent;
                                                select.appendChild(newOption);
                                            }
                                        });

                                        select.addEventListener('change', e => {
                                            const value = {
                                                taxonomy: e.target.value,
                                                terms: []
                                            };

                                            cell.set_value(JSON.stringify(value))
                                        });

                                        const cell_val = cell.value ? JSON.parse(cell.value.replace(/\\"/g, '"')) : null;
                                        select.value = cell_val?.taxonomy || '0';
                                        cell.set_node(select);
                                    }
                                }
                            }
                            break;


                        case 'calendar':
                            {
                                cell.avoid_click_edit = true;
                                cell.draw_content(0);
                            }
                            break;

                        case 'type':
                            {
                                cell.avoid_click_edit = true;//!!
                                const types = wrapper.dataset.metaTypes.split(',');

                                cell.set_node(Helper.create_html_select(types, data.value, {}, {
                                    name: 'change',
                                    callback: e => {
                                        cell.set_value(e.target.value);
                                        table.redraw();
                                    }
                                }));
                            }
                            break;

                        case 'append':
                            {
                                const meta_id = parseInt(data.extra.id);

                                if (exclude.includes(meta_id)) {
                                    cell.draw_content(`<b>${botoscope_lang.added}</b>`);
                                } else {
                                    cell.set_node(Helper.create_element('a', {
                                        href: '#',
                                        class: 'button button-primary'
                                    }, '<span class="icon-play"></span>', {
                                        name: 'click',
                                        callback: async e => {

                                            this.table.records_count += 1;
                                            //table.remove_row(meta_id);
                                            cell.draw_content(`<b>${botoscope_lang.added}</b>`);

                                            Functions.message(botoscope_lang.loading, 'warning', -1);
                                            Helper.ajax('botoscope_product_append_meta', {
                                                product_id: this.product_id,
                                                meta_id
                                            }, res => {
                                                Functions.message(botoscope_lang.saved);
                                                this.table.set_page(0);
                                            });
                                        }
                                    }));
                                }
                            }

                            break;

                        case 'delete':
                            {
                                let btn = Helper.create_element('a', {
                                    href: '#',
                                    class: 'button button-primary'
                                }, 'X', {
                                    name: 'click',
                                    callback: async e => {
                                        const meta_id = data.extra.id;
                                        if (confirm(botoscope_lang.meta_delete)) {
                                            Functions.message(botoscope_lang.loading, 'warning', -1);
                                            await Helper.ajax('botoscope_delete_meta', {
                                                meta_id
                                            }, res => {
                                                Functions.message(botoscope_lang.saved);
                                                this.table.set_page(0);
                                            }, false);

                                            table.remove_row(meta_id);
                                        }
                                    }
                                });

                                cell.set_node(btn);
                            }
                            break;
                    }
                }
            });


            table.data_is_mutated = (operation, data) => {
                switch (operation) {
                    case 'edit_cell':
                        Functions.edit_table_cell(data, slug);
                        break;
                    case 'move_col_right':
                    case 'move_col_left':
                        table.set_table_col_positions(data.positions, data.key, data.index);
                        break;
                    case 'order_col_data':
                        table.set_page(0);
                        break;
                }
            };

            table.set_page = async (page_num) => await Functions.reload_table_data(table, page_num, slug, {product_id: this.product_id});

            //+++
            //button to create new meta field
            const btn_create_meta = Helper.create_element('a', {
                href: '#',
                class: 'botoscope-button botoscope-button-small botoscope-create-meta-btn'
            }, '<span class="icon-hammer"></span>' + botoscope_lang.create_meta_field, {
                name: 'click',
                callback: async e => this.create_meta_field(table)
            });

            //+++

            const search_container = Helper.create_element('div', {
                class: 'botoscope-meta-search-container'
            }, '');

            const search_input = Helper.create_element('input', {
                type: 'search',
                class: 'botoscope-meta-search-input',
                placeholder: ''
            }, '', {
                name: 'input',
                callback: e => {
                    if (e.target.value.length > 0) {
                        table.search = e.target.value;

                        table.rows.forEach(row => {
                            row.display(0);
                            if (row.data.title.toLowerCase().includes(table.search.toLowerCase())) {
                                row.display(1);
                            }
                        });

                    } else {
                        table.rows.forEach(row => {
                            row.display(1);
                        });
                    }
                }
            });

            search_container.prepend(search_input);
            search_container.prepend(btn_create_meta);

            wrapper.prepend(search_container);

            //+++

            document.addEventListener('botoscope-selected-language', (e) => {
                if (e.detail.data.language !== languages.default_language) {
                    btn_create_meta.style.setProperty('visibility', 'hidden');
                    btn_create_meta.style.setProperty('width', '0', 'important');
                    btn_create_meta.style.setProperty('padding', '0');
                } else {
                    btn_create_meta.style.setProperty('visibility', 'visible');
                    btn_create_meta.style.removeProperty('width');
                    btn_create_meta.style.removeProperty('padding');
                }
            });

            table.format_custom_header = format_custom_header;
            languages.selected_language = languages.default_language = botoscope_default_language;
            const language_selector = Functions.init_app_language_functionality(table, slug, languages);
            if (languages.selected_language !== languages.default_language) {
                language_selector.dispatchEvent(new Event('change'));
            }
        };
    }

    create_meta_field(table) {
        Functions.message(botoscope_lang.loading, 'warning', -1);
        Helper.ajax('botoscope_product_create_meta', {}, res => {
            Functions.message(botoscope_lang.saved);
            table.set_page(0);
        });
    }

    move(table, data, rows_ids, row_index, direction) {

        let tr = data.cell.table.data.rows;//short link to object
        let new_row_index;

        if (direction === 'up') {
            new_row_index = row_index - 1;
        } else {
            //down
            new_row_index = row_index + 1;
        }

        let top_row = {...tr[row_index]};
        let bottom_row = {...tr[new_row_index]};
        tr[new_row_index] = top_row;
        tr[row_index] = bottom_row;

        rows_ids = [];
        data.cell.table.data.rows.forEach((value, index) => {
            rows_ids.push(parseInt(value.extra.id));
        });

        table.redraw();

        //+++

        Helper.ajax('botoscope_edit_cell', {
            what: 'product_meta_menu_order',
            value: rows_ids.join(','),
            id: this.product_id,
            key: 'menu_order'
        }, null, false);
    }
}


function format_custom_header(type = 1) {

    let header = [];

    switch (type) {
        case 1:
            header = [
                {
                    value: 'ID',
                    width: '5%',
                    key: 'oid'
                },
                {
                    value: botoscope_lang.title,
                    width: '35%',
                    key: 'title'
                },
                {
                    value: botoscope_lang.type,
                    width: '10%',
                    key: 'type'
                },
                {
                    value: botoscope_lang.default,
                    width: '10%',
                    key: 'default_value'
                },
                {
                    value: botoscope_lang.unit_of_measurement,
                    width: '10%',
                    key: 'unit_of_measurement'
                },
                {
                    value: botoscope_lang.icon,
                    width: '10%',
                    key: 'icon'
                },
                {
                    value: botoscope_lang.append,
                    width: '10%',
                    key: 'append'
                },
                {
                    value: botoscope_lang.delete,
                    width: '10%',
                    key: 'delete'
                }
            ];

            break;

        default:

            header = [
                {
                    value: botoscope_lang.title,
                    width: '75%',
                    key: 'title'
                },

                {
                    value: botoscope_lang.unit_of_measurement,
                    width: '25%',
                    key: 'unit_of_measurement'
                }
            ];

            break;
    }

    return header;
}

async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js'),
        import(botoscope_url + 'assets/js/lib/sidebar.js'),
        import(botoscope_url + 'assets/js/table/ae/switcher.js'),
        import(botoscope_url + 'assets/js/lib/selectm-23.js'),
        import(botoscope_url + 'assets/js/lib/calendar23.js'),
        import(botoscope_url + 'assets/js/lib/calendar23-selector.js')
    ]);

    return modules.map(mod => mod.default || mod);
}
