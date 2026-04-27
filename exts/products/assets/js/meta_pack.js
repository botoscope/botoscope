let Functions, Helper, Table, Sidebar, Switcher;
//11-03-2025
export default class MetaPack {
    constructor(product_id, wrapper, product_meta_table) {
        this.product_id = parseInt(product_id);
        this.wrapper = wrapper;
        this.product_meta_table = product_meta_table;
        this.slug = 'product_meta_pack';
        this.init();
    }

    async init() {
        [Functions, Helper, Table, Sidebar, Switcher] = await loadModules();
        this.draw_buttons();
    }

    draw_buttons() {

        const container = Helper.create_element('div', {
            class: 'botoscope-meta-pack-container'
        }, '');

        const btn_create = Helper.create_element('a', {
            href: '#',
            style: 'width: fit-content;',
            class: 'botoscope-button botoscope-button-small'
        }, '<span class="icon-archive" style="padding: 0 10px 0 0; margin: 0;"></span>' + botoscope_lang.create_meta_pack, {
            name: 'click',
            callback: async e => {
                Functions.message(botoscope_lang.loading, 'warning', -1);
                Helper.ajax('botoscope_product_create_meta_pack', {
                    product_id: this.product_id
                }, res => Functions.message(botoscope_lang.saved));
            }
        });


        const btn_load = Helper.create_element('a', {
            href: '#',
            style: 'width: fit-content;',
            class: 'botoscope-button botoscope-button-small'
        }, '<span class="icon-upload" style="padding: 0 10px 0 0; margin: 0;"></span>' + botoscope_lang.load_meta_pack, {
            name: 'click',
            callback: async e => this.open_sidebar()
        });

        container.appendChild(btn_create);
        container.appendChild(btn_load);
        this.wrapper.appendChild(container);
    }

    async open_sidebar() {
        let sidebar = new Sidebar(this.slug, this.product_id, botoscope_lang.meta_packs, botoscope_is_mobile ? '100%' : '97%');
        sidebar.set_content('products-meta-packs', {}, function (data_table, row_id, formProps) {
            return false;
        });

        sidebar.after_set = async () => {
            const slug = this.slug;
            const wrapper = document.getElementById('botoscope-products-meta-packs-wrapper');

            const data = {
                attributes: {
                    'class': slug,
                    'data-data-table': slug,
                    'data-order': 'desc',
                    'data-order-by': 'id',
                    'data-per-page': -1,
                    'data-records-count': 9999,
                    'id': `the_table_${slug}`
                },
                header: [
                    {
                        value: 'ID',
                        width: '5%',
                        key: 'oid'
                    },
                    {
                        value: botoscope_lang.title,
                        width: '75%',
                        key: 'title'
                    },
                    {
                        value: botoscope_lang.apply,
                        width: '10%',
                        key: 'apply'
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
                            //el_type: "switcher",
                            value: is_active
                        },
                        extra: {
                            editable: ['title'],
                            id: id
                        }
                    };
                }
            };

            //+++

            wrapper.innerHTML = '';

            const table = new Table(wrapper, data, slug, {
                cell_content_drawn: async data => {

                    const cell = data.cell;

                    switch (data.key) {
                        case 'oid':
                            {
                                cell.draw_content(`<b>${data.extra.id}</b>`);
                            }
                            break;

                        case 'apply':
                            {
                                cell.set_node(Helper.create_element('a', {
                                    href: '#',
                                    class: 'button button-primary'
                                }, '<span class="icon-play"></span>', {
                                    name: 'click',
                                    callback: async e => {
                                        Functions.message(botoscope_lang.loading, 'warning', -1);
                                        Helper.ajax('botoscope_product_apply_pack', {
                                            product_id: this.product_id,
                                            pack_id: data.extra.id
                                        }, res => {
                                            Functions.message(botoscope_lang.saved);
                                            this.product_meta_table.set_page(0);
                                        });
                                    }
                                }));
                            }
                            break;

                        case 'delete':
                            {
                                let btn = Helper.create_element('a', {
                                    href: '#',
                                    class: 'button button-primary'
                                }, 'X', {
                                    name: 'click',
                                    callback: e => call_delete_cell(data.extra.id)
                                });

                                cell.set_node(btn);

                                async function call_delete_cell(row_id) {
                                    if (confirm(botoscope_lang.are_you_sure)) {
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
        };
    }
}


async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js'),
        import(botoscope_url + 'assets/js/lib/sidebar.js'),
        import(botoscope_url + 'assets/js/table/ae/switcher.js'),
    ]);

    return modules.map(mod => mod.default || mod);
}


