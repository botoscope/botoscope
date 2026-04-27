let timer1;
//04-08-2025
export default async function init_marketing_strategies() {
    if (botoscope_is_no_cart || botoscope_no_bot) {
        return false;
    }

    const [Functions, Helper, Table] = await loadModules();
    let slug = 'marketing_strategies';
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
                value: botoscope_lang.title,
                width: '20%',
                key: 'title'
            },
            {
                value: botoscope_lang.description,
                width: '50%',
                key: 'description'
            },
            {
                value: botoscope_lang.formulas,
                width: '10%',
                key: 'formulas'
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
                    el_type: "switcher",
                    value: is_active
                },
                extra: {
                    editable: ['title', 'description'],
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
                case 'formulas':
                    {
                        let formulas_count = data.value?.length??0;

                        let btn = Helper.create_element('a', {
                            href: '#',
                            class: 'button button-primary'
                        }, `${botoscope_lang.manage} (${formulas_count})`, {
                            name: 'click',
                            callback: e => call_formulas_popup(btn, data.extra.id)
                        });

                        data.cell.set_node(btn);

                        function call_formulas_popup(btn, parent_row_id) {

                            let popup = Functions.create_popup({
                                title: `${botoscope_lang.formulas_for}: ${table.data.rows[data.cell.row_index].title}`,
                                title_logo: botoscope_url + 'assets/img/dolphin.svg',
                                title_top_info: botoscope_lang.botoscope,
                                left_button_word: '',
                                close_word: botoscope_lang.close,
                                footer_buttons_change_sides: 0,
                                width: '75%',
                                height: '75%',
                                top: '12%',
                                bottom: '12%',
                                left: '12%',
                                right: '12%',
                                mousemove: true
                            });

                            let wrapper = Functions.create_element('div', {
                                class: 'formulas-wrapper'
                            });

                            popup.clear_content();
                            popup.append_content(wrapper);

                            Helper.ajax('botoscope_get_parent_cell_data', {
                                parent_app: 'marketing_strategies',
                                parent_cell_name: 'formulas',
                                parent_row_id
                            }, function (server_data) {
                                let slug = 'marketing_strategies_formulas_table';

                                let formulas_data = {
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
                                            value: botoscope_lang.formula,
                                            width: '90%',
                                            key: 'formula'
                                        },
                                        {
                                            value: botoscope_lang.delete,
                                            width: '10%',
                                            key: 'delete'
                                        }
                                    ],
                                    raw_rows_data: server_data,
                                    format_data: function (rd) {
                                        const {id, ...rest} = rd;

                                        return {
                                            ...rest,
                                            extra: {
                                                editable: ['formula'],
                                                id: id
                                            }
                                        };
                                    }
                                };


                                let additional_params = {
                                    parent_row_id: parent_row_id,
                                    parent_table: 'marketing_strategies',
                                    parent_cell: 'formulas'
                                };


                                let formulas_table = new Table(wrapper, formulas_data, slug, {
                                    cell_content_drawn: data => {
                                        switch (data.key) {
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
                                                            formulas_table.remove_row(row_id);

                                                            Helper.ajax('botoscope_delete_row', {
                                                                what: slug,
                                                                row_id,
                                                                additional_params
                                                            }, () => {
                                                                formulas_table.redraw();
                                                                btn.innerText = btn.innerText.replace(/\(\d+\)/, `(${formulas_table.data.rows.filter(row => row.extra.id > 0).length})`);
                                                            }, false);

                                                        }
                                                    }
                                                }
                                                break;
                                        }
                                    }
                                });

                                formulas_table.data_is_mutated = (operation, data) => {
                                    switch (operation) {
                                        case 'edit_cell':
                                            Functions.edit_table_cell(data, slug, additional_params);
                                            break;
                                        case 'move_col_right':
                                        case 'move_col_left':
                                            formulas_table.set_table_col_positions(data.positions, data.key, data.index);
                                            break;
                                    }
                                };

                                //+++

                                let a = Functions.create_element('a', {
                                    class: 'button button-primary',
                                    style: 'margin: 5px auto; display: flex; width: fit-content;float:left;'
                                }, botoscope_lang.append_formula, {
                                    name: 'click',
                                    callback: e => {
                                        Functions.add_table_row(formulas_table, slug, additional_params);
                                        btn.innerText = btn.innerText.replace(/\(\d+\)/, `(${formulas_table.data.rows.filter(row => row.extra.id > 0).length + 1})`);
                                    }
                                });

                                popup.append_content(a);
                            }, true);

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
                            callback: e => call_delete_cell(btn, data.extra.id)
                        });

                        data.cell.set_node(btn);

                        function call_delete_cell(btn, row_id) {
                            if (confirm(botoscope_lang.are_you_sure)) {
                                table.remove_row(row_id);
                                update_campaign_selectes(table);

                                Helper.ajax('botoscope_delete_row', {
                                    what: slug,
                                    row_id
                                }, () => {
                                    table.redraw();
                                    active_campaign_observer();
                                }, false);

                            }
                        }
                    }
                    break;
            }
        }
    });

    //+++

    table.data_is_mutated = async (operation, data) => {
        switch (operation) {
            case 'edit_cell':
                if (data.key === 'title') {
                    await update_campaign_selectes(table);
                }

                await Functions.edit_table_cell(data, slug);
                active_campaign_observer();
                break;
            case 'move_col_right':
            case 'move_col_left':
                table.set_table_col_positions(data.positions, data.key, data.index);
                break;
        }
    };

    //+++    

    document.getElementById(`botoscope_create_${slug}`).addEventListener('click', e => Functions.add_table_row(table, slug));
    return table;
}


async function update_campaign_selectes(table) {

    const [Functions, Helper] = await loadModules();

    if (timer1) {
        clearTimeout(timer1);
    }

    timer1 = setTimeout(() => {
        const strategies = table.data.rows.map(item => ({
                title: item.title,
                id: item.extra?.id ?? 0
            }));

        strategies.unshift({title: botoscope_lang.select_strategia, id: 0});

        Helper.cast('botoscope-marketing-strategy-edited', {
            data: {strategies}
        });

    }, 777);
}

async function active_campaign_observer() {
    const [Functions, Helper] = await loadModules();
    Helper.cast('botoscope-marketing-campaign-call-observer', {});
}

async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js')
    ]);

    return modules.map(mod => mod.default || mod);
}
