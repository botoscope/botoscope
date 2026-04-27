let shipping_ways = [];
const languages = {selected_language: null, default_language: null};
//10-01-2025
export default async function init_pickup_points() {
    if (botoscope_is_no_cart || botoscope_no_bot) {
        return false;
    }

    const [Functions, Helper, Table, SelectM23] = await loadModules();
    let slug = 'pickup_points';
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
                    el_type: "switcher",
                    value: is_active
                },
                extra: {
                    editable: ['title', 'address', 'details'],
                    id: id
                }
            };
        }
    };

    //+++

    wrapper.innerHTML = '';

    let table = new Table(wrapper, data, slug, {
        cell_content_drawn: data => {

            switch (data.key) {

                case 'oid':
                    if (data.extra.id > 0) {
                        data.cell.draw_content(data.extra.id);
                    }
                    break;

                case 'title':
                case 'address':
                case 'details':
                    {
                        setTimeout(() => Functions.draw_translatable_cell(table, slug, data, languages), 999);
                    }
                    break;

                case 'shipping_ways':
                        Helper.addSingleEventListener('botoscope-shiping-ways-loaded', {instance_key:
                                data.cell.instance_key}, e => draw_cell(e.detail.data.shipping_ways));

                    draw_cell(shipping_ways);

                    function draw_cell(ways) {
                        data.cell.clear();
                        let select_options = {};

                        if (ways.length > 0) {
                            ways.forEach(w => {
                                select_options[w.id] = w.title;
                            });
                        }

                        let selected_ids = [];
                        if (data.value?.length > 0) {
                            selected_ids = data.value.split(',');
                        }

                        let select = Helper.create_html_select(select_options, selected_ids, {
                            class: '',
                            multiple: 'multiple'
                        }, {
                            name: 'change',
                            callback:
                                    e => {
                                        data.value = sm23.selected_values.join(',');
                                        Functions.edit_table_cell(data, slug, {}, table);
                                    }
                        });

                        data.cell.set_node(select);
                        let sm23 = new SelectM23(select, false, botoscope_lang.delivery_methods);
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

                        data.cell.set_node(btn);

                        function call_delete_cell(btn, row_id) {
                            if (confirm(botoscope_lang.are_you_sure)) {
                                table.remove_row(row_id);

                                Helper.ajax('botoscope_delete_row', {
                                    what: slug,
                                    row_id
                                }, () => {
                                    table.redraw();
                                }, false);

                            }
                        }
                    }
                    break;
            }
        }
    });

    update_shipping_ways();

    Helper.addSingleEventListener('botoscope-shiping-ways-updated', {instance_key: table.instance_key}, e => update_shipping_ways());

    //+++

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

    document.getElementById(`botoscope_create_${slug}`).addEventListener('click', e => {
        Functions.add_table_row(table, slug);
        table.redraw();
    });


    //for custom actions
    table.rebuild = (options) => {
        table.redraw();
    };

    //+++

    function update_shipping_ways() {
        Helper.ajax('botoscope_shipping_get', {}, (res) => {
            shipping_ways = res;

            Helper.cast('botoscope-shiping-ways-loaded', {
                data: {
                    shipping_ways
                }
            });
        }, true);

    }

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
                    value: 'ID',
                    width: '5%',
                    key: 'oid'
                },
                {
                    value: botoscope_lang.name,
                    width: '10%',
                    key: 'title'
                },
                {
                    value: botoscope_lang.address,
                    width: '25%',
                    key: 'address'
                },
                {
                    value: botoscope_lang.details,
                    width: '20%',
                    key: 'details'
                },
                {
                    value: botoscope_lang.shipping,
                    width: '20%',
                    key: 'shipping_ways'
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
                    value: botoscope_lang.name,
                    width: '25%',
                    key: 'title'
                },
                {
                    value: botoscope_lang.address,
                    width: '35%',
                    key: 'address'
                },
                {
                    value: botoscope_lang.details,
                    width: '40%',
                    key: 'details'
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
        import(botoscope_url + 'assets/js/lib/selectm-23.js')
    ]);

    return modules.map(mod => mod.default || mod);
}

