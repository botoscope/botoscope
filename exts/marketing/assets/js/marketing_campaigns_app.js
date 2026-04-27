const languages = {selected_language: null, default_language: null};
import MarketingProducts from './marketing_campaigns_products.js';

//07-08-2025
export default async function init_marketing_campaigns() {

    if (botoscope_is_no_cart || botoscope_no_bot) {
        return false;
    }

    const [Functions, Helper, Table, Calendar, CalendarSelector, Switcher] = await loadModules();
    let slug = 'marketing_campaigns';
    let wrapper = document.getElementById(`botoscope-${slug}-w`);

    let marketing_strategies_local_cache = document.getElementById('marketing_strategies_local_cache');

    if (!marketing_strategies_local_cache) {
        wrapper.innerHTML = botoscope_lang.mar_ext_should_active;
        return null;
    }

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
        header: format_custom_header(),
        raw_rows_data: JSON.parse(wrapper.textContent),
        format_data: function (rd) {
            const {id, ...rest} = rd;

            return {
                ...rest,
                extra: {
                    editable: ['title'],
                    id: id
                }
            };
        }
    };

    //+++

    const observer = new MutationObserver((mutationsList, observer) => {
        for (let mutation of mutationsList) {
            if (mutation.type === 'childList') {
                table.redraw();
            }
        }
    });

    observer.observe(marketing_strategies_local_cache.content, {
        attributes: true,
        childList: true,
        subtree: true
    });

    wrapper.innerHTML = '';
    let table = new Table(wrapper, table_data, slug, {
        cell_content_drawn: data => {

            const row_id = data.extra.id;
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

                case 'strategia_id':
                    {
                        cell.clear();
                        cell.container.style.flexDirection = 'column';

                        let cache = JSON.parse(marketing_strategies_local_cache.innerHTML);
                        let select_data = {0: botoscope_lang.select_strategia};

                        if (cache.length > 0) {
                            cache.forEach(item => {

                                let is_active = parseInt(item.is_active);
                                if (typeof item.is_active === 'object') {
                                    is_active = parseInt(item.is_active.value)
                                }


                                //if (is_active) {
                                select_data[parseInt(item.id??item.extra.id)] = item.title;
                                //}
                            });
                        }

                        const select = Helper.create_html_select(select_data, parseInt(data.value), {
                            class: ''
                        }, {
                            name: 'change',
                            callback:
                                    e => {
                                        data.value = e.target.value;
                                        Functions.edit_table_cell(data, slug, {}, table);
                                        active_campaign_observer();
                                    }
                        });

                        cell.append_node(select);

                        document.addEventListener('botoscope-marketing-strategy-edited', e => {
                            //console.log(e.detail.data.strategies);                           
                            Functions.update_select_options(select, e.detail.data.strategies);
                            active_campaign_observer();
                        });

                        //+++

                        let products_btn = Helper.create_element('a', {
                            href: '#',
                            class: 'button button-primary'
                        }, `${botoscope_lang.manage_products}`, {
                            name: 'click',
                            callback: e => new MarketingProducts(table, row_id, cell)
                        });

                        cell.append_node(products_btn);
                    }

                    break;

                case 'date':
                    {
                        cell.clear();
                        cell.container.style.flexDirection = 'column';

                        let container = Helper.create_element('div', {
                            class: 'calendar23-selector',
                            'data-name': 'time_start',
                            'data-date': parseInt(cell.get_sibling_value('time_start'))
                        });

                        let calendar = new CalendarSelector(container, 0, parseInt(cell.get_sibling_value('time_start')), botoscope_lang.set_start, {
                            show_time: true
                        });

                        calendar.selected = () => {
                            cell.refresh('time_start', calendar.unix_time_stamp);
                            Functions.save_cell_to_db('time_start', data.extra.id, calendar.unix_time_stamp, slug);
                            active_campaign_observer();
                        };

                        //+++

                        let container2 = Helper.create_element('div', {
                            class: 'calendar23-selector',
                            'data-name': 'time_finish',
                            'data-date': parseInt(cell.get_sibling_value('time_finish'))
                        });

                        let calendar2 = new CalendarSelector(container2, 0, parseInt(cell.get_sibling_value('time_finish')), botoscope_lang.set_finish, {
                            show_time: true
                        });

                        calendar2.selected = () => {
                            cell.refresh('time_finish', calendar2.unix_time_stamp);
                            Functions.save_cell_to_db('time_finish', data.extra.id, calendar2.unix_time_stamp, slug);
                            active_campaign_observer();
                        };

                        cell.append_node(container);
                        cell.append_node(container2);
                    }
                    break;

                case 'is_active':
                    {

                        cell.clear();

                        let switcher = new Switcher(this.id, parseInt(cell.table.data.rows[cell.row_index].is_active), cell.container);

                        switcher.setEvent('click', async (e, input) => {
                            cell.value = input.checked ? 1 : 0;
                            const sw_data = {...data, key: 'is_active', value: cell.value};

                            //only one campaign can be opened on the same time
                            for (let i = 0; i < cell.table.data.rows.length; i++) {
                                if (cell.row_index !== i) {
                                    cell.table.data.rows[i].is_active = 0;
                                    cell.table.data.raw_rows_data[i].is_active = 0;
                                }
                            }


                            await Functions.edit_table_cell(sw_data, slug, {}, table, true);
                            table.redraw();
                            active_campaign_observer();
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

    table.data_is_mutated = (operation, data) => {
        switch (operation) {
            case 'edit_cell':
                Functions.edit_table_cell(data, slug);
                active_campaign_observer();
                //table.redraw();
                break;
            case 'move_col_right':
            case 'move_col_left':
                table.set_table_col_positions(data.positions, data.key, data.index);
                break;
        }
    };

    //+++

    table.rebuild = (options) => {
        table.redraw();
    }

    //+++

    document.getElementById(`botoscope_create_${slug}`).addEventListener('click', e => Functions.add_table_row(table, slug));

    //+++

    table.format_custom_header = format_custom_header;
    const language_selector = Functions.init_app_language_functionality(table, slug, languages);
    if (languages.selected_language !== languages.default_language) {
        language_selector.dispatchEvent(new Event('change'));
    }

    //+++

    active_campaign_observer();

    Helper.addSingleEventListener('botoscope-marketing-campaign-call-observer', {instance_key: 1}, e => {
        active_campaign_observer();
    });

    //+++

    return table;
}

async function active_campaign_observer() {
    const [Functions, Helper, Table, Calendar, CalendarSelector, Switcher] = await loadModules();
    const container = document.getElementById('botoscope-marketing-active-campaign');
    const domain = window.location.origin;

    Helper.ajax('botoscope_marketing_campaigns_get', {}, async res => {
        if (res.success) {
            const id = res.data.id;
            const test_mode = parseInt(res.data.test_mode);
            container.innerHTML = `<span>${botoscope_lang.active}: #${id} | ${botoscope_lang.test_mode}: </span>`;

            let switcher = new Switcher('marketing_campaigns_test', test_mode, container);

            switcher.setEvent('click', async (e, input) => {
                const value = input.checked ? 1 : 0;

                if (value) {
                    container.querySelector('span').innerHTML = `${botoscope_lang.test_mode}: #${id}`;
                } else {
                    container.querySelector('span').innerHTML = `${botoscope_lang.active}: #${id} | ${botoscope_lang.test_mode}: `;
                }

                Helper.ajax('botoscope_marketing_campaigns_test_mode', {value}, null, false);
            });
        } else {
            container.innerHTML = `<em>${botoscope_lang.deactivated}</em>`;
        }
    }, true);
}

async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js'),
        import(botoscope_url + 'assets/js/lib/calendar23.js'),
        import(botoscope_url + 'assets/js/lib/calendar23-selector.js'),
        import(botoscope_url + 'assets/js/table/ae/switcher.js'),
    ]);

    return modules.map(mod => mod.default || mod);
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
                    value: botoscope_lang.description,
                    width: '40%',
                    key: 'title'
                },
                {
                    value: botoscope_lang.strategia_id,
                    width: '15%',
                    key: 'strategia_id'
                },
                {
                    value: botoscope_lang.date,
                    width: '20%',
                    key: 'date'
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
                    value: botoscope_lang.description,
                    width: '95%',
                    key: 'title'
                }
            ];

            break;
    }

    return header;
}
