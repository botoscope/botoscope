//10-01-2025
export default async function init_sizecharts() {
    if (botoscope_is_no_cart || botoscope_no_bot) {
        return false;
    }
    const [Functions, Helper, Table] = await loadModules();
    let slug = 'sizecharts';
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
                value: botoscope_lang.default,
                width: '5%',
                key: 'is_default'
            },
            {
                value: botoscope_lang.title,
                width: '20%',
                key: 'title'
            },
            {
                value: botoscope_lang.description,
                width: '45%',
                key: 'description'
            },
            {
                value: botoscope_lang.table,
                width: '10%',
                key: 'table'
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
    let table = new Table(wrapper, table_data, slug, {

        cell_content_drawn: data => {
            switch (data.key) {

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

                        data.cell.set_node(radio);
                    }
                    break;

                case 'table':
                    {
                        let charts_count = data.value?.length ?? 0;

                        let btn = Helper.create_element('a', {
                            href: '#',
                            class: 'button button-primary'
                        }, `${botoscope_lang.manage} (${charts_count})`, {
                            name: 'click',
                            callback: e => call_popup(btn, data.extra.id)
                        });

                        data.cell.set_node(btn);

                        function call_popup(btn, parent_row_id) {

                            let popup = Functions.create_popup({
                                title: `${botoscope_lang.charts_for}: ${table.data.rows[data.cell.row_index].title}`,
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
                                class: 'charts-wrapper'
                            });

                            popup.clear_content();
                            popup.append_content(wrapper);

                            Helper.ajax('botoscope_get_parent_cell_data', {
                                parent_app: 'sizecharts',
                                parent_cell_name: 'sizecharts_chart_tables',
                                parent_row_id
                            }, function (server_data) {
                                let slug = 'chart_table';

                                let chart_data = {
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
                                            value: botoscope_lang.height,
                                            width: '10%',
                                            key: 'height'
                                        },
                                        {
                                            value: botoscope_lang.neck,
                                            width: '10%',
                                            key: 'neck'
                                        },
                                        {
                                            value: botoscope_lang.shoulder,
                                            width: '10%',
                                            key: 'shoulder'
                                        },
                                        {
                                            value: botoscope_lang.breast,
                                            width: '10%',
                                            key: 'breast'
                                        },
                                        {
                                            value: botoscope_lang.waist,
                                            width: '10%',
                                            key: 'waist'
                                        },
                                        {
                                            value: botoscope_lang.hip,
                                            width: '10%',
                                            key: 'hip'
                                        },
                                        {
                                            value: botoscope_lang.arm,
                                            width: '10%',
                                            key: 'arm'
                                        },
                                        {
                                            value: botoscope_lang.leg_length_from_waist,
                                            width: '20%',
                                            key: 'leg_length_from_waist'
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
                                                editable: ['height', 'neck', 'shoulder', 'breast', 'waist', 'hip', 'arm', 'leg_length_from_waist'],
                                                id: id
                                            }
                                        };
                                    }
                                };


                                let additional_params = {
                                    parent_row_id: parent_row_id,
                                    parent_table: 'sizecharts',
                                    parent_cell: 'table'
                                };


                                let chart_table = new Table(wrapper, chart_data, slug, {
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
                                                            chart_table.remove_row(row_id);

                                                            Helper.ajax('botoscope_delete_row', {
                                                                what: slug,
                                                                row_id,
                                                                additional_params
                                                            }, () => {
                                                                chart_table.redraw();
                                                                btn.innerText = btn.innerText.replace(/\(\d+\)/, `(${chart_table.data.rows.filter(row => row.extra.id > 0).length})`);
                                                            }, false);

                                                        }
                                                    }
                                                }
                                                break;
                                        }
                                    }
                                });



                                chart_table.data_is_mutated = (operation, data) => {
                                    switch (operation) {
                                        case 'edit_cell':
                                            Functions.edit_table_cell(data, slug, additional_params);
                                            break;
                                        case 'move_col_right':
                                        case 'move_col_left':
                                            chart_table.set_table_col_positions(data.positions, data.key, data.index);
                                            break;
                                    }
                                };

                                //+++

                                let a = Functions.create_element('a', {
                                    class: 'button button-primary',
                                    style: 'margin: 5px auto; display: flex; width: fit-content;'
                                }, botoscope_lang.append_new_row, {
                                    name: 'click',
                                    callback: e => {
                                        Functions.add_table_row(chart_table, slug, additional_params);
                                        chart_table.redraw();
                                        let count = chart_table.data.rows.filter(row => row.extra.id > 0).length + 1;
                                        btn.innerText = btn.innerText.replace(/\(\d+\)/, `(${count})`);
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
