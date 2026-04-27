//18-12-2025
export default async function init_support() {

    if (botoscope_no_bot) {
        return false;
    }

    const [Functions, Helper, Table] = await loadModules();
    let slug = 'support';
    let wrapper = document.getElementById(`botoscope-${slug}-w`);
    let search = document.getElementById(`botoscope-support-search`);
    let username = document.getElementById(`botoscope-support-username`);
    let web_site = document.getElementById(`botoscope-support-web`);
    let mode_select = document.getElementById(`botoscope-support-mode`);

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
                value: botoscope_lang.type,
                width: '10%',
                key: 'object_type'
            },
            {
                value: botoscope_lang.topic,
                width: '45%',
                key: 'object_id'
            },
            {
                value: botoscope_lang.interaction,
                width: '15%',
                key: 'interactions'
            },
            {
                value: botoscope_lang.unanswered,
                width: '10%',
                key: 'is_active'
            },
            {
                value: botoscope_lang.updated,
                width: '20%',
                key: 'updated'
            }
        ],
        raw_rows_data: JSON.parse(wrapper.textContent),
        format_data: function (rd) {
            {
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
        }
    };

    //+++

    wrapper.innerHTML = '';
    let table = new Table(wrapper, table_data, slug, {
        cell_content_drawn: data => {

            switch (data.key) {

                case 'updated_xxx':

                    const date = new Date(data.value * 1000);
                    const formattedDate = date.toLocaleDateString(botoscope_locale.replace('_', '-'), {
                        year: 'numeric',
                        month: 'long', //long, 2-digit
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    data.cell.draw_content(formattedDate);

                    break;

                case 'object_type':

                    switch (data.value) {
                        case 'product':
                            data.cell.draw_content('🏷️');
                            break;

                        case 'order':
                            data.cell.draw_content('📦');
                            break;
                    }

                    break;

                case 'object_id':
                    let btn;
                    let title = data.cell.table.data.rows[data.cell.row_index].object_title;
                    let link = data.cell.table.data.rows[data.cell.row_index].object_link;

                    if (data.cell.table.data.rows[data.cell.row_index].object_type === 'order') {
                        btn = Helper.create_element('a', {
                            href: link,
                            target: '_blank'
                        }, title);
                    } else {
                        const product_id = parseInt(data.cell.table.data.rows[data.cell.row_index].object_id);
                        title = `#${product_id} ${title}`;
                        btn = Helper.create_element('a', {
                            href: '#',
                        }, title, {
                            name: 'click',
                            callback: e => {
                                Helper.cast('botoscope-open-product-sidebar', {
                                    data: {product_id}
                                });
                            }
                        });
                    }

                    data.cell.set_node(btn);

                    break;
                case 'interactions':
                    {

                        let count = data.cell.table.data.rows[data.cell.row_index].messages_count;

                        let btn = Helper.create_element('a', {
                            href: '#',
                            class: 'button button-primary'
                        }, `${botoscope_lang.messages} (${count})`, {
                            name: 'click',
                            callback: e => interaction_popup(data.extra.id, table, data, btn)
                        });

                        data.cell.set_node(btn);
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

    table.set_page = (page_num) => Functions.reload_table_data(table, page_num, slug);

    //+++

    document.getElementById(`botoscope_create_${slug}`).addEventListener('click', e => {
        let popup = Functions.create_popup({
            title: botoscope_lang.search_orders,
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
            class: 'products-wrapper'
        });

        let wrapper2 = Functions.create_element('div', {
            class: 'products-wrapper',
            style: 'display: none'
        });

        wrapper2.innerHTML = '';

        popup.clear_content();

        //+++

        let search_ajax_request = null;

        let input = Functions.create_element('input', {
            type: 'text',
            class: 'form-control search-input',
            placeholder: botoscope_lang.enter_order_num,
        }, '', {
            name: 'keyup',
            callback: e => {
                let value = input.value;

                if (search_ajax_request) {
                    try {
                        search_ajax_request.abort('New request started');
                    } catch (e) {
                        console.log('Request aborted');
                    }
                }

                search_ajax_request = new AbortController();

                Helper.ajax('botoscope_search_orders', {
                    what: 'support',
                    value
                }, server_data => {
                    wrapper.style.display = 'none';
                    wrapper2.style.removeProperty('display');
                    cross.style.removeProperty('display');
                    wrapper2.innerHTML = '';

                    //+++

                    let slug = 'support_orders';

                    let suggested_orders_data = {
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
                                value: 'ID',
                                width: '10%',
                                key: 'oid'
                            },
                            {
                                value: botoscope_lang.status,
                                width: '20%',
                                key: 'status'
                            },
                            {
                                value: botoscope_lang.total,
                                width: '20%',
                                key: 'total'
                            },
                            {
                                value: botoscope_lang.date,
                                width: '40%',
                                key: 'date'
                            },
                            {
                                value: botoscope_lang.ticket,
                                width: '10%',
                                key: 'create'
                            }
                        ],
                        raw_rows_data: server_data,
                        format_data: function (rd) {
                            const {id, ...rest} = rd;

                            return {
                                ...rest,
                                extra: {
                                    editable: [],
                                    id: id
                                }
                            };
                        }
                    };


                    let table = new Table(wrapper2, suggested_orders_data, slug, {
                        cell_content_drawn: data => {

                            switch (data.key) {

                                case 'oid':
                                    data.cell.draw_content(data.extra.id);
                                    break;

                                case 'create':
                                    {
                                        let btn = Helper.create_element('a', {
                                            class: 'button button-primary'
                                        }, 'contact', {
                                            name: 'click',
                                            callback: e => click(data.extra.id)
                                        });

                                        data.cell.set_node(btn);

                                        function click(order_id) {

                                            Helper.ajax('botoscope_support_get_ticket_id', {
                                                object_type: 'order',
                                                object_id: order_id
                                            }, (ticket_id) => {
                                                ticket_id = parseInt(ticket_id);
                                                interaction_popup(parseInt(ticket_id), table, data)
                                            }, false);

                                            return true;
                                        }
                                    }
                                    break;
                            }
                        }
                    });

                }, true, null, search_ajax_request.signal);

            }
        });

        //+++

        let cross = Functions.create_element('a', {
            href: '#',
            class: 'botoscope_search_cross',
        }, 'x', {
            name: 'click',
            callback: e => {
                input.value = '';
                input.dispatchEvent(new Event('keyup'));
            }
        });

        cross.style.display = 'none';

        //+++

        popup.append_content(input);
        popup.append_content(cross);
        popup.append_content(wrapper);
        popup.append_content(wrapper2);

        return false;
    });

    //+++


    search.addEventListener('keydown', (event) => {
        if (search.value.length > 0) {
            table.search = search.value;
            if (event.key === 'Enter') {
                event.preventDefault();
                Functions.reload_table_data(table, 0, slug);
                return false;
            }
        }
    });


    search.addEventListener('keyup', (event) => {
        if (search.value.length === 0) {
            table.search = search.value;
            if (event.key !== 'Enter') {
                event.preventDefault();
                Functions.reload_table_data(table, 0, slug);
                return false;
            }
        }
    });

    //+++    

    mode_select.addEventListener('change', e => {
        const value = e.target.value;

        switch (value) {
            case 'username':
                document.getElementById('botoscope-support-username-block').style.display = 'block';
                document.getElementById('botoscope-support-web-block').style.display = 'none';
                document.getElementById('botoscope-support-system-block').style.display = 'none';
                break;

            case 'web_site':
                document.getElementById('botoscope-support-web-block').style.display = 'block';
                document.getElementById('botoscope-support-username-block').style.display = 'none';
                document.getElementById('botoscope-support-system-block').style.display = 'none';
                break;

            default:
                //system
                document.getElementById('botoscope-support-system-block').style.display = 'block';
                document.getElementById('botoscope-support-username-block').style.display = 'none';
                document.getElementById('botoscope-support-web-block').style.display = 'none';
                break;
        }

        Helper.ajax('botoscope_support_set_mode', {value}, () => Functions.message(botoscope_lang.saved), false);
    });


    username.addEventListener('keydown', e => {
        const value = e.target.value.trim();

        if (e.key === 'Enter' && value.length > 0) {
            e.preventDefault();
            Helper.ajax('botoscope_support_set_username', {value}, () => Functions.message(botoscope_lang.saved), false);
            return false;
        }
    });


    web_site.addEventListener('keydown', e => {
        const value = e.target.value.trim();

        if (e.key === 'Enter' && value.length > 0) {
            e.preventDefault();
            Helper.ajax('botoscope_support_set_web_site', {value}, () => Functions.message(botoscope_lang.saved), false);
            return false;
        }
    });


    //+++

    function interaction_popup(parent_row_id, table = null, data = null, btn = null) {

        let popup_data = {
            title_logo: botoscope_url + 'assets/img/dolphin.svg',
            title_top_info: botoscope_lang.botoscope,
            left_button_word: '',
            close_word: botoscope_lang.close,
            footer_buttons_change_sides: 0,
            width: botoscope_is_mobile ? '100%' : '85%',
            height: botoscope_is_mobile ? '85%' : '65%',
            top: '12%',
            bottom: '12%',
            left: botoscope_is_mobile ? 0 : '12%',
            right: '12%',
            mousemove: true
        };

        if (table) {
            popup_data.title = `${botoscope_lang.messages_for}: ${table.data.rows[data.cell.row_index].object_title}`;
        } else {
            popup_data.title = `${botoscope_lang.messages_for_order} #${parent_row_id}`;
        }

        //+++

        let popup = Functions.create_popup(popup_data);

        let wrapper = Functions.create_element('div', {
            class: 'formulas-wrapper'
        });

        popup.clear_content();
        popup.append_content(wrapper);

        Helper.ajax('botoscope_get_parent_cell_data', {
            parent_app: 'support',
            parent_cell_name: 'interactions',
            parent_row_id
        }, function (server_data) {
            let slug = 'support_interactions_table';

            let interactions_table_data = {
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
                        value: botoscope_lang.role,
                        width: '10%',
                        key: 'message_type'
                    },
                    {
                        value: botoscope_lang.content,
                        width: '70%',
                        key: 'content'
                    },
                    {
                        value: botoscope_lang.time,
                        width: '20%',
                        key: 'time'
                    }
                ],
                raw_rows_data: server_data,
                format_data: function (rd) {
                    const {id, ...rest} = rd;

                    return {
                        ...rest,
                        extra: {
                            editable: ['content2'],
                            id: id
                        }
                    };
                },
                footer: 0
            };


            let additional_params = {
                parent_row_id: parent_row_id,
                parent_table: 'support',
                parent_cell: 'interactions'
            };


            let interactions_table = new Table(wrapper, interactions_table_data, slug, {
                cell_content_drawn: data => {

                    switch (data.key) {
                        case 'message_type':

                            switch (data.value) {
                                case 'question':
                                    data.cell.draw_content('🙋‍♂️');
                                    break;

                                case 'answer':
                                    data.cell.draw_content('🎧');
                                    break;
                            }

                            break;

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
                                        interactions_table.remove_row(row_id);

                                        Helper.ajax('botoscope_delete_row', {
                                            what: slug,
                                            row_id,
                                            additional_params
                                        }, () => {
                                            interactions_table.redraw();
                                            if (btn) {
                                                btn.innerText = btn.innerText.replace(/\(\d+\)/, `(${interactions_table.data.rows.filter(row => row.extra.id > 0).length})`);
                                            }
                                        }, false);

                                    }
                                }
                            }
                            break;
                    }
                }
            });

            interactions_table.data_is_mutated = (operation, data) => {
                switch (operation) {
                    case 'edit_cell':
                        Functions.edit_table_cell(data, slug, additional_params);
                        break;
                    case 'move_col_right':
                    case 'move_col_left':
                        interactions_table.set_table_col_positions(data.positions, data.key, data.index);
                        break;

                }
            };

            //+++

            let text = Functions.create_element('textarea', {
                class: 'regular-text widefat',
                style: 'width: 100%; margin-bottom: 5px;',
                rows: 5
            }, '');

            popup.append_content(text);

            setTimeout(() => {
                text.focus();
            }, 333);

            //+++

            let add_btn = Functions.create_element('a', {
                class: 'button button-primary',
                style: 'margin: 0 auto; display: flex; width: fit-content;'
            }, botoscope_lang.send_message, {
                name: 'click',
                callback: e => {

                    e.preventDefault();

                    if (text.value.length > 0) {
                        additional_params.content = text.value;
                        Functions.add_table_row(interactions_table, slug, additional_params);
                        let count = parseInt(interactions_table.data.rows.length) + 1;
                        if (btn) {
                            btn.innerText = `${botoscope_lang.messages} (${count})`;
                        }
                        text.value = '';
                        if (data) {
                            data.table.data.rows[data.cell.row_index].is_active = '✔️';
                            data.cell.table.redraw();
                        }
                        setTimeout(() => {
                            text.focus();
                        }, 111);
                    }

                    return false;
                }
            });

            popup.append_content(add_btn);

            text.addEventListener('keydown', function (event) {
                if (event.ctrlKey && event.key === 'Enter') {
                    add_btn.click();
                }
            });


        }, true);

    }


    //+++


    return table;
}

//+++


async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js')
    ]);

    return modules.map(mod => mod.default || mod);
}
