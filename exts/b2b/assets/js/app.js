const languages = {selected_language: null, default_language: null};
//05-03-2025
export default async function init_app() {

    if (botoscope_is_no_cart || botoscope_no_bot) {
        return false;
    }

    const [Functions, Helper, Table] = await loadModules();
    let slug = 'b2b';
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
        header: [
            {
                value: botoscope_lang.name,
                width: '30%',
                key: 'title'
            },
            {
                value: botoscope_lang.value,
                width: '25%',
                key: 'value'
            },
            {
                value: botoscope_lang.description,
                width: '45%',
                key: 'description'
            },
        ],
        raw_rows_data: JSON.parse(wrapper.textContent),
        format_data: function (rd) {
            const {id, is_active, ...rest} = rd;

            return {
                ...rest,
                extra: {
                    editable: ['value'],
                    id: id
                }
            };
        }
    };

    //+++

    wrapper.innerHTML = '';
    let table = new Table(wrapper, data, slug, {
        cell_content_drawn: data => {
            //data.cell.test = 1;
            const cell = data.cell;

            switch (data.key) {
                case 'value':
                    switch (data.extra.id) {
                        case 'show_on_cart_set_qty_btn':
                            {
                                cell.avoid_click_edit = true;//!!

                                const select = Helper.create_html_select({1: botoscope_lang.yes, 0: botoscope_lang.no}, parseInt(data.value), {
                                    class: '',
                                }, {
                                    name: 'change',
                                    callback: e => cell.set_value(e.target.value)
                                });

                                cell.set_node(select);
                            }
                            break;

                        case 'about22':
                            cell.editable_type = 'textarea';//!!
                            break;
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

    return table;
}

async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js')
    ]);

    return modules.map(mod => mod.default || mod);
}
