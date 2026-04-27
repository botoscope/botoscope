//24-03-2025
export default async function init_coupons() {

    if (botoscope_is_no_cart) {
        return false;
    }

    const [Functions, Helper, Table, Calendar, CalendarSelector, Switcher] = await loadModules();
    let slug = 'coupons';
    let wrapper = document.getElementById(`botoscope-${slug}-w`);

    let table_data = {
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
                value: botoscope_lang.code,
                width: '15%',
                key: 'code'
            },
            {
                value: botoscope_lang.type,
                width: '9%',
                key: 'discount_type'
            },
            {
                value: botoscope_lang.amount,
                width: '9%',
                key: 'amount'
            },

            {
                value: botoscope_lang.usage_limit,
                width: '9%',
                key: 'usage_limit'
            },
            {
                value: botoscope_lang.minimum,
                width: '9%',
                key: 'minimum_amount'
            },
            {
                value: botoscope_lang.maximum,
                width: '9%',
                key: 'maximum_amount'
            },
            {
                value: botoscope_lang.expiry,
                width: '10%',
                key: 'date_expires'
            },
            {
                value: botoscope_lang.product,
                width: '10%',
                key: 'product_ids'
            },
            {
                value: botoscope_lang.active,
                width: '10%',
                key: 'is_active'
            },
            {
                value: botoscope_lang.delete,
                width: '10%',
                key: 'delete'
            }
        ],
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
                    editable: ['code', 'amount', 'usage_limit', 'minimum_amount', 'maximum_amount'],
                    id: id
                }
            };
        }
    };


    //+++

    wrapper.innerHTML = '';
    let table = new Table(wrapper, table_data, slug, {

        cell_content_drawn: data => {

            const cell = data.cell;

            switch (data.key) {

                case 'is_active':
                    {
                        let switcher = new Switcher(this.id, parseInt(cell.table.data.rows[cell.row_index].is_active?.value), cell.container);

                        switcher.setEvent('click', (e, input) => {
                            cell.value = input.checked ? 1 : 0;
                            const sw_data = {...data, key: 'is_active', value: cell.value};
                            Functions.edit_table_cell(sw_data, slug, {}, table, true);
                        });

                    }
                    break;

                case 'date_expires':
                    {
                        let container = Helper.create_element('div', {
                            class: 'calendar23-selector',
                            'data-name': 'expiry_date',
                            'data-date': parseInt(data.value)
                        }, '');

                        cell.set_node(container);

                        const additional = {
                            show_time: true
                        };

                        additional.month_names = botoscope_lang.month_names;
                        additional.month_names_short = botoscope_lang.month_names_short;
                        additional.day_names = botoscope_lang.day_names;

                        const calendar = new CalendarSelector(container, 0, parseInt(data.value), '', additional);

                        calendar.selected = () => {
                            data.value = calendar.unix_time_stamp;
                            Functions.edit_table_cell(data, slug);
                        };
                    }
                    break;

                case 'discount_type':
                    {
                        let select_data = {
                            percent: botoscope_lang.percent_to_cart,
                            fixed_cart: botoscope_lang.fixed_to_cart,
                            botoscope_percent_product: botoscope_lang.percent_to_selected_product,
                            fixed_product: botoscope_lang.fixed_to_selected_product
                        };

                        let select = Helper.create_html_select(select_data, data.value, {
                            class: ''
                        }, {
                            name: 'change',
                            callback:
                                    e => {
                                        data.value = e.target.value;
                                        Functions.edit_table_cell(data, slug);

                                        let row = table.rows[cell.row_index];
                                        let pid_cell = row.get_cell_by_key('product_ids');
                                        table_data.rows[cell.row_index].discount_type = data.value;
                                        pid_cell.draw_elements();
                                    }
                        });

                        cell.set_node(select);
                    }

                    break;

                case 'product_ids':
                    {

                        if (['percent', 'fixed_cart'].includes(table_data.rows[cell.row_index].discount_type)) {
                            cell.clear();
                            return;
                        }

                        let products_ids = [];
                        let products_count = 0;

                        if (data?.value) {
                            products_ids = data.value.split(',');
                            products_count = products_ids.length;
                        }

                        let btn = Helper.create_element('a', {
                            href: '#',
                            class: 'button button-primary'
                        }, `${botoscope_lang.manage} (${products_count})`, {
                            name: 'click',
                            callback:
                                    e => call_popup(btn, data.extra.id)
                        });

                        cell.set_node(btn);

                        //+++

                        function call_popup(parent_btn, parent_row_id) {

                            let popup = Functions.create_popup({
                                title: `${botoscope_lang.products_for}: ${table.data.rows[cell.row_index].code}`,
                                title_logo: botoscope_url + 'assets/img/dolphin.svg',
                                title_top_info: botoscope_lang.botoscope,
                                left_button_word: '',
                                close_word: botoscope_lang.close,
                                footer_buttons_change_sides: 0,
                                width: botoscope_is_mobile ? '100%' : '75%',
                                height: botoscope_is_mobile ? '90%' : '75%',
                                top: '12%',
                                bottom: '12%',
                                left: botoscope_is_mobile ? 0 : '12%',
                                right: '12%',
                                mousemove: true
                            });


                            let wrapper = Functions.create_element('div', {
                                class: 'products-wrapper'
                            });

                            let wrapper2 = Functions.create_element('div', {
                                class: 'products-wrapper',
                                style: 'display: none'
                            });

                            wrapper2.innerHTML = '';

                            popup.clear_content();

                            //+++

                            let search_ajax_request = null;

                            let input = Functions.create_element('input', {
                                type: 'text',
                                class: 'form-control search-input',
                                placeholder: botoscope_lang.enter3_to_start_search,
                            }, '', {
                                name: 'keyup',
                                callback: e => {
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
                                            what: 'coupons',
                                            parent_row_id,
                                            value
                                        }, server_data => {
                                            wrapper.style.display = 'none';
                                            wrapper2.style.removeProperty('display');
                                            cross.style.removeProperty('display');
                                            wrapper2.innerHTML = '';

                                            //+++

                                            let slug = 'coupons_products_suggestion';

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
                                                        value: 'ID',
                                                        width: '10%',
                                                        key: 'id'
                                                    },
                                                    {
                                                        value: botoscope_lang.title,
                                                        width: '80%',
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


                                            let table = new Table(wrapper2, suggested_products_data, slug, {
                                                cell_content_drawn: data => {

                                                    switch (data.key) {

                                                        case 'id':

                                                            data.cell.draw_content(data.extra.id);

                                                            break;

                                                        case 'add':
                                                            {
                                                                let btn = Helper.create_element('input', {
                                                                    type: 'checkbox',
                                                                    class: 'input-checkbox'
                                                                }, 'X', {
                                                                    name: 'change',
                                                                    callback: e => check(btn, data.extra.id)
                                                                });

                                                                data.cell.set_node(btn);

                                                                function check(btn, row_id) {

                                                                    let add = 1;

                                                                    if (!btn.checked) {
                                                                        add = 0;
                                                                    }

                                                                    Helper.ajax('botoscope_edit_cell', {
                                                                        what: 'coupons_update_products',
                                                                        product_id: row_id,
                                                                        coupon_id: parent_row_id,
                                                                        key: 'products',
                                                                        add
                                                                    }, () => {
                                                                        fill_coupon_products(wrapper, parent_row_id, parent_btn);
                                                                    }, false);

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
                                }
                            });

                            //+++

                            let cross = Functions.create_element('a', {
                                href: '#',
                                class: 'botoscope_search_cross',
                                style: "top: 16px;"
                            }, 'x', {
                                name: 'click',
                                callback: e => {
                                    input.value = '';
                                    input.dispatchEvent(new Event('keyup'));
                                }
                            });

                            cross.style.display = 'none';

                            //+++

                            popup.append_content(input);
                            popup.append_content(cross);
                            popup.append_content(wrapper);
                            popup.append_content(wrapper2);

                            fill_coupon_products(wrapper, parent_row_id, parent_btn);
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
                            callback: e => delete_cell(btn, data.extra.id)
                        });

                        data.cell.set_node(btn);

                        function delete_cell(btn, row_id) {
                            if (confirm(botoscope_lang.are_you_sure)) {
                                table.remove_row(row_id);

                                Helper.ajax('botoscope_delete_row', {
                                    what: slug,
                                    row_id
                                }, () => {
                                    table.redraw();
                                    redraw_local_cache();
                                }, false);

                            }
                        }
                    }
                    break;
            }
        }
    });

    //+++

    function fill_coupon_products(wrapper, parent_row_id, parent_btn) {
        wrapper.innerHTML = '';

        Helper.ajax('botoscope_get_parent_cell_data', {
            parent_app: 'coupons',
            parent_cell_name: 'products',
            parent_row_id
        }, function (server_data) {

            let raw_rows_data = [];

            if (server_data) {
                raw_rows_data = server_data;
            }

            parent_btn.innerText = `${botoscope_lang.manage} (${raw_rows_data.length})`;

            let slug = 'coupons_products';

            let products_data = {
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
                        value: 'ID',
                        width: '10%',
                        key: 'id'
                    },
                    {
                        value: botoscope_lang.product,
                        width: '80%',
                        key: 'title'
                    },
                    {
                        value: botoscope_lang.delete,
                        width: '10%',
                        key: 'delete'
                    }
                ],
                raw_rows_data,
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

            let additional_params = {
                parent_row_id: parent_row_id,
                parent_table: 'coupons',
                parent_cell: 'products'
            };

            let products_table = new Table(wrapper, products_data, slug, {
                cell_content_drawn: data => {

                    switch (data.key) {

                        case 'id':

                            data.cell.draw_content(data.extra.id);

                            break;

                        case 'delete':
                            {
                                let del_btn = Helper.create_element('a', {
                                    href: '#',
                                    class: 'button button-primary'
                                }, 'X', {
                                    name: 'click',
                                    callback: e => call_delete_cell(del_btn, data.extra.id)
                                });

                                data.cell.set_node(del_btn);

                                function call_delete_cell(del_btn, row_id) {
                                    if (confirm(botoscope_lang.are_you_sure)) {
                                        products_table.remove_row(row_id);

                                        Helper.ajax('botoscope_delete_row', {
                                            what: slug,
                                            row_id,
                                            additional_params
                                        }, () => {
                                            products_table.redraw();
                                            parent_btn.innerText = parent_btn.innerText.replace(/\(\d+\)/, `(${products_table.data.rows.filter(row => row.extra.id > 0).length})`);
                                        }, false);

                                    }
                                }
                            }
                            break;
                    }
                }
            });

            //+++

            products_table.data_is_mutated = (operation, data) => {
                switch (operation) {
                    case 'edit_cell':
                        Functions.edit_table_cell(data, slug, additional_params);
                        break;
                    case 'move_col_right':
                    case 'move_col_left':
                        products_table.set_table_col_positions(data.positions, data.key, data.index);
                        break;
                }
            };

        }, true);
    }


    //+++

    function redraw_local_cache() {
        //document.getElementById('marketing_strategies_local_cache').innerHTML = JSON.stringify(table.data.rows);
    }

    //+++

    table.data_is_mutated = (operation, data) => {
        switch (operation) {
            case 'edit_cell':
                Functions.edit_table_cell(data, slug);
                redraw_local_cache();
                break;
            case 'move_col_right':
            case 'move_col_left':
                table.set_table_col_positions(data.positions, data.key, data.index);
                break;
        }
    };

    //+++

    document.getElementById(`botoscope_create_${slug}`).addEventListener('click', e => {
        Functions.add_table_row(table, slug);
        redraw_local_cache();

        return false;
    });

    return table;
}

async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js'),
        import(botoscope_url + 'assets/js/lib/calendar23.js'),
        import(botoscope_url + 'assets/js/lib/calendar23-selector.js'),
        import(botoscope_url + 'assets/js/table/ae/switcher.js'),
    ]);

    return modules.map(mod => mod.default || mod);
}
