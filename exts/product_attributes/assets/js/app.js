const languages = {selected_language: null, default_language: null};
//24-03-2025
export default async function init_product_attributes() {
    if (botoscope_is_no_cart || botoscope_no_bot) {
        return false;
    }

    const [Functions, Helper, Table, Switcher] = await loadModules();
    let slug = 'product_attributes';
    let wrapper = document.getElementById(`botoscope-${slug}-w`);

    let table_data = {
        attributes: {
            class: `${slug} sortable-list`,
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
                    editable: ['title', 'cols_in_row', 'formula', 'icon'],
                    id: id,
                    attributes: {
                        class: 'sortable-item'
                    }
                }
            };
        }

    };

    //+++

    let instance_key = Helper.generate_key('t-');//!!
    table_data.instance_key = instance_key;

    //doesn work
    Helper.addSingleEventListener('data-table-drawn2', {instance_key}, e => {
        let t = e.detail.data.table;
        Functions.make_sortable(t.container, () => {
            console.log('done');
        });
    });

    wrapper.innerHTML = '';
    let table = new Table(wrapper, table_data, slug, {
        cell_content_drawn: data => {

            const cell = data.cell;

            switch (data.key) {

                case 'title':
                    {
                        setTimeout(() => Functions.draw_translatable_cell(table, slug, data, languages), 999);
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

                case 'menu_order':

                    {
                        let row_index = parseInt(cell.row_index);

                        let container = Helper.create_element('div', {
                            class: 'botoscope_row_menu_order',
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

                            //+++

                            Helper.ajax('botoscope_edit_cell', {
                                what: 'product_attributes_menu_order',
                                key: 'menu_order',
                                id: 0,
                                value: 1,
                                slug,
                                new_values
                            }, null, false);

                        }

                    }
                    break;

                case 'display_as':
                    {
                        let select = Helper.create_html_select({
                            button: botoscope_lang.button,
                            switcher: botoscope_lang.switcher
                        }, data.value, {
                            class: ''
                        }, {
                            name: 'change',
                            callback:
                                    e => {
                                        cell.set_value(e.target.value);//!!
                                        table.redraw()
                                    }
                        });

                        cell.set_node(select);
                    }
                    break;

                case 'cols_in_row':
                    {
                        let display_as = cell.table.data.rows[cell.row_index].display_as;

                        if (display_as === 'switcher') {
                            cell.draw_content('-');
                            cell.avoid_click_edit = true;
                        } else {
                            cell.draw_content(data.value);
                            cell.avoid_click_edit = false;
                        }
                    }
                    break;


                case 'taxonomy_slug':
                    {

                        let tdata = {};
                        tdata[''] = botoscope_lang.select_attribute;
                        let tdata_row = JSON.parse(document.getElementById('woocommerce_product_attributes').innerHTML);

                        if (tdata_row.length) {
                            tdata_row.forEach(d => {
                                tdata[d.slug] = d.name;
                            });
                        }

                        let select = Helper.create_html_select(tdata, data.value, {
                            class: ''
                        }, {
                            name: 'change',
                            callback: e => cell.set_value(e.target.value)
                        });

                        cell.set_node(select);
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
                                }, res => table.redraw(), false);
                            }
                        }
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
        }
    };

    //+++

    table.rebuild = (options) => {
        table.redraw();
    }

    //+++

    document.getElementById(`botoscope_create_${slug}`).addEventListener('click', e => Functions.add_table_row(table, slug));

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
                {
                    value: botoscope_lang.order,
                    width: '5%',
                    key: 'menu_order'
                },
                {
                    value: botoscope_lang.title,
                    width: '35%',
                    key: 'title'
                },
                {
                    value: botoscope_lang.attribute,
                    width: '10%',
                    key: 'taxonomy_slug'
                },
                {
                    value: botoscope_lang.display,
                    width: '10%',
                    key: 'display_as'
                },
                {
                    value: botoscope_lang.inline,
                    width: '10%',
                    key: 'cols_in_row'
                },
                /*
                 {
                 value: botoscope_lang.formula,
                 width: '10%',
                 key: 'formula'
                 },
                 * 
                 */
                {
                    value: botoscope_lang.icon,
                    width: '10%',
                    key: 'icon'
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
                    value: botoscope_lang.title,
                    width: '100%',
                    key: 'title'
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


