import Downloads from './downloads.js';
let Functions, Helper, Table, Sidebar, Switcher;
const languages = {selected_language: null, default_language: null};
//27-11-2025
export default class Variations {
    constructor(parent_table, parent_product_id, parent_sidebar, parent_call_btn) {
        this.parent_table = parent_table;
        this.parent_product_id = parent_product_id;
        this.parent_call_btn = parent_call_btn;
        this.parent_sidebar = parent_sidebar;
        this.filter_container = null;

        this.init();
    }

    async init() {
        [Functions, Helper, Table, Sidebar, Switcher] = await loadModules();
        this.draw();
    }

    async draw(raw_rows_data = null) {
        const wrapper = this.parent_sidebar.content_container.querySelector('#botoscope_product_variations_container');
        const table_slug = 'product_variations';

        if (!raw_rows_data) {
            raw_rows_data = JSON.parse(wrapper.textContent);
        }
        wrapper.innerHTML = botoscope_lang.loading;
        wrapper.style.display = 'block';

        let child_ids = Object.values(raw_rows_data).map(item => parseInt(item.id));

        const data = {
            attributes: {
                class: table_slug,
                'data-data-table': table_slug,
                'data-order': 'desc',
                'data-order-by': 'id',
                'data-per-page': -1,
                'data-records-count': raw_rows_data.length,
                id: `the_table_${table_slug}`
            },
            header: this.format_custom_header(),
            raw_rows_data,
            format_data: function (rd) {
                const {id, is_active, ...rest} = rd;

                return {
                    ...rest,
                    is_active: {
                        //el_type: "switcher",
                        value: is_active
                    },
                    extra: {
                        editable: ['price', 'sale_price', 'sku', 'description'],
                        id: id
                    }
                };
            }
        };

        //+++

        const selected_attributes = await this.get_selected_attributes();
        //const all_attributes = await this.get_all_attributes();

        wrapper.innerHTML = '';
        const table = new Table(wrapper, data, table_slug, {
            cell_content_drawn: async data => {

                const cell = data.cell;

                switch (data.key) {

                    case 'oid':
                        {
                            if (data.extra.id > 0) {
                                const product_id = data.extra.id;
                                cell.container.classList.add('botoscope-products-id');
                                cell.draw_content(`<b>${product_id}</b>`);

                                //+++

                                if (languages.selected_language === languages.default_language) {
                                    let a = Helper.create_element('a', {
                                        href: '#',
                                        class: 'botoscope-edit-product-btn'
                                    }, '<span class="icon-edit"></span>', {
                                        name: 'click',
                                        callback: e => {
                                            this.open_sidebar(table, product_id);
                                        }
                                    });

                                    cell.append_node(a);

                                    //+++

                                    let switcher = new Switcher(this.id, parseInt(cell.table.data.rows[cell.row_index].is_active?.value), cell.container);

                                    switcher.setEvent('click', (e, input) => {
                                        data.cell.value = cell.value = input.checked ? 1 : 0;
                                        const sw_data = {...data, key: 'is_active', value: cell.value};
                                        Functions.edit_table_cell(sw_data, 'products', {}, table, true);
                                    });
                                }
                            }
                        }
                        break;

                    case 'title':
                        {
                            let combination = table.data.rows[cell.row_index].combination;
                            if (combination && Object.values(combination).length > 0) {
                                cell.clear();

                                for (const [attribute_key, terms] of Object.entries(selected_attributes.blocks)) {
                                    if (Object.values(terms).length > 0) {
                                        let prev_value = parseInt(combination[attribute_key]);
                                        const select = Helper.create_html_select({...{0: botoscope_lang.not_selected}, ...terms}, parseInt(combination[attribute_key]), {
                                            class: ''
                                        }, {
                                            name: 'change',
                                            callback: e => {
                                                e.preventDefault();

                                                const possible_combination = {...combination};
                                                possible_combination[attribute_key] = parseInt(select.value);

                                                if (is_combination_unique(possible_combination) && parseInt(select.value) > 0) {
                                                    prev_value = parseInt(select.value);
                                                    cell.refresh('combination', possible_combination);
                                                    combination = {...possible_combination};

                                                    Helper.ajax('botoscope_products_set_variation_combination', {
                                                        variation_id: data.extra.id,
                                                        combination: possible_combination
                                                    }, null, false);

                                                } else {
                                                    select.value = parseInt(prev_value);
                                                    Functions.message(botoscope_lang.wrong_combination, 'error', 3000);
                                                }

                                                return true;
                                            }
                                        });

                                        cell.append_node(select);
                                    }
                                }

                                function is_combination_unique(possible_combination) {
                                    let is_unique = true;

                                    table.data.raw_rows_data.forEach((row, index) => {
                                        if (Functions.are_objects_equal(possible_combination, row.combination)) {
                                            is_unique = false;
                                            return;
                                        }
                                    })

                                    return is_unique;
                                }
                            }
                        }
                        break;
                    case 'sale_price':
                        {
                            cell.container.classList.add('botoscope-products-sale-price');
                            const regular_price = parseFloat(cell.table.data.rows[cell.row_index].price);

                            if (parseFloat(cell.value) > regular_price) {
                                cell.value = 0;
                                cell.draw_content(0);
                                Functions.message(botoscope_lang.wrong_sale_price, 'error', 3000)
                            }

                            if (parseFloat(cell.value) > 0) {
                                cell.container.classList.add('botoscope-products-sale-price');
                            } else {
                                cell.container.classList.remove('botoscope-products-sale-price');
                            }
                        }
                        break;

                    case 'price':
                        cell.container.classList.add('botoscope-products-regular-price');
                        break;

                    case 'sku':
                        cell.container.classList.add('botoscope-products-sku');
                        break;

                    case 'description':
                        {
                            ///todo
                            cell.container.classList.add('botoscope-products-description');
                            setTimeout(() => Functions.draw_translatable_cell(table, table_slug, data, languages), 999);
                        }
                        break;

                    case 'delete':
                        {
                            let btn = Helper.create_element('a', {
                                href: '#',
                                class: 'button button-primary'
                            }, 'X', {
                                name: 'click',
                                callback: e => {
                                    if (confirm(botoscope_lang.are_you_sure)) {
                                        const child_id = data.extra.id;
                                        table.remove_row(child_id);

                                        Helper.ajax('botoscope_delete_product_child', {
                                            product_id: this.parent_product_id,
                                            child_id
                                        }, res => {
                                            table.redraw();
                                            table.records_count -= 1;
                                            this.parent_call_btn.innerText = this.parent_call_btn.innerText.replace(/\(\d+\)/, `(${table.records_count})`);
                                        });
                                    }
                                }
                            });

                            cell.set_node(btn);
                        }
                        break;
                }
            }
        });

        table.data_is_mutated = async (operation, data) => {
            switch (operation) {
                case 'edit_cell':
                    await Functions.edit_table_cell(data, 'products');
                    break;
                case 'move_col_right':
                case 'move_col_left':
                    await table.set_table_col_positions(data.positions, data.key, data.index);
                    break;
                case 'order_col_data':
                    const order_by = table.get_attribute_value('order-by');
                    const order = table.get_attribute_value('order');

                    await Helper.ajax('botoscope_products_get_variations', {
                        product_id: this.parent_product_id,
                        order_by,
                        order
                    }, res => table.redraw(res, true));

                    break;
            }

            const select = this.filter_container.querySelector('select');
            select.dispatchEvent(new Event('change'));
        };

        this.draw_filter(wrapper, table, selected_attributes);

        //+++

        const create_variation_btn = Helper.create_element('a', {
            href: '#',
            class: 'botoscope_products_add_attachments tips',
            style: 'top: 23px'
        }, botoscope_lang.add, {
            name: 'click',
            callback: (e) => {
                Helper.ajax('botoscope_products_variable_get_possible_combinations', {
                    product_id: this.parent_product_id
                }, res => {
                    if (res.combinations.length > 0) {
                        const popup = Functions.create_popup({
                            title: botoscope_lang.select_possible_variation,
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

                        popup.clear_content();
                        popup.append_content(wrapper);

                        //+++

                        const table_data = [];

                        res.combinations.forEach(combination => {
                            const titleParts = [];

                            for (const [key, value] of Object.entries(combination)) {
                                const attributeTitle = res.info[key]?.title || key;
                                const termValue = res.info[key]?.terms[value] || value;
                                titleParts.push(`${attributeTitle}: ${termValue}`);
                            }

                            const id = JSON.stringify(combination);

                            table_data.push({
                                id,
                                oid: id,
                                title: titleParts.join(' | '),
                                combination
                            });
                        });

                        //+++

                        const slug = 'possible_variations';

                        const table_of_possible_variations = new Table(wrapper, {
                            attributes: {
                                class: slug,
                                'data-data-table': slug,
                                'data-order': 'asc',
                                'data-order-by': 'id',
                                'data-per-page': -1,
                                'data-records-count': table_data.length,
                                id: `the_table_${slug}`
                            },
                            header: [
                                {
                                    value: botoscope_lang.combination,
                                    width: '80%',
                                    key: 'title'
                                },
                                {
                                    value: botoscope_lang.select,
                                    width: '20%',
                                    key: 'select',
                                    callback: (h_cell) => {
                                        h_cell.set_node(Helper.create_element('a', {
                                            href: '#',
                                            class: 'button button-primary'
                                        }, botoscope_lang.add_all, {
                                            name: 'click',
                                            callback: async e => {
                                                const buttons = wrapper.querySelectorAll('.botoscope-select-variation');
                                                if (buttons.length > 0) {
                                                    for (let i = 0; i < table_of_possible_variations.data.rows.length; i++) {
                                                        const combination = table_of_possible_variations.data.rows[i].oid;

                                                        const rowCells = table_of_possible_variations.rows[i].cells;
                                                        const selectCell = Object.values(rowCells).find(c => c.key === 'select');

                                                        if (selectCell) {
                                                            await this.create_single_variation(table, selectCell, combination, null);
                                                            await new Promise(r => setTimeout(r, 500));//for safe timing of server request
                                                        }
                                                    }

                                                    if (this.redraw_data) {
                                                        this.draw(this.redraw_data);
                                                        popup.close();
                                                    }
                                                }
                                            }
                                        }));
                                    }
                                }
                            ],
                            raw_rows_data: table_data,
                            format_data: function (rd) {
                                const {id, is_active, ...rest} = rd;

                                return {
                                    ...rest,
                                    is_active: {
                                        //el_type: "switcher",
                                        value: is_active
                                    },
                                    extra: {
                                        editable: [],
                                        id: id
                                    }
                                };
                            }
                        }, slug, {
                            cell_content_drawn: async data => {
                                const cell = data.cell;

                                switch (data.key) {

                                    case 'select':

                                        const btn = Helper.create_element('a', {
                                            href: '#',
                                            class: 'botoscope-select-variation'
                                        }, '<span class="icon-play"></span>', {
                                            name: 'click',
                                            callback: async e => {
                                                const combination = table_of_possible_variations.data.rows[cell.row_index].oid;
                                                await this.create_single_variation(table, cell, combination, e);
                                            }
                                        });

                                        cell.set_node(btn);
                                        break;
                                }
                            }
                        });

                        this.draw_filter(wrapper, table_of_possible_variations, selected_attributes);

                    } else {
                        alert(botoscope_lang.no_free_possible_variations);
                    }
                });

            }
        });

        this.parent_sidebar.content_container.appendChild(create_variation_btn);
    }

    async open_sidebar(table, product_id) {
        let sidebar = new Sidebar('products', product_id, `${botoscope_lang.variation} #${product_id}`, '100%');
        sidebar.set_content('single-product-variation', {}, function (data_table, row_id, formProps) {
            Functions.message(botoscope_lang.loading, 'warning', -1);


            const editorIds = ['botoscope-variation-description'];
            const savedContents = {}; // Save content here

            // FIRST PASS - collecting content
            editorIds.forEach(editorId => {
                const editorWrapper = document.getElementById(`wp-${editorId}-wrap`);

                if (editorWrapper) {
                    const isCodeMode = editorWrapper.classList.contains('html-active');

                    if (isCodeMode) {
                        const textarea = editorWrapper.querySelector(`textarea#${editorId}`);

                        if (textarea && textarea.offsetParent !== null) {
                            savedContents[editorId] = textarea.value;
                            //console.log(`Collected from ${editorId} (Code mode):`, savedContents[editorId].length);
                        }
                    } else {
                        const editor = tinymce.get(editorId);
                        if (editor) {
                            savedContents[editorId] = editor.getContent();
                            //console.log(`Collected from ${editorId} (Visual mode):`, savedContents[editorId].length);
                        }
                    }
                }
            });

            // SECOND PASS - install and save
            editorIds.forEach(editorId => {
                const editor = tinymce.get(editorId);

                if (editor && savedContents[editorId] !== undefined) {
                    editor.setContent(savedContents[editorId]);
                    editor.save();

                    // Updating textarea directly
                    const textarea = document.getElementById(editorId);
                    if (textarea) {
                        textarea.value = savedContents[editorId];
                    }

                    //console.log(`Saved to ${editorId}:`, savedContents[editorId].length);
                }
            });

            //+++

            Helper.ajax('botoscope_edit_row', {
                what: data_table,
                id: row_id,
                data: {type: formProps.type}
            }, xxx => {
                delete formProps.type;
                //fix to save product type correctly
                Helper.ajax('botoscope_edit_row', {
                    what: data_table,
                    id: row_id,
                    data: formProps
                }, res => {
                    if (typeof res === 'object') {
                        if (res.success) {
                            Functions.message(botoscope_lang.saved);
                            sidebar.after_save(res);
                        } else {
                            Functions.message(res.data, 'error', 3000);
                        }
                    } else {
                        Functions.message(botoscope_lang.saved);
                        sidebar.after_save(res);
                    }
                }, true);

            }, false);
        });


        sidebar.after_set = async () => {
            const textarea = sidebar.content_container.querySelector('#botoscope-product_variation-description');

            sidebar.content_container.querySelector('#botoscope_product_variation_description_ai').addEventListener('click', e => {
                e.preventDefault();
                Functions.text_by_ai(textarea, 'generate_description');
                return false;
            });

            sidebar.content_container.querySelector('#botoscope_product_variation_grammar_ai').addEventListener('click', e => {
                e.preventDefault();
                Functions.text_by_ai(textarea, 'fix_description');
                return false;
            });


            //media
            const media_container = sidebar.content_container.querySelector('#botoscope-product_variation-media-container');
            const media_input_container = sidebar.content_container.querySelector('#botoscope-product_variation-media-value-container');
            const media_input = sidebar.content_container.querySelector('#botoscope-product_variation-media-value');
            const selected_medias = JSON.parse(media_input_container.innerText);

            let ul = await Functions.draw_product_media(table, product_id, selected_medias, function (ids) {
                media_input.value = ids.join(',');
            });

            media_container.appendChild(ul);
            Functions.pause_videos(media_container);

            let a = await Functions.draw_media_add_button(product_id, ul, table, function (ids) {
                const all_ids = media_input.value.split(',').concat(ids).join(',');
                media_input.value = all_ids;
                Helper.ajax('botoscope_products_get_medias', {
                    ids: all_ids
                }, async function (res) {
                    ul.remove();

                    ul = await Functions.draw_product_media(table, product_id, res, function (ids) {
                        media_input.value = ids.join(',');
                    });

                    media_container.appendChild(ul);
                    Functions.pause_videos(media_container);
                });
            });

            media_container.appendChild(a);

            //+++

            const product_type_select = sidebar.content_container.querySelector('#botoscope_product_variation_type');
            const product_files_btn = sidebar.content_container.querySelector('#botoscope_product_variation_files');

            product_type_select.addEventListener('change', e => {
                e.preventDefault();

                const value = e.target.value;
                if (['variation_virtual_downloadable', 'variation_media_casting'].includes(value)) {
                    product_files_btn.setAttribute('style', 'display: flex !important');
                } else {
                    product_files_btn.setAttribute('style', 'display: none !important');
                }

                return true;
            });

            //files for simple product
            product_files_btn.addEventListener('click', e => {
                e.preventDefault();

                const sidebar = new Sidebar('products', product_id, `${botoscope_lang.product_downloads} #${product_id}`, botoscope_is_mobile ? '100%' : '70%');
                sidebar.set_content('single-product-downloads');

                sidebar.after_set = async () => {
                    new Downloads(table, product_id, sidebar, product_files_btn);
                };

                return false;
            });


            Functions.tiny_textarea('botoscope-variation-description', 'bold italic underline strikethrough');
        };

        sidebar.after_save = async () => {
            Helper.ajax('botoscope_products_get_variations', {
                product_id: this.parent_product_id
            }, res => this.draw(res));
        };
    }

    filter(table, filter_value) {
        if (table.rows.length > 0) {
            table.rows.forEach(row => {
                let row_combination = row?.data?.combination;
                //console.log(filter_value);
                //console.log(row_combination);

                if (!row_combination) {
                    return;
                }

                let matches = true;

                for (let key in filter_value) {
                    if (filter_value[key] !== row_combination[key]) {
                        matches = false;
                        break;
                    }
                }

                if (matches) {
                    row.display(1);
                } else {
                    row.display(0);
                }
            });
        }
    }

    draw_filter(wrapper, table, attributes) {
        if (Object.values(attributes.taxonomies).length > 0) {
            this.filter_container = Helper.create_element('div', {
                class: 'botoscope-product-variations-filter'
            });

            let filter_value = {};

            for (const [attribute_key, terms] of Object.entries(attributes.blocks)) {
                if (Object.values(terms).length > 0) {
                    const merged = {...{0: attributes.taxonomies[attribute_key]}, ...terms};
                    const select = Helper.create_html_select(merged, 0, {
                        class: ''
                    }, {
                        name: 'change',
                        callback: e => {
                            const id = parseInt(select.value);
                            filter_value[attribute_key] = id;
                            if (id === 0) {
                                delete filter_value[attribute_key];
                            }
                            this.filter(table, filter_value);
                        }
                    });
                    this.filter_container.appendChild(select);
                }
            }

            const reset_btn = Helper.create_element('a', {
                href: '#',
                class: 'button bs-reset-variations'
            }, 'X', {
                name: 'click',
                callback: e => {
                    e.preventDefault();
                    filter_value = {};
                    this.filter_container.querySelectorAll('select').forEach(select => select.value = 0);
                    this.filter(table, filter_value);
                    return false;
                }
            });

            this.filter_container.appendChild(reset_btn);
            wrapper.prepend(this.filter_container);
        }
    }

    async get_selected_attributes() {
        const res = await Helper.ajax('botoscope_product_get_allowed_attributes', {
            product_id: this.parent_product_id
        });

        return res;
    }

    async get_all_attributes() {
        return await Helper.ajax('botoscope_product_get_all_attributes');
    }

    async create_single_variation(table, cell, combination, e) {
        Functions.message(botoscope_lang.loading, 'warning', -1);

        cell.table.rows[cell.row_index].delete();
        table.records_count += 1;
        this.parent_call_btn.innerText = this.parent_call_btn.innerText.replace(/\(\d+\)/, `(${table.records_count})`);
        this.redraw_data = null;

        const res = await Helper.ajax('botoscope_product_create_variation', {
            product_id: this.parent_product_id,
            combination: JSON.parse(combination)
        });

        Functions.message(botoscope_lang.saved);
        if (e && e.isTrusted) {
            this.draw(res);
        } else {
            this.redraw_data = res;
        }

        return res;
    }

    format_custom_header(type = 1) {

        let header = [];

        switch (type) {
            case 1:

                header = [
                    {
                        value: 'ID',
                        width: '10%',
                        key: 'oid',
                        order: 'desc'
                    },
                    {
                        value: botoscope_lang.combination,
                        width: '50%',
                        key: 'title'
                    },
                    {
                        value: botoscope_lang.price,
                        width: '10%',
                        key: 'price',
                        order: 'desc'
                    },
                    {
                        value: botoscope_lang.sale,
                        width: '10%',
                        key: 'sale_price',
                        order: 'desc'
                    },
                    {
                        value: botoscope_lang.sku,
                        width: '10%',
                        key: 'sku',
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
                        width: '40%',
                        key: 'title'
                    },
                    {
                        value: botoscope_lang.description,
                        width: '75%',
                        key: 'description'
                    }
                ];

                break;
        }

        return header;
    }
}

async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js'),
        import(botoscope_url + 'assets/js/lib/sidebar.js'),
        import(botoscope_url + 'assets/js/table/ae/switcher.js')
    ]);

    return modules.map(mod => mod.default || mod);
}


