import * as Functions from '../lib/functions.js';
import Helper from '../table/lib/helper.js';
import Table from '../table/table.js';
import SelectM23 from '../lib/selectm-23.js';
//09-03-2026
export default function init_controls() {
    let slug = 'controls';
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
                width: '70%',
                key: 'value'
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
            const cell = data.cell;
            data.cell.test = 1;

            switch (data.key) {

                case 'title':
                    {
                        cell.draw_content(data.value);
                        const help_text = cell.table.data.raw_rows_data[cell.row_index]?.help;

                        if (help_text) {
                            const icon = Helper.create_element('span', {
                                class: 'bs-tip',
                                'data-tip': help_text,
                                style: 'position: absolute; right: 5px;'
                            }, `<svg class="bs-tip__icon" width="18" height="18" viewBox="0 0 24 24" aria-label="Info" role="img">
                                <circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.12"></circle>
                                <circle cx="12" cy="12" r="9.25" fill="none" stroke="currentColor" stroke-width="1.5"></circle>
                                <path d="M12 10.5v6M12 7.5h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
                            </svg>`);

                            cell.append_node(icon);
                        }
                    }
                    break;

                case 'value':

                    let languages = JSON.parse(document.getElementById('botoscope_languages_list').innerHTML);

                    switch (data.extra.id) {
                        case 'default_language':
                            {
                                cell.avoid_click_edit = true;//!!

                                let select = Helper.create_html_select(languages, data.value, {
                                    class: '',
                                }, {
                                    name: 'change',
                                    callback:
                                            async e => {
                                                Functions.message(botoscope_lang.loading, 'warning', -1);
                                                data.value = e.target.value;
                                                cell.set_value(data.value);
                                                //await Functions.edit_table_cell(data, slug);
                                                table.redraw();
                                                Functions.message(botoscope_lang.done);
                                                window.location.reload();
                                            }
                                });

                                cell.set_node(select);
                            }
                            break;

                        case 'languages':
                            {

                                cell.avoid_click_edit = true;//!!
                                let selected_languages = [];
                                let default_language = cell.table.data.rows[cell.row_index - 1].value;

                                if (data.value) {
                                    selected_languages = data.value.split(',');
                                    selected_languages = selected_languages.filter(language => language !== default_language);
                                }

                                let filtered_languages = Object.assign({}, reorderLanguages(languages, selected_languages));
                                delete filtered_languages[default_language];

                                let select = Helper.create_html_select(filtered_languages, selected_languages, {
                                    class: '',
                                    multiple: 'multiple'
                                }, {
                                    name: 'change',
                                    callback:
                                            e => {
                                                data.value = sm23.selected_values.join(',');
                                                Functions.edit_table_cell(data, slug);

                                                setTimeout(() => {
                                                    Helper.cast('botoscope-selected-bot-languages', {
                                                        data: {
                                                            languages: sm23.selected_values.filter(value => typeof value !== undefined),
                                                            default_language
                                                        }
                                                    });
                                                }, 1999);

                                            }
                                });

                                cell.set_node(select);
                                let sm23 = new SelectM23(select, true, botoscope_lang.bot_languages);

                                select.addEventListener('selectm23-reorder', e => {
                                    data.value = e.detail.values;
                                    Functions.edit_table_cell(data, slug);

                                    setTimeout(() => {
                                        Helper.cast('botoscope-selected-bot-languages', {
                                            data: {
                                                languages: sm23.selected_values.filter(value => typeof value !== undefined),
                                                default_language
                                            }
                                        });
                                    }, 1999);
                                });
                            }
                            break;

                        case 'products_full_reset':
                            {
                                cell.avoid_click_edit = true;//!!
                                const btn = Helper.create_element('a', {
                                    href: '#',
                                    id: 'botoscope_reset_cache_products',
                                    class: 'button button-primary'
                                }, botoscope_lang.reset_products_cache️, {
                                    name: 'click',
                                    callback: e => {
                                        btn.style.display = 'none';
                                        Functions.message(botoscope_lang.loading, 'warning', -1);
                                        Helper.ajax('botoscope_reset_cache', {
                                            cache_name: 'products'
                                        }, function () {

                                            setTimeout(() => {
                                                btn.style.display = 'inline-block';
                                                Functions.message(botoscope_lang.done);
                                            }, 1000 * 60);


                                        }, false);

                                    }
                                });

                                cell.set_node(btn);
                            }
                            break;


                        case 'system_full_reset':
                            {
                                cell.avoid_click_edit = true;//!!
                                const btn = Helper.create_element('a', {
                                    href: '#',
                                    id: 'botoscope_reset_cache_system',
                                    class: 'button button-primary'
                                }, botoscope_lang.reset_system_cache️, {
                                    name: 'click',
                                    callback: e => {

                                        const products_btn = document.getElementById('botoscope_reset_cache_products');
                                        const options_btn = document.getElementById('botoscope_reset_system_options');

                                        btn.style.display = 'none';
                                        products_btn.style.display = 'none';
                                        options_btn.style.display = 'none';

                                        Functions.message(botoscope_lang.loading, 'warning', -1);

                                        Helper.ajax('botoscope_reset_cache', {
                                            cache_name: 'options'
                                        }, function () {
                                            setTimeout(() => {
                                                Helper.ajax('botoscope_reset_cache', {
                                                    cache_name: 'products'
                                                }, function () {

                                                    setTimeout(() => {
                                                        btn.style.display = 'inline-block';
                                                        products_btn.style.display = 'inline-block';
                                                        options_btn.style.display = 'inline-block';
                                                        Functions.message(botoscope_lang.done);
                                                    }, 1000 * 60);


                                                }, false);
                                            }, 1000 * 20);
                                        }, false);
                                    }
                                });

                                cell.set_node(btn);
                            }
                            break;


                        case 'options_full_reset':
                            {
                                cell.avoid_click_edit = true;//!!
                                const btn = Helper.create_element('a', {
                                    href: '#',
                                    id: 'botoscope_reset_system_options',
                                    class: 'button button-primary'
                                }, botoscope_lang.reset_options_cache️, {
                                    name: 'click',
                                    callback: e => {
                                        btn.style.display = 'none';
                                        Functions.message(botoscope_lang.loading, 'warning', -1);
                                        Helper.ajax('botoscope_reset_cache', {
                                            cache_name: 'options'
                                        }, function () {
                                            setTimeout(() => {
                                                btn.style.display = 'inline-block';
                                                Functions.message(botoscope_lang.done);
                                            }, 1000 * 30);
                                        }, false);

                                    }
                                });

                                cell.set_node(btn);
                            }
                            break;

                        case 'order_logo':
                            {
                                cell.avoid_click_edit = true;//!!
                                let logo_url = botoscope_default_image;
                                const logo = cell.table.data.raw_rows_data.find(obj => obj.id === "order_logo_url");
                                if (logo?.value) {
                                    logo_url = logo.value;
                                }

                                const img = Helper.create_element('img', {
                                    src: logo_url,
                                    alt: 'logo',
                                    style: "cursor: pointer",
                                    width: 200,
                                    class: ''
                                }, '', {
                                    name: 'click',
                                    callback: e => {
                                        const fileFrame = wp.media({
                                            title: botoscope_lang.select_image,
                                            library: {
                                                type: "image"
                                            },
                                            button: {
                                                text: botoscope_lang.add_image
                                            },
                                            multiple: false
                                        });

                                        fileFrame.on("select", async () => {
                                            const attachment = fileFrame.state().get("selection").first().toJSON();

                                            if (attachment) {
                                                img.src = attachment.url;
                                                Functions.message(botoscope_lang.loading, 'warning', -1);
                                                data.value = parseInt(attachment.id);
                                                cell.set_value(data.value);
                                                await Functions.edit_table_cell(data, slug);

                                                const index = cell.table.data.raw_rows_data.findIndex(obj => obj.id === "order_logo_url");
                                                cell.table.data.raw_rows_data[index].value = attachment.url;

                                                table.redraw();
                                                Functions.message(botoscope_lang.saved);
                                            }
                                        });

                                        fileFrame.open();
                                    }
                                });

                                cell.set_node(img);
                            }
                            break;

                        case 'delete_product_without_ask':
                            {
                                cell.avoid_click_edit = true;//!!

                                const select = Helper.create_html_select({1: botoscope_lang.yes, 0: botoscope_lang.no}, parseInt(data.value), {
                                    class: '',
                                }, {
                                    name: 'change',
                                    callback:
                                            async e => {
                                                Functions.message(botoscope_lang.loading, 'warning', -1);
                                                botoscope_delete_product_without_ask = data.value = parseInt(e.target.value);
                                                cell.set_value(data.value);
                                                await Functions.edit_table_cell(data, slug);
                                                Functions.message(botoscope_lang.saved);
                                            }
                                });

                                cell.set_node(select);
                            }
                            break;

                        case 'categories_per_row':
                            {
                                cell.avoid_click_edit = true;//!!
                                const per_row = parseInt(data.value);
                                if (per_row < 1 || per_row > 2) {
                                    per_row = 2;
                                }

                                cell.set_node(Helper.create_html_select({2: "2", 1: "1"}, per_row, {
                                    class: '',
                                }, {
                                    name: 'change',
                                    callback: async e => {
                                        await cell.set_value(parseInt(e.target.value));

                                        if (data.extra.id === 'disable_cart_checkout') {
                                            setTimeout(() => window.location.reload(), 999);
                                        }
                                    }
                                }));
                            }
                            break;

                        default:
                            {
                                const item = cell.table.data.raw_rows_data.find(obj => obj.id === data.extra.id);
                                const type = item.type??'';
                                switch (type) {
                                    case 'switcher':
                                        {
                                            cell.avoid_click_edit = true;//!!

                                            cell.set_node(Helper.create_html_select({1: botoscope_lang.yes, 0: botoscope_lang.no}, parseInt(data.value), {
                                                class: '',
                                            }, {
                                                name: 'change',
                                                callback: async e => {
                                                    Functions.message(botoscope_lang.loading, 'warning', -1);
                                                    await cell.set_value(parseInt(e.target.value));
                                                    Functions.message(botoscope_lang.saved);

                                                    if (data.extra.id === 'disable_cart_checkout') {
                                                        setTimeout(() => window.location.reload(), 999);
                                                    }
                                                }
                                            }));
                                        }
                                        break;
                                    case 'textarea':
                                        cell.editable_type = 'textarea';//!!
                                        break;

                                }
                            }
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

    //lets hide order logo url
    Helper.addSingleEventListener('data-table-drawn', {instance_key: table.instance_key}, e => {
        if (e.detail.data.table.instance_key === table.instance_key) {
            const logo = table.container.querySelector("data-table-row[data-id='order_logo_url']");
            if (logo) {
                table.container.querySelector("data-table-row[data-id='order_logo_url']").style.display = 'none';
            }
        }
    });

    return table;
}

function reorderLanguages(languages, selected_languages) {
    let reorderedLanguages = {};

    // First, add the selected languages ​​in the order specified in selected_languages
    selected_languages.forEach(lang => {
        if (languages[lang]) {
            reorderedLanguages[lang] = languages[lang];
        }
    });

    // Then we add the rest of the languages ​​that were not added earlier.
    for (let lang in languages) {
        if (!reorderedLanguages[lang]) {
            reorderedLanguages[lang] = languages[lang];
        }
    }

    return reorderedLanguages;
}
