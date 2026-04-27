const languages = {selected_language: null, default_language: null};
//24-03-2025
export default async function init_elogios() {
    if (botoscope_is_no_cart || botoscope_no_bot) {
        return false;
    }

    const [Functions, Helper, Table, Switcher] = await loadModules();
    let slug = 'elogios';
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
                    editable: ['title'],
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

    document.getElementById(`botoscope_create_${slug}`).addEventListener('click', e => Functions.add_table_row(table, slug));

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
                {
                    value: 'ID',
                    width: '5%',
                    key: 'oid'
                },
                {
                    value: botoscope_lang.title,
                    width: '75%',
                    key: 'title'
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
                    width: '95%',
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
        import(botoscope_url + 'assets/js/table/ae/switcher.js'),
    ]);

    return modules.map(mod => mod.default || mod);
}
