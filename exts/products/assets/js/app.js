import Variations from './variations.js';
import Downloads from './downloads.js';
import BookingSlots from '../../../booking/assets/js/booking-slots.js';
import Grouped from './grouped.js';
import Groupes from './groupes.js';
import Meta from './meta.js';
const languages = {selected_language: null, default_language: null};
let Functions, Helper, Table, SelectM23, Sidebar, Switcher, Calendar, CalendarSelector;
let products_order_by = 'menu_order';
let products_order = 'asc';
//20-04-2026
export default async function init_products() {
    [Functions, Helper, Table, SelectM23, Sidebar, Switcher, Calendar, CalendarSelector] = await loadModules();
    const slug = 'products';
    const wrapper = document.getElementById(`botoscope-${slug}-w`);
    const product_types = JSON.parse(document.getElementById('botoscope-product-types').innerHTML);

    const data = {
        attributes: {
            class: slug,
            'data-data-table': slug,
            'data-order': products_order,
            'data-order-by': products_order_by,
            'data-per-page': parseInt(wrapper.dataset.perPage),
            'data-records-count': parseInt(wrapper.dataset.itemsCount),
            id: `the_table_${slug}`
        },
        header: format_custom_header(),
        raw_rows_data: JSON.parse(wrapper.textContent),
        format_data: function (rd) {
            const {id, is_hidden, ...rest} = rd;

            return {
                ...rest,
                is_hidden: {
                    //el_type: "switcher",
                    value: is_hidden
                },
                extra: {
                    editable: ['title', 'price', 'sale_price', 'sku', 'description'],
                    editable_types: {description: 'textarea'},
                    id: id
                }
            };
        }
    };

    //+++

    let categoriesTerms = await Functions.load_taxonomies_terms();

    Helper.addSingleEventListener('botoscope-taxonomies-updated', {instance_key: -1}, async e => {
        categoriesTerms = await Functions.load_taxonomies_terms();
        table.redraw();
    });

    function get_categories_terms() {
        return categoriesTerms;
    }

    //+++

    wrapper.innerHTML = '';

    const table = new Table(wrapper, data, slug, {
        cell_content_drawn: async data => {

            const cell = data.cell;

            switch (data.key) {

                case 'oid':
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
                                callback: function (e) {
                                    open_sidebar(table, product_id);
                                }
                            });

                            cell.append_node(a);

                            //+++

                            let switcher = new Switcher(this.id, !Boolean(parseInt(cell.table.data.rows[cell.row_index].is_hidden?.value)), cell.container);

                            switcher.setEvent('click', async (e, input) => {
                                data.cell.value = cell.value = input.checked ? 0 : 1;
                                const sw_data = {...data, key: 'is_hidden', value: cell.value};
                                await Functions.edit_table_cell(sw_data, slug, {}, table, true);
                                Helper.cast('botoscope-draw-progress-bar', {});
                            });

                            //+++

                            if (botoscope_no_bot) {
                                cell.append_node(Helper.create_element('a', {
                                    href: `${botoscope_site_url}?p=${product_id}`,
                                    target: '_blank',
                                    class: 'botoscope-edit-product-btn botoscope-edit-product-btn-eye'
                                }, '<span class="icon-eye"></span>'));
                            } else {
                                const btn = Helper.create_element('a', {
                                    href: `https://t.me/${botoscope_bot_name}?start=product_${product_id}`,
                                    target: '_blank',
                                    class: 'botoscope-edit-product-btn botoscope-edit-product-btn-eye'
                                }, '<span class="icon-eye"></span>', {
                                    name: 'click',
                                    callback: (e) => {
                                        e.preventDefault();

                                        Helper.ajax('botoscope_is_product_published', {
                                            product_id
                                        }, async function (res) {
                                            if (parseInt(res)) {
                                                window.open(btn.href, '_blank');
                                            } else {
                                                Functions.message(botoscope_lang.make_product_published, 'error', 3000)
                                            }
                                        });

                                        return false;
                                    },
                                });
                                cell.append_node(btn);
                            }
                        }
                    }
                    break;

                case 'title':
                    {
                        cell.container.classList.add('botoscope-products-title');
                        setTimeout(() => Functions.draw_translatable_cell(table, slug, data, languages), 999);
                        const product_id = data.extra.id;

                        //+++

                        if (languages.selected_language === languages.default_language) {
                            const selected_terms = cell.table.data.rows[cell.row_index]?.category?.sort((a, b) => a - b);

                            if (selected_terms) {
                                const selected_titles = Functions.flat_taxonomy_terms(get_categories_terms())
                                        .filter(item => selected_terms.includes(item.id))
                                        .map(item => item.title.replace(/^[-\s]+/, ''));

                                if (selected_titles.length > 0) {
                                    const c = Helper.create_element('div', {
                                        class: 'botoscope-cell-tag-container'
                                    });

                                    selected_titles.forEach(t => {
                                        c.appendChild(Helper.create_element('span', {
                                            class: 'botoscope-cell-tag'
                                        }, t));
                                    });

                                    cell.append_node(c);
                                }
                            }

                            //+++

                            let stock;

                            if (parseInt(cell.table.data.rows[cell.row_index].is_in_stock)) {
                                stock = Helper.create_element('span', {
                                    class: 'botoscope-cell-tag-instock'
                                }, botoscope_lang.in_stock);
                            } else {
                                stock = Helper.create_element('span', {
                                    class: 'botoscope-cell-tag-instock botoscope-cell-tag-instock-out'
                                }, botoscope_lang.out_of_stock);
                            }

                            cell.append_node(stock);

                            //+++

                            const botoscope_type = cell.table.data.rows[cell.row_index].botoscope_type;

                            cell.append_node(Helper.create_element('span', {
                                class: `botoscope-cell-tag-type botoscope-cell-tag-type-${botoscope_type}`
                            }, product_types[botoscope_type] || botoscope_type));

                            //+++
                            let icon = '';
                            if (parseInt(cell.table.data.rows[cell.row_index].is_in_group_of)) {
                                icon = '✔️';
                            }

                            if (!botoscope_no_bot) {
                                if (!['grouped', 'external'].includes(botoscope_type)) {
                                    const group_btn = cell.append_node(Helper.create_element('a', {
                                        href: '#',
                                        class: `botoscope-cell-tag-type botoscope-cell-btn-append-to-group`,
                                    }, `${icon} ${botoscope_lang.append_to_group}`, {
                                        name: 'click',
                                        callback: async e => {
                                            e.preventDefault();

                                            const sidebar = new Sidebar('products', product_id, `${botoscope_lang.groups_for} #${product_id}`, botoscope_is_mobile ? '100%' : '70%');
                                            sidebar.set_content('single-product-groups');

                                            sidebar.after_set = async () => {
                                                new Groupes(table, product_id, sidebar, group_btn);
                                            };

                                            return false;
                                        }
                                    }));
                                }
                            }

                        }
                    }
                    break;

                case 'description':
                    {
                        cell.container.classList.add('botoscope-products-description');
                        setTimeout(() => Functions.draw_translatable_cell(table, slug, data, languages), 999);
                    }
                    break;

                case 'sale_price':
                    {
                        if (cell.table.data.rows[cell.row_index].type === 'variable') {
                            cell.avoid_click_edit = true;
                            cell.draw_content('🚫');
                        } else {

                            cell.container.classList.add('botoscope-products-sale-price');
                            const regular_price = parseFloat(cell.table.data.rows[cell.row_index].price);

                            if (parseFloat(cell.value) > regular_price) {
                                cell.value = 0;
                                cell.draw_content(0);
                                cell.refresh('sale_price', 0);
                                Functions.message(botoscope_lang.wrong_sale_price, 'error', 3000);
                            }

                            if (regular_price <= 0) {
                                cell.draw_content(0);
                                cell.refresh('sale_price', 0);
                            }

                            if (parseFloat(cell.value) > 0) {
                                cell.container.classList.add('botoscope-products-sale-price');
                            } else {
                                cell.container.classList.remove('botoscope-products-sale-price');
                            }
                        }
                    }
                    break;

                case 'price':
                    {
                        if (cell.table.data.rows[cell.row_index].type === 'variable') {
                            cell.avoid_click_edit = true;
                            cell.draw_content('🚫');
                        } else {
                            cell.container.classList.add('botoscope-products-regular-price');
                        }
                    }
                    break;

                case 'sku':
                    {
                        cell.container.classList.add('botoscope-products-sku');
                    }
                    break;

                case 'media':
                    {
                        const ul = await Functions.draw_product_media(table, data.extra.id, cell.value, null, cell);
                        cell.append_node(ul);
                        Functions.pause_videos(ul);

                        const a = await Functions.draw_media_add_button(data.extra.id, ul, table);
                        cell.append_node(a);
                    }
                    break;


                case 'category':
                    {
                        const select = await Functions.draw_terms_select(get_categories_terms(), Functions.get_disabled_terms(get_categories_terms()), cell.value, function () {
                            data.value = sm23.selected_values.join(',');
                            data.cell.value = sm23.selected_values.join(',');
                            Functions.edit_table_cell(data, slug, {}, table, true);
                        });
                        cell.clear();
                        cell.set_node(select);
                        const sm23 = new SelectM23(select, false, botoscope_lang.terms);
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

                        async function call_delete_cell(btn, row_id) {
                            if (botoscope_delete_product_without_ask ? true : confirm(botoscope_lang.are_you_sure)) {
                                await Helper.ajax('botoscope_delete_row', {
                                    what: slug,
                                    row_id
                                }, null, false);

                                table.remove_row(row_id);
                            }
                        }
                    }
                    break;
            }
        }
    });

    //+++

    const search_container = Helper.create_element('div', {
        class: 'botoscope-products-search-container'
    }, '');

    const search_input = Helper.create_element('input', {
        type: 'search',
        class: 'botoscope-products-search-input',
        placeholder: botoscope_lang.search_by_title_or_sku
    }, '', {
        name: 'keyup',
        callback: function (event) {
            if (event.key === "Enter") {
                do_search();
            }

            return true;
        }
    });

    let cross = Functions.create_element('a', {
        href: '#',
        class: 'botoscope_search_cross',
    }, 'x', {
        name: 'click',
        callback: e => {
            search_input.value = '';
            cross.style.display = 'none';
            do_search();
        }
    });

    cross.style.display = 'none';

    function do_search() {
        const value = search_input.value.trim();
        table.search.title = value;
        if (value.length > 0) {
            cross.style.display = 'block';
        }
        table.set_page(0);
    }

    search_container.prepend(search_input);
    search_container.prepend(cross);
    wrapper.prepend(search_container);

    //+++

    const s_options = {
        '': botoscope_lang.select_prod_type,
        simple: botoscope_lang.simple,
        ...(is_botoscope_connected && {
                botoscope_simple_virtual: botoscope_lang.simple_virtual,
                botoscope_simple_virtual_downloadable: botoscope_lang.simple_virtual_downloadable,
                botoscope_simple_media_casting: botoscope_lang.simple_media_casting,
        }),
        external: botoscope_lang.external,
        grouped: botoscope_lang.grouped,
        variable: botoscope_lang.variable
    };

    if (botoscope_no_bot) {
        delete s_options.botoscope_simple_media_casting;
    }

    const product_types_select = Helper.create_html_select(s_options, data.value, {
        class: '',
    }, {
        name: 'change',
        callback: async e => {
            table.search.product_type = e.target.value;
            table.set_page(0);
        }
    });

    search_container.append(product_types_select);

    //+++

    function generateCategoryTree(categories, prefix = '', level = 0) {
        let result = [];

        for (let category of categories) {
            // Add current category
            result.push({
                id: category.id,
                title: `${prefix}${category.title}`,
                level: level
            });
            // If there are child categories, add them immediately after the parent
            if (category.children && category.children.length > 0) {
                const childResults = generateCategoryTree(
                        category.children,
                        `${prefix}--`,
                        level + 1
                        );
                result = result.concat(childResults);
            }
        }

        return result;
    }

    //+++

    const cat_select = Helper.create_html_select([...[{id: 0, title: botoscope_lang.select_prod_category}], ...generateCategoryTree(categoriesTerms)], data.value, {
        //class: 'selectpicker',
        //'data-live-search': 'true',
        style: 'flex: 1; width: 100%;'
    }, {
        name: 'change',
        callback: async e => {
            table.search.product_category = e.target.value;
            table.set_page(0);
        }
    });
    search_container.append(cat_select);

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
            case 'order_col_data':
                products_order_by = data.key;
                if (products_order_by === 'oid') {
                    products_order_by = 'id';
                }
                products_order = data.order;
                table.set_page(0);
                break;
        }
    };

    //+++

    table.set_page = async (page_num) => {
        await Functions.reload_table_data(table, page_num, slug);
        Functions.pause_videos(table.container);
    };

    //+++
    //create new product
    let botoscope_create_products_flag = false;//to avoid click twice
    document.getElementById('botoscope_create_products').addEventListener('click', async e => {
        if (botoscope_create_products_flag) {
            return;
        }
        botoscope_create_products_flag = true;
        const res = await Functions.add_table_row(table, slug, {}, async res => table.set_page(0));
        open_sidebar(table, res.id);
        botoscope_create_products_flag = false;
    });

    //create dummy products
    document.getElementById('botoscope_create_dummy_products').addEventListener('click', async e => {
        let count = prompt(botoscope_lang.enter_products_count_to_create, 1);
        if (parseInt(count)) {
            Functions.message(botoscope_lang.loading, 'warning', -1);
            await Helper.ajax('botoscope_create_dummy_products', {count}, a => table.set_page(0), false);
            Functions.message(botoscope_lang.done);
        }
    });

    table.do_after_redraw = () => {
        Functions.pause_videos(table.container);
        jQuery(".botoscope-admin-preloader").fadeOut("slow");//!!
    };

    //for custom actions
    table.rebuild = async (options) => {
        await table.redraw();
        Functions.pause_videos(table.container);
    };

    //+++

    table.format_custom_header = format_custom_header;
    const language_selector = Functions.init_app_language_functionality(table, slug, languages);
    if (languages.selected_language !== languages.default_language) {
        language_selector.dispatchEvent(new Event('change'));
    }

    //+++

    const ps = new PerfectScrollbar(table.container, {suppressScrollY: true});

    function updateScrollbarPosition() {
        const viewportHeight = window.innerHeight;
        const scrollY = window.scrollY || window.pageYOffset;
        let thumb = null;
        if (thumb = document.querySelector('.ps__thumb-x')) {
            thumb.style.top = `${scrollY + (viewportHeight - 100)}px`;
        }
    }

    window.addEventListener('scroll', updateScrollbarPosition);
    window.addEventListener('resize', updateScrollbarPosition);
    updateScrollbarPosition();

    //+++

    Helper.addSingleEventListener('botoscope-open-product-sidebar', {instance_key: 1}, e => {
        const product_id = parseInt(e.detail.data.product_id);
        open_sidebar(table, product_id);
    });


    Helper.addSingleEventListener('botoscope-draw-progress-bar', {instance_key: -1}, async e => {
        Helper.ajax('botoscope_draw_progress_bar', {}, function (html) {
            document.getElementById('botoscope_progress_wrap_container').innerHTML = html;
        }, false);
    });

    //+++
    if (document.getElementById('botoscope-products-all-visible')) {
        document.getElementById('botoscope-products-all-visible').addEventListener('click', async e => {
            e.preventDefault();

            if (confirm(botoscope_lang.make_products_visible)) {
                Functions.message(botoscope_lang.loading, 'warning', -1);
                await Helper.ajax('botoscope_products_make_all_visible', {}, a => {
                    Functions.message(botoscope_lang.saved);
                    window.location.reload();
                }, false);
            }

            return false;
        });

        document.getElementById('botoscope-products-all-hidden').addEventListener('click', async e => {
            e.preventDefault();

            if (confirm(botoscope_lang.make_products_hidden)) {
                Functions.message(botoscope_lang.loading, 'warning', -1);
                await Helper.ajax('botoscope_products_make_all_hidden', {}, a => {
                    Functions.message(botoscope_lang.saved);
                    window.location.reload();
                }, false);
            }

            return false;
        });
    }
    //+++

    return table;
}

async function open_sidebar(table, product_id) {
    //table.data.rows[cell.row_index].title
    let sidebar = new Sidebar('products', product_id, `${botoscope_lang.product} #${product_id}`, botoscope_is_mobile ? '100%' : '97%');
    sidebar.set_content('single-product', {}, function (data_table, row_id, formProps) {
        Functions.message(botoscope_lang.loading, 'warning', -1);


        const editorIds = ['botoscope-product-description', 'botoscope-product-details'];
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

        // Remove triggerSave - it can overwrite
        // if (typeof tinymce !== 'undefined') {
        //     tinymce.triggerSave();
        // }

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
        //categories
        const input = sidebar.content_container.querySelector('#botoscope-single-product-categories-value');
        const selected_terms = input.value.split(',');
        let categoriesTerms = await Functions.load_taxonomies_terms();
        const select = await Functions.draw_terms_select(categoriesTerms, Functions.get_disabled_terms(categoriesTerms), selected_terms, function () {
            input.value = sm23.selected_values.join(',');
        });
        sidebar.content_container.querySelector('#botoscope-single-product-categories-container').appendChild(select);
        const sm23 = new SelectM23(select, false, botoscope_lang.select_category);

        //media
        const media_container = sidebar.content_container.querySelector('#botoscope-single-product-media-container');
        const media_input_container = sidebar.content_container.querySelector('#botoscope-single-product-media-value-container');
        const media_input = sidebar.content_container.querySelector('#botoscope-single-product-media-value');
        const selected_medias = JSON.parse(media_input_container.innerText);

        //+++

        let ul = await Functions.draw_product_media(table, product_id, selected_medias, function (ids) {
            media_input.value = ids.join(',');
        });

        media_container.appendChild(ul);
        Functions.pause_videos(media_container);

        //+++

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

        const textarea = sidebar.content_container.querySelector('#botoscope-product-description');

        sidebar.content_container.querySelector('#botoscope_product_description_ai').addEventListener('click', async e => {
            e.preventDefault();
            Functions.ai_description('generate_description');
            return false;
        });

        sidebar.content_container.querySelector('#botoscope_product_grammar_ai').addEventListener('click', async e => {
            e.preventDefault();
            Functions.ai_description('fix_description');
            return false;
        });

        //+++

        const product_type_select = sidebar.content_container.querySelector('#botoscope_product_type');
        const product_files_btn = sidebar.content_container.querySelector('#botoscope_product_files');
        const product_botoscope_access_days = sidebar.content_container.querySelector('#botoscope_access_days');
        const product_external_link_input = sidebar.content_container.querySelector('#product_external_link');
        const product_products_btn = sidebar.content_container.querySelector('#botoscope_product_products');
        const product_variations_btn = sidebar.content_container.querySelector('#botoscope_product_variations');
        const product_booking_slots_btn = sidebar.content_container.querySelector('#botoscope_product_booking_slots_btn');
        const product_variable_section = sidebar.content_container.querySelector('#botoscope-product-variable-section');

        product_type_select.addEventListener('change', e => {
            e.preventDefault();

            const value = e.target.value;

            if (['botoscope_simple_virtual_downloadable', 'botoscope_simple_media_casting'].includes(value)) {
                product_files_btn.style.display = 'inline-block';
                product_botoscope_access_days.parentNode.style.display = 'inline-block';
            } else {
                product_files_btn.style.display = 'none';
                product_botoscope_access_days.parentNode.style.display = 'none';
            }

            if (['external'].includes(value)) {
                product_external_link_input.parentNode.style.display = 'block';
            } else {
                product_external_link_input.parentNode.style.display = 'none';
            }

            if (product_booking_slots_btn) {
                if (['botoscope_simple_virtual'].includes(value)) {
                    product_booking_slots_btn.style.display = 'inline-block';
                } else {
                    product_booking_slots_btn.style.display = 'none';
                }
            }

            if (['grouped'].includes(value)) {
                product_products_btn.style.display = 'block';
                document.querySelectorAll('.botoscope-products-product-type-grouped').forEach(element => {
                    element.style.removeProperty('display');
                });
            } else {
                product_products_btn.style.display = 'none';
                document.querySelectorAll('.botoscope-products-product-type-grouped').forEach(element => {
                    element.style.display = 'none';
                });
            }

            if (['variable'].includes(value)) {
                product_variations_btn.style.display = 'inline-block';
                document.querySelectorAll('.botoscope-product-price-container').forEach(element => {
                    element.style.display = 'none';
                });
                product_variable_section.style.display = 'block';
            } else {
                product_variations_btn.style.display = 'none';
                document.querySelectorAll('.botoscope-product-price-container').forEach(element => {
                    element.style.removeProperty('display');
                });
                product_variable_section.style.display = 'none';
            }


            if (['simple', 'variable', 'variation_physical'].includes(value)) {
                document.querySelectorAll('.botoscope_manage_stock').forEach(element => {
                    element.style.display = 'block';
                });
            } else {
                document.querySelectorAll('.botoscope_manage_stock').forEach(element => {
                    element.style.display = 'none';
                });
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

        //for virtual product
        if (product_booking_slots_btn) {
            product_booking_slots_btn.addEventListener('click', e => {
                e.preventDefault();

                const sidebar = new Sidebar('products', product_id, `${botoscope_lang.booking_slots_for} #${product_id}`, botoscope_is_mobile ? '100%' : '70%');
                sidebar.set_content('single-product-booking-slots');

                sidebar.after_set = async () => {
                    const bs = new BookingSlots(table, product_id, sidebar, product_products_btn);
                    bs.draw_weekdays_buttons();
                    bs.draw();
                };

                return false;
            });
        }

        //grouped product
        product_products_btn.addEventListener('click', e => {
            e.preventDefault();

            const sidebar = new Sidebar('products', product_id, `${botoscope_lang.products_for} #${product_id}`, botoscope_is_mobile ? '100%' : '70%');
            sidebar.set_content('single-product-products');

            sidebar.after_set = async () => {
                new Grouped(table, product_id, sidebar, product_products_btn);
            };

            return false;
        });

        //variable product
        product_variations_btn.addEventListener('click', async e => {
            e.preventDefault();

            Functions.message(botoscope_lang.loading, 'warning', -1);

            const product_data = await Helper.ajax('botoscope_products_get_single_product', {
                product_id
            }, d => {
                Functions.message(botoscope_lang.loading, 'warning', 1);
            }, true);

            let can = false;

            if (product_data.attributes.length > 0 && product_data.attributes_terms.length > 0) {
                can = true;
            }

            if (!can) {
                Functions.message(botoscope_lang.select_attributes_and_terms, 'warning', 3000);
                return;
            }

            const sidebar = new Sidebar('products', product_id, `${botoscope_lang.variations_for} #${product_id}`, botoscope_is_mobile ? '100%' : '85%');
            sidebar.set_content('single-product-variations');

            sidebar.after_set = async () => {
                new Variations(table, product_id, sidebar, product_variations_btn);
            };

            return false;
        });

        //for variable product
        draw_attributes_section(product_id);

        //+++
        const calendar_input = document.getElementById('botoscope-single-product-publish_date-value');
        const calendar_container = Helper.create_element('div', {
            class: 'calendar23-selector',
            'data-name': 'publish_date',
            'data-date': parseInt(calendar_input.value)
        }, '');


        const additional = {
            show_time: true
        };

        additional.month_names = botoscope_lang.month_names;
        additional.month_names_short = botoscope_lang.month_names_short;
        additional.day_names = botoscope_lang.day_names;

        const calendar = new CalendarSelector(calendar_container, 0, parseInt(calendar_input.value), '', additional);

        calendar.selected = () => {
            calendar_input.value = calendar.unix_time_stamp;
        };

        document.getElementById('publish_date').appendChild(calendar_container);

        //+++
        //const product_details = sidebar.content_container.querySelector('#botoscope-product-details');
        Functions.tiny_textarea('botoscope-product-details');
        Functions.tiny_textarea('botoscope-product-description', 'bold italic underline strikethrough');

        //+++
        //Audio button file selector
        document.querySelectorAll(".uploadAudioButton").forEach(function (button) {
            button.addEventListener("click", function (event) {
                event.preventDefault();

                const fileFrame = wp.media({
                    title: botoscope_lang.select_audio,
                    library: {
                        type: "audio"
                    },
                    button: {
                        text: botoscope_lang.add_audio
                    },
                    multiple: false
                });

                fileFrame.on("select", function () {
                    let attachment = fileFrame.state().get("selection").first().toJSON();

                    const container = button.closest('div');
                    const input = container.querySelector('input[type="text"]');

                    if (input) {
                        input.value = attachment.url;
                    }
                });

                fileFrame.open();
            });
        });

        //+++
        new Meta(product_id, document.getElementById('botoscope-single-product-meta'));
    };

    sidebar.after_save = async () => {
        await table.set_page(table.pagination_1.current_page);
        /*
         * its not need here as synhronozation is doing trought hook woocommerce_update_product
         Helper.ajax('botoscope_update_product_bot_cache', {
         product_id
         }, null, false);
         */
    };

    sidebar.close = () => {
        //rewrite sidebar close to close all if opened
        const openSidebars = document.querySelectorAll('.botoscope-static-sidebar-opened');

        openSidebars.forEach(sidebar => {
            sidebar.classList.remove('botoscope-static-sidebar-opened');

            if (typeof sidebar.transparent === 'function') {
                sidebar.transparent(false);
            }

            setTimeout(() => {
                sidebar.remove();
            }, 777);
        });
    };
}


async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js'),
        import(botoscope_url + 'assets/js/lib/selectm-23.js'),
        import(botoscope_url + 'assets/js/lib/sidebar.js'),
        import(botoscope_url + 'assets/js/table/ae/switcher.js'),
        import(botoscope_url + 'assets/js/lib/calendar23.js'),
        import(botoscope_url + 'assets/js/lib/calendar23-selector.js')
    ]);

    return modules.map(mod => mod.default || mod);
}

function get_media_attach_filter() {
    return document.querySelector('#media-attachment-date-filters');
}

function format_custom_header(type = 1) {

    let header = [];

    switch (type) {
        case 1:

            header = [
                {
                    value: 'ID',
                    width: '6%',
                    key: 'oid',
                    order: 'desc'
                },
                {
                    value: botoscope_lang.title,
                    width: '30%',
                    key: 'title'
                },
                {
                    value: botoscope_lang.media,
                    width: '30%',
                    key: 'media'
                },
                /*
                 {
                 value: botoscope_lang.category',
                 width: '20%',
                 key: 'category'
                 },
                 *
                 */
                {
                    value: botoscope_lang.price,
                    width: '9%',
                    key: 'price',
                    order: 'desc'
                },
                {
                    value: botoscope_lang.sale,
                    width: '9%',
                    key: 'sale_price',
                    order: 'desc'
                },
                {
                    value: botoscope_lang.sku,
                    width: '10%',
                    key: 'sku',
                    order: 'asc'
                },
                {
                    value: 'X',
                    width: '6%',
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
                    width: '55%',
                    key: 'description'
                }
            ];

            break;
    }

    return header;
}


async function get_all_attributes() {
    return await Helper.ajax('botoscope_product_get_all_attributes');
}


async function draw_attributes_section(product_id) {
    //for variable product lets set selection of allowed attributes
    const attributes_container = document.getElementById('botoscope-variable-product-attributes-container');
    const attributes_input = document.querySelector('input[name="product_attributes"]');
    const attributes_terms_input = document.querySelector('input[name="product_attributes_terms"]');

    let selected_product_attributes = attributes_input.value.split(',');
    let selected_product_attributes_terms = attributes_terms_input.value.split(',').map(item => parseInt(item));

    const all_attributes = await get_all_attributes();

    //selector of attributes
    let product_attributes_select = Helper.create_html_select(all_attributes.taxonomies, selected_product_attributes, {
        class: '',
        multiple: 'multiple'
    }, {
        name: 'change',
        callback: e => {
            attributes_input.value = product_attributes_select23.selected_values.join(',');
        }
    });

    attributes_container.appendChild(product_attributes_select);

    const product_attributes_select23 = new SelectM23(product_attributes_select, false, botoscope_lang.attributes);
    product_attributes_select23.after_option_unselect = function (option_value) {
        if (terms_selectors.length > 0) {
            terms_selectors.forEach(s => {
                if (s.select.dataset.attribute === option_value) {
                    terms_selectors = terms_selectors.filter(s => s.select.dataset.attribute !== option_value);
                    s.remove();
                    return;
                }
            });

            update_selected_terms(product_id);
        }
    };

    product_attributes_select23.after_option_select = function (attribute_name) {
        create_terms_selector(attribute_name, all_attributes.blocks[attribute_name], [], product_id);
    };

    //+++

    let terms_selectors = [];

    //lets draw selectors for terms of selected attributes
    for (const [attribute_name, terms] of Object.entries(all_attributes.blocks)) {
        if (selected_product_attributes.includes(attribute_name)) {
            create_terms_selector(attribute_name, terms, selected_product_attributes_terms, product_id);
        }
    }

    function create_terms_selector(attribute_name, terms, selected = [], product_id = 0) {
        const select = Helper.create_html_select(terms, selected, {
            class: '',
            multiple: 'multiple',
            'data-attribute': attribute_name
        }, {
            name: 'change',
            callback: e => update_selected_terms(product_id)
        });
        attributes_container.appendChild(select);
        const select23 = new SelectM23(select, false, all_attributes.taxonomies[attribute_name]);
        terms_selectors.push(select23);
    }

    async function update_selected_terms(product_id = 0) {
        const product_variations_btn = document.querySelector('#botoscope_product_variations');
        product_variations_btn.classList.add('disabled');
        selected_product_attributes_terms = [];
        if (terms_selectors.length > 0) {
            terms_selectors.forEach(select23 => {
                const ids = select23.selected_values.map(item => parseInt(item));
                selected_product_attributes_terms = [...selected_product_attributes_terms, ...ids];
            });
            selected_product_attributes_terms = [...new Set(selected_product_attributes_terms)];
            attributes_terms_input.value = selected_product_attributes_terms.join(',');
        } else {
            selected_product_attributes_terms = [];
            attributes_terms_input.value = selected_product_attributes_terms.join(',');
        }

        if (product_id > 0) {
            await Helper.ajax('botoscope_edit_row', {
                what: 'products',
                id: product_id,
                data: {product_attributes_terms: attributes_terms_input.value}
            });

            product_variations_btn.classList.remove('disabled');
    }
    }


}
