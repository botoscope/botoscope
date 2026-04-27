const languages = {selected_language: null, default_language: null};
//12-01-2026
export default async function init_interface_translations() {

    if (botoscope_no_bot) {
        return false;
    }

    const [Functions, Helper, Table] = await loadModules();
    let slug = 'interface_translations';
    let wrapper = document.getElementById(`botoscope-${slug}-w`);
    let search = document.getElementById(`botoscope-${slug}-search`);

    let table_data = {
        attributes: {
            class: slug,
            'data-data-table': slug,
            'data-order': 'desc',
            'data-order-by': 'id',
            'data-per-page': parseInt(wrapper.dataset.perPage),
            'data-records-count': parseInt(wrapper.dataset.itemsCount),
            id: `the_table_${slug}`
        },
        header: [
            {
                value: botoscope_lang.key,
                width: '4%',
                key: 'key'
            },
            {
                value: botoscope_lang.original,
                width: '48%',
                key: 'original'
            },
            {
                value: botoscope_lang.customized,
                width: '48%',
                key: 'title'
            }
        ],
        raw_rows_data: JSON.parse(wrapper.textContent),
        format_data: function (rd) {
            {
                const {id, is_active, ...rest} = rd;

                return {
                    ...rest,
                    extra: {
                        editable: ['title'],
                        id: id
                    }
                };
            }
        }
    };

    //+++

    wrapper.innerHTML = '';
    let table = new Table(wrapper, table_data, slug, {
        cell_content_drawn: data => {
            switch (data.key) {
                case 'key':
                    data.cell.draw_content(`<b>${data.value}</b>`);
                    break;
            }
        }
    });


    //+++

    table.set_page = (page_num) => Functions.reload_table_data(table, page_num, slug);

    //+++
    search.addEventListener('input', e => {
        if (e.target.value.length > 0) {
            table.search = e.target.value;

            table.rows.forEach(row => {
                row.display(0);
                if (row.data.original.includes(table.search) || row.data.title.includes(table.search)) {
                    row.display(1);
                }
            });

        } else {
            table.rows.forEach(row => {
                row.display(1);
            });
        }
    });

    //+++

    table.data_is_mutated = async(operation, data) => {
        switch (operation) {
            case 'edit_cell':
                await Functions.edit_table_cell(data, slug);
                break;
            case 'move_col_right':
            case 'move_col_left':
                await table.set_table_col_positions(data.positions, data.key, data.index);
                break;
        }
        
        search.dispatchEvent(new Event('input'));
    };

    //+++

    const language_selector = Functions.init_app_language_functionality(table, slug, languages);
    if (languages.selected_language !== languages.default_language) {
        language_selector.dispatchEvent(new Event('change'));
    }

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

