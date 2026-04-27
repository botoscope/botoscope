let Functions, Helper, Table;
//31-07-2025
export default class MarketingProducts {
    constructor(parent_table, campaign_id, parent_cell) {
        this.parent_table = parent_table;
        this.campaign_id = campaign_id;
        this.parent_cell = parent_cell;
        this.init();
    }

    async init() {
        [Functions, Helper, Table] = await loadModules();
        this.draw();
    }

    async draw() {
        const [Functions, Helper, Table] = await loadModules();

        this.popup = Functions.create_popup({
            title: `${botoscope_lang.products_for}: ${this.parent_table.data.rows[this.parent_cell.row_index].title}`,
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

        this.draw_tabs();
    }

    draw_tabs() {
        this.popup.clear_content();

        let ul = Functions.create_element('ul', {
            id: 'botoscope-marketing-selected-products-tabs',
            class: 'botoscope-tabs'
        });

        let li = Functions.create_element('li');

        let a = Functions.create_element('a', {
            href: '#',
            'data-tab': 'products',
            class: 'botoscope-button selected'
        }, botoscope_lang.products);


        li.appendChild(a);
        ul.appendChild(li);
        /*
         * todo, under dev
         li = Functions.create_element('li');
         a = Functions.create_element('a', {
         href: '#',
         'data-tab': 'products',
         class: 'botoscope-button'
         }, botoscope_lang.terms);
         
         li.appendChild(a);
         ul.appendChild(li);
         
         
         li = Functions.create_element('li');
         a = Functions.create_element('a', {
         href: '#',
         'data-tab': 'products',
         class: 'botoscope-button'
         }, botoscope_lang.excluded_products);
         
         li.appendChild(a);
         ul.appendChild(li);
         */
        this.popup.append_content(ul);

        let wrapper1 = Functions.create_element('div', {
            class: 'form-body mt-4 botoscope-tab-container',
            style: 'display: block;'
        });

        let wrapper2 = Functions.create_element('div', {
            class: 'form-body mt-4 botoscope-tab-container',
            style: 'display: none;'
        });

        let wrapper3 = Functions.create_element('div', {
            class: 'form-body mt-4 botoscope-tab-container',
            style: 'display: none;'
        });

        this.popup.append_content(wrapper1);
        this.popup.append_content(wrapper2);
        this.popup.append_content(wrapper3);

        this.fill_tab1(wrapper1);
        this.fill_tab2(wrapper2);
        this.fill_tab3(wrapper3);

        botoscope_init_tabs(ul);
    }

    fill_tab1(popup_wrapper) {
        this.draw_products(popup_wrapper);
    }

    fill_tab2(popup_wrapper) {
        //under dev
        //popup_wrapper.innerHTML = 'Hello World 2026';
    }

    fill_tab3(popup_wrapper) {
        //under dev
        //this.draw_products(popup_wrapper, 'excluded_products');
    }

    draw_products(popup_wrapper, the_key = 'included_products') {
        let wrapper = Functions.create_element('div', {
            class: 'products-wrapper'
        });

        let table_products_wrapper = Functions.create_element('div', {
            class: 'products-wrapper',
            style: 'display: none'
        });

        table_products_wrapper.innerHTML = '';

        //+++

        let search_ajax_request = null;

        let input = Functions.create_element('input', {
            type: 'text',
            class: 'form-control search-input',
            placeholder: botoscope_lang.enter3_to_start_search,
        }, '', {
            name: 'keyup',
            callback: e => {
                let value = input.value;

                if (search_ajax_request) {
                    try {
                        search_ajax_request.abort('New request started');
                    } catch (e) {
                        console.error('Request aborted');
                    }
                }

                search_ajax_request = new AbortController();

                let can_search = false;

                if (value.length >= 3) {
                    can_search = true;
                } else if (/^(v\d+|\d+)$/i.test(value)) {
                    can_search = true;
                }

                if (can_search) {

                    Helper.ajax('botoscope_search_products', {
                        what: 'marketing_campaigns',
                        parent_row_id: this.campaign_id,
                        key: the_key,
                        value
                    }, server_data => {
                        wrapper.style.display = 'none';
                        table_products_wrapper.style.removeProperty('display');
                        cross.style.removeProperty('display');
                        table_products_wrapper.innerHTML = '';

                        //+++

                        let slug = 'marketing_campaigns_products_suggestion';

                        let suggested_products_data = {
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
                                    width: '90%',
                                    key: 'title'
                                },
                                {
                                    value: botoscope_lang.add,
                                    width: '10%',
                                    key: 'add'
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


                        let table_products = new Table(table_products_wrapper, suggested_products_data, slug, {
                            cell_content_drawn: data => {

                                switch (data.key) {

                                    case 'add':
                                        {
                                            let btn = Helper.create_element('input', {
                                                type: 'checkbox',
                                                class: 'input-checkbox'
                                            }, 'X', {
                                                name: 'change',
                                                callback: e => {
                                                    let add = 1;

                                                    if (!btn.checked) {
                                                        add = 0;
                                                    }

                                                    Helper.ajax('botoscope_edit_cell', {
                                                        what: 'marketing_campaigns',
                                                        value: data.extra.id,
                                                        id: this.campaign_id,
                                                        key: the_key,
                                                        add
                                                    }, async () => {
                                                        const products_table = await this.fill_products(this.parent_table, wrapper, this.campaign_id, 'marketing_campaigns_products', 'marketing_campaigns', the_key);
                                                        this.parent_table.products_count[this.campaign_id] = products_table.rows.length;
                                                        this.parent_table.redraw();
                                                    }, false);

                                                    return true;
                                                }
                                            });

                                            data.cell.set_node(btn);

                                        }
                                        break;
                                }
                            }
                        });

                    }, true, null, search_ajax_request.signal);

                } else {
                    wrapper.style.removeProperty('display');
                    table_products_wrapper.style.display = 'none';
                    cross.style.display = 'none';
                }
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

        popup_wrapper.appendChild(input);
        popup_wrapper.appendChild(cross);
        popup_wrapper.appendChild(wrapper);
        popup_wrapper.appendChild(table_products_wrapper);

        this.fill_products(this.parent_table, wrapper, this.campaign_id, 'marketing_campaigns_products', 'marketing_campaigns', the_key);
    }

    async fill_products(parent_table, wrapper, parent_row_id, slug, parent_app, parent_cell = 'included_products') {
        wrapper.innerHTML = '';
        let products_table;

        await Helper.ajax('botoscope_get_parent_cell_data', {
            parent_app,
            parent_cell_name: parent_cell,
            parent_row_id
        }, function (server_data) {
            let products_data = {
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
                        value: botoscope_lang.product,
                        width: '90%',
                        key: 'title'
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
                            editable: [],
                            id: id
                        }
                    };
                }
            };

            let additional_params = {
                parent_row_id: parent_row_id,
                parent_table: parent_app,
                parent_cell
            };

            products_table = new Table(wrapper, products_data, slug, {
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
                                        products_table.remove_row(row_id);

                                        Helper.ajax('botoscope_delete_row', {
                                            what: slug,
                                            row_id,
                                            additional_params
                                        }, () => {
                                            parent_table.products_count[parent_row_id] -= 1;
                                            parent_table.redraw();
                                            products_table.redraw();
                                        }, false);
                                    }
                                }
                            }
                            break;
                    }
                }
            });

            //+++

            products_table.data_is_mutated = (operation, data) => {
                switch (operation) {
                    case 'edit_cell':
                        Functions.edit_table_cell(data, slug, additional_params);
                        break;
                    case 'move_col_right':
                    case 'move_col_left':
                        products_table.set_table_col_positions(data.positions, data.key, data.index);
                        break;
                }
            };

        }, true);

        return products_table;
    }

}



async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js'),
        import(botoscope_url + 'assets/js/lib/sidebar.js')
    ]);

    return modules.map(mod => mod.default || mod);
}


