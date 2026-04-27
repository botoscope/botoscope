let Functions, Helper, Table;
//15-12-2025
export default class Downloads {
    constructor(parent_table, parent_product_id, parent_sidebar, parent_call_btn) {
        this.parent_table = parent_table;
        this.parent_product_id = parent_product_id;
        this.parent_call_btn = parent_call_btn;
        this.parent_sidebar = parent_sidebar;

        this.init();
    }

    async init() {
        [Functions, Helper, Table] = await loadModules();
        this.draw();
    }

    async draw(raw_rows_data = null) {
        const [Functions, Helper, Table] = await loadModules();
        const wrapper = this.parent_sidebar.content_container.querySelector('#botoscope_product_downloads');
        const table_slug = 'product_downloads';
        if (!raw_rows_data) {
            raw_rows_data = JSON.parse(wrapper.textContent);
        }

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
            header: format_custom_header(),
            raw_rows_data,
            format_data: function (rd) {
                const {id, is_active, ...rest} = rd;

                return {
                    ...rest,
                    is_active: {
                        el_type: "switcher",
                        value: is_active
                    },
                    extra: {
                        editable: ['title', 'file_url'],
                        id: id
                    }
                };
            }
        };

        wrapper.innerHTML = '';
        wrapper.style.display = 'block';
        const table = new Table(wrapper, data, table_slug, {
            cell_content_drawn: async data => {

                const cell = data.cell;

                switch (data.key) {

                    case 'menu_order':
                        {
                            let row_index = parseInt(data.cell.row_index);

                            let container = Helper.create_element('div', {
                                class: 'botoscope_row_menu_order'
                            }, '');

                            data.cell.set_node(container);

                            if (row_index > 0) {
                                let btn_up = Helper.create_element('a', {
                                    href: '#',
                                    class: 'arrow-button-up'
                                }, '', {
                                    name: 'click',
                                    callback: e => this.move(table, data, row_index, 'up')
                                });

                                container.appendChild(btn_up);
                            }

                            if (row_index < data.cell.table.data.rows.length - 1) {
                                let btn_down = Helper.create_element('a', {
                                    href: '#',
                                    class: 'arrow-button-down'
                                }, '️', {
                                    name: 'click',
                                    callback: e => this.move(table, data, row_index, 'down')
                                });

                                container.appendChild(btn_down);
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
                                callback: e => {
                                    const download_id = data.extra.id;
                                    if (confirm(botoscope_lang.are_you_sure)) {
                                        table.remove_row(download_id);

                                        Functions.message(botoscope_lang.loading, 'warning', -1);
                                        Helper.ajax('botoscope_delete_product_download', {
                                            product_id: this.parent_product_id,
                                            download_id
                                        }, res => {
                                            table.records_count -= 1;
                                            this.parent_call_btn.innerText = this.parent_call_btn.innerText.replace(/\(\d+\)/, `(${table.records_count})`);
                                            table.redraw();

                                            Functions.message(botoscope_lang.saved);
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

        //+++

        table.data_is_mutated = (operation, data) => {
            switch (operation) {
                case 'edit_cell':
                    Functions.edit_table_cell(data, table_slug, {product_id: this.parent_product_id});
                    break;
                case 'move_col_right':
                case 'move_col_left':
                    table.set_table_col_positions(data.positions, data.key, data.index);
                    break;
            }
        };

        //+++

        const add_files_btn = Helper.create_element('a', {
            href: '#',
            class: 'botoscope_products_add_attachments tips'
        }, botoscope_lang.add, {
            name: 'click',
            callback: (e) => {
                let exclude = [];
                var media = wp.media({
                    title: botoscope_lang.select_media,
                    multiple: true,
                    library: {
                        //exclude: exclude.map(id => parseInt(id))
                    }
                });

                // Tracking file downloads
                let uploadComplete = false;
                let totalToUpload = 0;

                media.on('open', () => {
                    uploadComplete = false;
                    totalToUpload = 0;

                    if (typeof wp.Uploader !== 'undefined' && typeof wp.Uploader.queue !== 'undefined') {

                        // Counting files
                        wp.Uploader.queue.on('add', () => {
                            totalToUpload++;
                        });

                        // We are waiting for all files to finish downloading.
                        wp.Uploader.queue.on('reset', () => {
                            if (totalToUpload > 0 && !uploadComplete) {
                                uploadComplete = true;
                                setTimeout(() => {
                                    refreshMediaLibrary();
                                }, 500);
                            }
                        });
                    }
                });

                // Library update function
                function refreshMediaLibrary() {
                    try {
                        const frame = media.state();
                        const library = frame.get('library');

                        if (library) {
                            library.props.set({ignore: (+new Date())});
                        }
                    } catch (e) {
                        console.log('Media refresh error:', e);
                    }
                }

                media.open().on('select', () => {
                    Functions.message(botoscope_lang.loading, 'warning', -1);
                    var selection = media.state().get('selection');
                    var new_attachment_ids = [];
                    selection.each(function (attachment) {
                        new_attachment_ids.push(attachment.id);
                    });
                    Helper.ajax('botoscope_products_add_product_downloads', {
                        product_id: this.parent_product_id,
                        attachment_ids: new_attachment_ids.join(',')
                    }, async (res) => {
                        Functions.message(botoscope_lang.saved);
                        this.draw(Object.values(res.data));
                        this.parent_call_btn.innerText = this.parent_call_btn.innerText.replace(/\(\d+\)/, `(${Object.values(res.data).length})`);
                    });
                });
            }
        });

        wrapper.appendChild(add_files_btn);

        //+++

        function format_custom_header(type = 1) {

            let header = [];

            switch (type) {
                case 1:

                    header = [
                        {
                            value: botoscope_lang.order,
                            width: '7%',
                            key: 'menu_order'
                        },
                        {
                            value: botoscope_lang.title,
                            width: '33%',
                            key: 'title'
                        },
                        {
                            value: botoscope_lang.file_url,
                            width: '50%',
                            key: 'file_url'
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
                            value: botoscope_lang.title,
                            width: '100%',
                            key: 'title'
                        }
                    ];

                    break;
            }

            return header;
    }
    }

    move(table, data, row_index, direction) {

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

        table.redraw();

        //+++

        Helper.ajax('botoscope_edit_cell', {
            what: 'product_downloads',
            value: tr.map(item => item.extra.id),
            id: this.parent_product_id,
            key: 'downloads_order'
        });

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

