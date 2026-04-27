import * as Functions from '../lib/functions.js';
import Helper from '../table/lib/helper.js';
import Table from '../table/table.js';
//13-10-2025
const languages = {selected_language: null, default_language: null};

export default function init_extensions() {
    let slug = 'extensions';
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
                    editable: [],
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

                case 'help':
                    {
                        const help_link = data.value;
                        cell.clear();

                        if (help_link.length) {
                            const icon = Helper.create_element('a', {
                                href: help_link,
                                'data-tip': help_link,
                                target:'_blank',
                                style: 'cursor: pointer; display: block;'
                            }, `<svg class="bs-tip__icon" width="28" height="28" viewBox="0 0 24 24" aria-label="Info" role="img">
                                <circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.12"></circle>
                                <circle cx="12" cy="12" r="9.25" fill="none" stroke="currentColor" stroke-width="1.5"></circle>
                                <path d="M12 10.5v6M12 7.5h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
                            </svg>`);

                            cell.append_node(icon);
                        }
                    }
                    break;

                case 'is_active':
                    if (table.data.rows[data.cell.row_index]?.nonswitchable) {
                        data.cell.clear();
                    }
                    break;

                case 'menu_order':

                    {
                        let row_index = parseInt(data.cell.row_index);

                        let container = Helper.create_element('div', {
                            class: 'botoscope_row_menu_order',
                        }, '');

                        data.cell.set_node(container);

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

                        if (row_index < data.cell.table.data.rows.length - 1) {
                            let btn_down = Helper.create_element('a', {
                                href: '#',
                                class: 'arrow-button-down'
                            }, '', {
                                name: 'click',
                                callback: e => move(row_index, 'down')
                            });

                            container.appendChild(btn_down);
                        }

                        function move(row_index, direction) {

                            let tr = data.cell.table.data.rows;//short link to object
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
                            let menu = document.getElementById('botoscope_menu_manager_menu');
                            data.cell.table.data.rows.forEach((value, index) => {
                                new_values.push({
                                    menu_order: index,
                                    gateway: value.extra.id
                                });

                                if (menu.querySelector(`li[data-slug="${value.extra.id}"]`)) {
                                    menu.querySelector(`li[data-slug="${value.extra.id}"]`).style.order = index;
                                }
                            });

                            table.redraw();

                            //+++

                            Helper.ajax('botoscope_edit_cell', {
                                what: 'extensions_menu_order',
                                key: 'menu_order',
                                id: 0,
                                value: 1,
                                slug,
                                new_values
                            }, null, false);
                        }

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

                            data.cell.set_node(btn);

                            function call_popup(btn, parent_row_id) {

                                let popup = Functions.create_popup({
                                    title: `${botoscope_lang.settings_for}: ${table.data.rows[data.cell.row_index].title}`,
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
                                    parent_app: 'extensions',
                                    parent_cell_name: 'settings',
                                    parent_row_id
                                }, function (server_data) {
                                    let slug = 'extensions_settings_table';

                                    let extensions_table_data = {
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
                                        parent_table: 'extensions',
                                        parent_cell: 'settings'
                                    };


                                    let extensions_table = new Table(wrapper, extensions_table_data, slug, {
                                        cell_content_drawn: data => {

                                            switch (data.key) {

                                                case 'value':
                                                    //+++
                                                    break;

                                            }
                                        }
                                    });

                                    extensions_table.data_is_mutated = (operation, data) => {
                                        switch (operation) {
                                            case 'edit_cell':
                                                Functions.edit_table_cell(data, slug, additional_params);
                                                break;
                                            case 'move_col_right':
                                            case 'move_col_left':
                                                extensions_table.set_table_col_positions(data.positions, data.key, data.index);
                                                break;
                                        }
                                    };


                                }, true);
                            }
                        } else {
                            data.cell.draw_content('-');
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

                if (data.key === 'is_active') {
                    table.create_loader();
                }

                await Functions.edit_table_cell(data, slug);

                if (data.key === 'is_active') {
                    window.location.reload();
                }

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
    return table;
}

function format_custom_header() {
    return [
        {
            value: botoscope_lang.order,
            width: '5%',
            key: 'menu_order'
        },
        {
            value: botoscope_lang.name,
            width: '25%',
            key: 'title'
        },
        {
            value: botoscope_lang.description,
            width: '40%',
            key: 'description'
        },
        {
            value: botoscope_lang.help,
            width: '10%',
            key: 'help'
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
        },
    ];
}
