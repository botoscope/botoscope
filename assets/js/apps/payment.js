const languages = {selected_language: null, default_language: null};
//24-03-2025
export default async function init_payment() {
    if (botoscope_is_no_cart || botoscope_no_bot) {
        return false;
    }

    const [Functions, Helper, Table, Switcher] = await loadModules();

    let slug = 'payment';
    let wrapper = document.getElementById(`botoscope-${slug}-w`);

    let raw_rows_data = JSON.parse(wrapper.textContent);
    raw_rows_data = raw_rows_data.filter(method => method.id !== 'gift');

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
                    editable: ['title', 'details'],
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

                case 'title':
                case 'details':
                    {
                        setTimeout(() => Functions.draw_translatable_cell(table, slug, data, languages), 999);
                    }
                    break;

                case 'settings':
                    {

                        if (Object.keys(data.value).length) {

                            let btn = Helper.create_element('a', {
                                href: '#',
                                class: 'button button-primary'
                            }, botoscope_lang.settings, {
                                name: 'click',
                                callback:
                                        e => call_popup(btn, data.extra.id)
                            });

                            cell.set_node(btn);

                            function call_popup(btn, parent_row_id) {

                                let popup = Functions.create_popup({
                                    title: `${botoscope_lang.settings_for}: ${table.data.rows[cell.row_index].title}`,
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
                                    class: 'formulas-wrapper'
                                });

                                popup.clear_content();
                                popup.append_content(wrapper);

                                Helper.ajax('botoscope_get_parent_cell_data', {
                                    parent_app: 'payment',
                                    parent_cell_name: 'settings',
                                    parent_row_id
                                }, function (server_data) {
                                    const slug = 'payment_settings_table';

                                    let payment_table_data = {
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
                                                value: botoscope_lang.name,
                                                width: '30%',
                                                key: 'title'
                                            },
                                            {
                                                value: botoscope_lang.value,
                                                width: '70%',
                                                key: 'value'
                                            }
                                        ],
                                        raw_rows_data: server_data,
                                        format_data: function (rd) {
                                            const {id, ...rest} = rd;

                                            return {
                                                ...rest,
                                                extra: {
                                                    editable: ['value'],
                                                    id: id
                                                }
                                            };
                                        }
                                    };


                                    let additional_params = {
                                        parent_row_id: parent_row_id,
                                        parent_table: 'payment',
                                        parent_cell: 'settings'
                                    };


                                    let payment_table = new Table(wrapper, payment_table_data, slug, {
                                        cell_content_drawn: data => {

                                            switch (data.key) {

                                                case 'value':
                                                    //+++
                                                    break;

                                            }
                                        }
                                    });

                                    payment_table.data_is_mutated = (operation, data) => {
                                        switch (operation) {
                                            case 'edit_cell':
                                                Functions.edit_table_cell(data, slug, additional_params);
                                                break;
                                            case 'move_col_right':
                                            case 'move_col_left':
                                                payment_table.set_table_col_positions(data.positions, data.key, data.index);
                                                break;
                                        }
                                    };


                                }, true);
                            }
                        } else {
                            cell.draw_content('-');
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
            }
        }
    });

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

    table.rebuild = (options) => {
        //table.redraw(); - do need it hee as btn is in child btn inside
    }

    //+++

    table.format_custom_header = format_custom_header;
    const language_selector = Functions.init_app_language_functionality(table, slug, languages);
    if (languages.selected_language !== languages.default_language) {
        language_selector.dispatchEvent(new Event('change'));
    }

    return table;
}

function format_custom_header(type = 1) {

    let header = [];

    switch (type) {
        case 1:

            header = [
                {
                    value: botoscope_lang.title,
                    width: '30%',
                    key: 'title'
                },
                {
                    value: botoscope_lang.details,
                    width: '50%',
                    key: 'details'
                },
                {
                    value: botoscope_lang.settings,
                    width: '10%',
                    key: 'settings'
                },
                {
                    value: botoscope_lang.active,
                    width: '10%',
                    key: 'is_active'
                }
            ];

            break;

        default:

            header = [
                {
                    value: botoscope_lang.title,
                    width: '50%',
                    key: 'title'
                },
                {
                    value: botoscope_lang.details,
                    width: '50%',
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
        import(botoscope_url + 'assets/js/table/ae/switcher.js'),
    ]);

    return modules.map(mod => mod.default || mod);
}
