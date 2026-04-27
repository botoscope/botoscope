let Functions, Helper, Table;
//09-04-2026
export default class Grouped {
    constructor(parent_table, parent_product_id, parent_sidebar, parent_call_btn) {
        this.parent_table = parent_table;
        this.parent_product_id = parent_product_id;
        this.parent_call_btn = parent_call_btn;
        this.parent_sidebar = parent_sidebar;

        this.init();
    }

    async init() {
        [Functions, Helper, Table] = await loadModules();
        this.draw();
    }

    async draw(raw_rows_data = null) {
        const [Functions, Helper, Table] = await loadModules();
        const wrapper = this.parent_sidebar.content_container.querySelector('#botoscope_product_products_container');
        const table_slug = 'product_products';

        if (!raw_rows_data) {
            raw_rows_data = JSON.parse(wrapper.textContent);
        }
        wrapper.innerHTML = '';
        wrapper.style.display = 'block';

        let child_ids = Object.values(raw_rows_data).map(item => parseInt(item.id));

        const data = {
            attributes: {
                class: table_slug,
                'data-data-table': table_slug,
                'data-order': 'desc',
                'data-order-by': 'id',
                'data-per-page': -1,
                'data-records-count': raw_rows_data.length,
                id: `the_table_${table_slug}`
            },
            header: format_custom_header(),
            raw_rows_data,
            format_data: function (rd) {
                const {id, is_active, ...rest} = rd;

                return {
                    ...rest,
                    is_active: {
                        el_type: "switcher",
                        value: is_active
                    },
                    extra: {
                        editable: [],
                        id: id
                    }
                };
            }
        };

        const table = new Table(wrapper, data, table_slug, {
            cell_content_drawn: async data => {

                const cell = data.cell;

                switch (data.key) {
                    case 'menu_order':
                        {
                            let row_index = parseInt(data.cell.row_index);

                            let container = Helper.create_element('div', {
                                class: 'botoscope_row_menu_order'
                            }, '');

                            data.cell.set_node(container);

                            if (row_index > 0) {
                                let btn_up = Helper.create_element('a', {
                                    href: '#',
                                    class: 'arrow-button-up'
                                }, '', {
                                    name: 'click',
                                    callback: e => this.move(table, data, child_ids, row_index, 'up')
                                });

                                container.appendChild(btn_up);
                            }

                            if (row_index < data.cell.table.data.rows.length - 1) {
                                let btn_down = Helper.create_element('a', {
                                    href: '#',
                                    class: 'arrow-button-down'
                                }, '️', {
                                    name: 'click',
                                    callback: e => this.move(table, data, child_ids, row_index, 'down')
                                });

                                container.appendChild(btn_down);
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
                                callback: e => call_delete_cell.apply(this, [data.extra.id])
                            });

                            cell.set_node(btn);

                            function call_delete_cell(child_id) {
                                if (confirm(botoscope_lang.are_you_sure)) {
                                    table.remove_row(child_id);

                                    Helper.ajax('botoscope_delete_product_child', {
                                        product_id: this.parent_product_id,
                                        child_id
                                    }, res => {
                                        table.redraw();
                                        table.records_count -= 1;
                                        this.parent_call_btn.innerText = this.parent_call_btn.innerText.replace(/\(\d+\)/, `(${table.records_count})`);
                                        /*
                                         * * its not need here as synhronozation is doing trought hook woocommerce_update_product
                                         Helper.ajax('botoscope_update_product_bot_cache', {
                                         product_id: this.parent_product_id
                                         }, null, false);
                                         * 
                                         */
                                    });
                                }
                            }
                        }
                        break;
                }
            }
        });

        //lets append search input
        let wrapper2 = Functions.create_element('div', {
            class: 'products-wrapper',
            style: 'display: none'
        });

        wrapper2.innerHTML = '';
        wrapper.insertAdjacentElement('afterend', wrapper2);

        let search_ajax_request = null;

        let input = Functions.create_element('input', {
            type: 'text',
            class: 'form-control search-input',
            placeholder: botoscope_lang.enter3_to_start_search,
        }, '', {
            name: 'keyup',
            callback: e => {
                e.preventDefault();
                let value = input.value;

                if (search_ajax_request) {
                    try {
                        search_ajax_request.abort('New request started');
                    } catch (e) {
                        console.log('Request aborted');
                    }
                }

                search_ajax_request = new AbortController();

                if (value.length >= 3) {

                    Helper.ajax('botoscope_search_products', {
                        botoscope_form_nonce: document.getElementById('botoscope_form_nonce').value,
                        what: 'product_products',
                        parent_row_id: this.parent_product_id,
                        value
                    }, server_data => {

                        wrapper.style.display = 'none';
                        wrapper2.style.removeProperty('display');
                        cross.style.removeProperty('display');
                        wrapper2.innerHTML = '';

                        //+++

                        let slug = 'product_products_suggestion';

                        let suggested_products_data = {
                            attributes: {
                                class: slug,
                                'data-data-table': slug,
                                'data-order': 'desc',
                                'data-order-by': 'id',
                                'data-per-page': -1,
                                'data-records-count': -1,
                                id: `the_table_${slug}`
                            },
                            header: [
                                {
                                    value: botoscope_lang.title,
                                    width: '90%',
                                    key: 'title'
                                },
                                {
                                    value: botoscope_lang.add,
                                    width: '10%',
                                    key: 'add'
                                }
                            ],
                            raw_rows_data: server_data,
                            format_data: function (rd) {
                                const {id, ...rest} = rd;

                                return {
                                    ...rest,
                                    extra: {
                                        editable: [],
                                        id: id
                                    }
                                };
                            }
                        };

                        //+++

                        let table2 = new Table(wrapper2, suggested_products_data, slug, {
                            cell_content_drawn: data => {

                                switch (data.key) {

                                    case 'add':
                                        {
                                            let btn = Helper.create_element('input', {
                                                type: 'checkbox',
                                                class: 'input-checkbox'
                                            }, 'X', {
                                                name: 'change',
                                                callback: e => {
                                                    check.apply(this, [btn, data.extra.id]);
                                                }
                                            });

                                            data.cell.set_node(btn);

                                            function check(btn, row_id) {

                                                let add = 1;

                                                if (!btn.checked) {
                                                    add = 0;
                                                }

                                                if (add) {
                                                    child_ids.push(row_id);
                                                } else {
                                                    child_ids = child_ids.filter(value => value !== parseInt(row_id));
                                                }

                                                Helper.ajax('botoscope_edit_row', {
                                                    what: 'products',
                                                    id: this.parent_product_id,
                                                    data: {type: 'grouped'}//fix to avoid fatal error
                                                }, res => {
                                                    Helper.ajax('botoscope_edit_cell', {
                                                        what: 'product_products',
                                                        value: child_ids.join(','),
                                                        id: this.parent_product_id,
                                                        key: 'child_ids'
                                                    }, res => {
                                                        this.parent_call_btn.innerText = this.parent_call_btn.innerText.replace(/\(\d+\)/, `(${child_ids.length})`);
                                                        table.redraw(res, true);
                                                        /*
                                                         * * its not need here as synhronozation is doing trought hook woocommerce_update_product
                                                         Helper.ajax('botoscope_update_product_bot_cache', {
                                                         product_id: this.parent_product_id
                                                         }, null, false);
                                                         * 
                                                         */
                                                    });
                                                }, true);

                                                return true;
                                            }
                                        }
                                        break;
                                }
                            }
                        });

                    }, true, null, search_ajax_request.signal);

                } else {
                    wrapper.style.removeProperty('display');
                    wrapper2.style.display = 'none';
                    cross.style.display = 'none';
                }

                return false;
            }
        });

        //+++

        const cross = Functions.create_element('a', {
            href: '#',
            class: 'botoscope_search_cross',
        }, 'x', {
            name: 'click',
            callback: e => {
                input.value = '';
                input.dispatchEvent(new Event('keyup'));
            }
        });

        cross.style.display = 'none';
        wrapper.insertAdjacentElement('beforebegin', cross);
        wrapper.insertAdjacentElement('beforebegin', input);

        //+++

        function format_custom_header(type = 1) {

            let header = [];

            switch (type) {
                case 1:

                    header = [
                        {
                            value: botoscope_lang.order,
                            width: '10%',
                            key: 'menu_order'
                        },
                        {
                            value: 'ID',
                            width: '10%',
                            key: 'oid'
                        },
                        {
                            value: botoscope_lang.title,
                            width: '70%',
                            key: 'title'
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
                            width: '100%',
                            key: 'title'
                        }
                    ];

                    break;
            }

            return header;
    }
    }

    move(table, data, child_ids, row_index, direction) {

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

        child_ids = [];
        data.cell.table.data.rows.forEach((value, index) => {
            child_ids.push(parseInt(value.extra.id));
        });

        table.redraw();

        //+++

        Helper.ajax('botoscope_edit_cell', {
            what: 'product_products',
            value: child_ids.join(','),
            id: this.parent_product_id,
            key: 'child_ids'
        });

    }

}

async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js'),
        import(botoscope_url + 'assets/js/lib/sidebar.js')
    ]);

    return modules.map(mod => mod.default || mod);
}
