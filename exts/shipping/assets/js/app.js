const languages = {selected_language: null, default_language: null};
//24-03-2025
export default async function init_shipping() {
    if (botoscope_is_no_cart || botoscope_no_bot) {
        return false;
    }

    const [Functions, Helper, Table, Switcher] = await loadModules();
    let slug = 'shipping';
    let wrapper = document.getElementById(`botoscope-${slug}-w`);

    let data = {
        attributes: {
            class: slug,
            'data-data-table': slug,
            'data-order': 'desc',
            'data-order-by': 'id',
            'data-per-page': -1,
            'data-records-count': -1,
            id: `the_table_${slug}`
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
                    editable: ['title', 'price', 'min_amount', 'description'],
                    id: id
                }
            };
        }
    };

    //+++

    wrapper.innerHTML = '';
    let table = new Table(wrapper, data, slug, {
        cell_content_drawn: data => {
            
            const cell = data.cell;

            switch (data.key) {

                case 'oid':
                    if (data.extra.id > 0) {
                        cell.draw_content(data.extra.id);
                    }
                    break;

                case 'title':
                case 'description':
                    {
                        setTimeout(() => Functions.draw_translatable_cell(table, slug, data, languages), 999);
                    }
                    break;

                case 'is_default':
                    {
                        let att = {
                            type: 'radio',
                            name: `${slug}[]`,
                            value: parseInt(data.value)
                        };

                        if (parseInt(data.value) === 1) {
                            att.checked = 1;
                        }

                        let radio = Helper.create_element('input', att, '', {
                            name: 'change',
                            callback:
                                    e => {
                                        e.target.checked = 1;
                                        data.value = 1;
                                        data.key = 'is_default';

                                        table.data.raw_rows_data.forEach((r, i) => {
                                            table.data.raw_rows_data[i].is_default = 0;
                                        });

                                        Functions.edit_table_cell(data, slug, {}, table, true);

                                        return true;
                                    }
                        });

                        cell.set_node(radio);
                    }
                    break;

                case 'menu_order':
                    {
                        let row_index = parseInt(cell.row_index);

                        let container = Helper.create_element('div', {
                            class: 'botoscope_product_attribute_menu_order',
                        }, '');

                        cell.set_node(container);

                        if (row_index > 0) {
                            let btn_up = Helper.create_element('a', {
                                href: '#',
                                class: 'arrow-button-up'
                            }, '', {
                                name: 'click',
                                callback: e => move(row_index, 'up')
                            });

                            container.appendChild(btn_up);
                        }

                        if (row_index < cell.table.data.rows.length - 1) {
                            let btn_down = Helper.create_element('a', {
                                href: '#',
                                class: 'arrow-button-down'
                            }, '️', {
                                name: 'click',
                                callback: e => move(row_index, 'down')
                            });

                            container.appendChild(btn_down);
                        }

                        function move(row_index, direction) {

                            let tr = cell.table.data.rows;//short link to object
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

                            let new_values = [];
                            cell.table.data.rows.forEach((value, index) => {
                                new_values.push({
                                    value: index,
                                    id: parseInt(value.extra.id)
                                });
                            });

                            table.redraw();

                            Helper.ajax('botoscope_edit_cell', {
                                what: slug,
                                key: 'menu_order',
                                id: 0,
                                value: 1,
                                slug,
                                new_values
                            }, () => {
                                Functions.reload_table_data(table, 0, slug);
                            }, false);

                        }
                    }
                    break;

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

                case 'delete':
                    {
                        let btn = Helper.create_element('a', {
                            href: '#',
                            class: 'button button-primary'
                        }, 'X', {
                            name: 'click',
                            callback: e => call_delete_cell(btn, data.extra.id)
                        });

                        cell.set_node(btn);

                        function call_delete_cell(btn, row_id) {
                            if (confirm(botoscope_lang.are_you_sure)) {
                                table.remove_row(row_id);

                                Helper.ajax('botoscope_delete_row', {
                                    what: slug,
                                    row_id
                                }, function () {
                                    table.redraw();
                                    Helper.cast('botoscope-shiping-ways-updated', {
                                        data: {}
                                    });
                                }, false);

                            }
                        }
                    }
                    break;
            }
        }
    });

    //+++    

    table.data_is_mutated = (operation, data) => {
        switch (operation) {
            case 'edit_cell':
                Functions.edit_table_cell(data, slug);

                setTimeout(() => {
                    if (data.key === 'title') {
                        Helper.cast('botoscope-shiping-ways-updated', {
                            data: {}
                        });
                    }
                }, 1999);

                break;
            case 'move_col_right':
            case 'move_col_left':
                table.set_table_col_positions(data.positions, data.key, data.index);
                break;
        }
    };

    //+++    

    document.getElementById(`botoscope_create_${slug}`).addEventListener('click', e => Functions.add_table_row(table, slug));

    //for custom actions
    table.rebuild = (options) => {
        table.redraw();
    };

    //+++

    table.format_custom_header = format_custom_header;
    const language_selector = Functions.init_app_language_functionality(table, slug, languages);
    if (languages.selected_language !== languages.default_language) {
        language_selector.dispatchEvent(new Event('change'));
    }
    ;

    return table;
}

function format_custom_header(type = 1) {

    let header = [];

    switch (type) {
        case 1:
            header = [
                /*
                 {
                 value: botoscope_lang.default,
                 width: '5%',
                 key: 'is_default'
                 },
                 * 
                 */
                {
                    value: botoscope_lang.order,
                    width: '5%',
                    key: 'menu_order'
                },
                {
                    value: botoscope_lang.title,
                    width: '25%',
                    key: 'title'
                },
                {
                    value: botoscope_lang.price,
                    width: '10%',
                    key: 'price'
                },
                {
                    value: botoscope_lang.minimum,
                    width: '10%',
                    key: 'min_amount'
                },
                {
                    value: botoscope_lang.description,
                    width: '30%',
                    key: 'description'
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
            ];

            break;

        default:

            header = [
                {
                    value: 'ID',
                    width: '5%',
                    key: 'oid'
                },
                {
                    value: botoscope_lang.title,
                    width: '50%',
                    key: 'title'
                },
                {
                    value: botoscope_lang.description,
                    width: '45%',
                    key: 'description'
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
        import(botoscope_url + 'assets/js/table/ae/switcher.js')
    ]);

    return modules.map(mod => mod.default || mod);
}

