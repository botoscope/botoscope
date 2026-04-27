import * as Functions from '../lib/functions.js';
import Helper from '../table/lib/helper.js';
import Table from '../table/table.js';
import Sidebar from '../lib/sidebar.js';
import Switcher from '../table/ae/switcher.js';
//15-12-2025
const languages = {selected_language: null, default_language: null};
let active_switchers = [];

export default function init_taxonomies() {
    let slug = 'taxonomies';
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
        raw_rows_data: JSON.parse(wrapper.textContent).sort((a, b) => {
            const orderA = parseInt(a.menu_order) || 0;
            const orderB = parseInt(b.menu_order) || 0;
            return orderA - orderB;
        }),
        format_data: function (rd) {
            const {id, is_active, ...rest} = rd;

            return {
                ...rest,
                is_active: {
                    //el_type: "switcher",
                    value: is_active
                },
                extra: {
                    editable: ['title', 'icon'],
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

                case 'id':
                    if (data.extra.id > 0) {
                        cell.draw_content(data.extra.id);
                    }
                    break;

                case 'title':
                    {
                        setTimeout(() => Functions.draw_translatable_cell(table, slug, data, languages), 999);
                    }
                    break;

                case 'childs':
                    {
                        let taxonomy = table.data.rows[cell.row_index].taxonomy;

                        if (taxonomy?.substr(0, 3) === 'pa_' || taxonomy === 'product_brand') {
                            cell.draw_content('-');
                            return;
                        }

                        let child_count = cell.get_sibling_value('child_count');
                        let btn = Helper.create_element('a', {
                            href: '#',
                            class: 'button button-primary'
                        }, `${botoscope_lang.child_terms} (${child_count})`, {
                            name: 'click',
                            callback: e => update_parent_term(cell.extra.id, table, slug)
                        });

                        cell.set_node(btn);
                    }
                    break;

                case 'menu_order':
                    {
                        const row_index = parseInt(cell.row_index);
                        const terms_ids = cell.table.data.raw_rows_data.map(item => parseInt(item.id))

                        let container = Helper.create_element('div', {
                            class: 'botoscope_row_menu_order'
                        }, '');

                        cell.set_node(container);

                        if (row_index > 0) {
                            container.appendChild(Helper.create_element('a', {
                                href: '#',
                                class: 'arrow-button-up'
                            }, '', {
                                name: 'click',
                                callback: e => move(table, data, terms_ids, row_index, 'up')
                            }));
                        }

                        if (row_index < cell.table.data.rows.length - 1) {
                            container.appendChild(Helper.create_element('a', {
                                href: '#',
                                class: 'arrow-button-down'
                            }, '️', {
                                name: 'click',
                                callback: e => move(table, data, terms_ids, row_index, 'down')
                            }));
                        }

                    }
                    break;

                case 'is_active':
                    {
                        let sw = new Switcher(this.id, parseInt(cell.table.data.rows[cell.row_index].is_active?.value), cell.container);

                        sw.activate = async function () {
                            this.set_checked();
                            await Functions.edit_table_cell({...data, key: 'is_active', value: 1}, slug, {}, table, true);
                        };

                        sw.deactivate = async function () {
                            this.set_unchecked();
                            await Functions.edit_table_cell({...data, key: 'is_active', value: 0}, slug, {}, table, true);
                        };

                        sw.setEvent('click', async (e, input) => {
                            if (sw.is_checked()) {
                                sw.activate();
                            } else {
                                sw.deactivate();
                            }
                        });

                        active_switchers[cell.row_index] = sw;
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
                                    Helper.cast('botoscope-taxonomies-updated', {
                                        data: {}
                                    });
                                }, false);
                            }
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
                await Functions.edit_table_cell(data, slug);
                Helper.cast('botoscope-taxonomies-updated', {
                    data: {}
                });
                break;
            case 'move_col_right':
            case 'move_col_left':
                table.set_table_col_positions(data.positions, data.key, data.index);
                break;
        }
    };

    //+++    

    document.getElementById(`botoscope_create_${slug}`).addEventListener('click', e => Functions.add_table_row(table, slug));

    table.rebuild = (options) => {
        if (table.data.raw_rows_data && Array.isArray(table.data.raw_rows_data)) {
            table.data.raw_rows_data.sort((a, b) => {
                const orderA = parseInt(a.menu_order) || 0;
                const orderB = parseInt(b.menu_order) || 0;
                return orderA - orderB;
            });
        }

        table.redraw();
    };

    //+++

    document.querySelector('#botoscope-taxonomies-selector1').addEventListener('change', e => {

        const taxonomy = e.target.value;

        if (taxonomy === 'product_brand' || taxonomy === 'product_cat') {
            document.getElementById('botoscope-taxonomies-edit-taxonomy-attribute-btn').style.display = 'none';
        } else {
            document.getElementById('botoscope-taxonomies-edit-taxonomy-attribute-btn').style.display = 'inline-block';
        }

        Helper.ajax('botoscope_taxonomies_set_current', {
            what: slug,
            taxonomy
        }, function () {
            Functions.reload_table_data(table, 0, slug);
            update_breadcrumb(0);
        }, false);
    });

    //+++

    document.getElementById('botoscope-taxonomies-breadcrumb').addEventListener('click', e => {
        e.preventDefault();

        if (e.target.tagName === 'A') {
            update_parent_term(e.target.dataset.termId, table, slug)
        }

        return false;
    });

    //+++

    document.getElementById('botoscope-taxonomies-create-taxonomy-attribute-btn').addEventListener('click', e => {
        e.preventDefault();

        let sidebar = new Sidebar('taxonomies', 'new_attribute', botoscope_lang.new_attribute_taxonomy, botoscope_is_mobile ? '100%' : '70%');
        sidebar.set_content('sidebar-create-attribute');

        sidebar.after_save = res => {
            update_selector(`pa_${decodeURIComponent(res.data.slug)}`, res.data.name, sidebar);
        };

        sidebar.after_set = async () => {
            //***
        };

        return false;
    });

    //+++

    document.getElementById('botoscope-taxonomies-create-taxonomy-btn').addEventListener('click', e => {
        e.preventDefault();

        let sidebar = new Sidebar('taxonomies', 'new_taxonomy', botoscope_lang.new_taxonomy, botoscope_is_mobile ? '100%' : '70%');
        sidebar.set_content('sidebar-create-taxonomy');

        sidebar.after_save = res => {
            update_selector(decodeURIComponent(res.data.slug), res.data.name, sidebar);
        };

        sidebar.after_set = async () => {
            //***
        };

        return false;
    });

    //+++

    document.getElementById('botoscope-taxonomies-edit-taxonomy-attribute-btn').addEventListener('click', e => {
        e.preventDefault();

        let sidebar = new Sidebar('taxonomies', 'edit_taxonomy', botoscope_lang.edit, botoscope_is_mobile ? '100%' : '70%');
        sidebar.set_content('sidebar-edit-taxonomy', {taxonomy: document.querySelector('#botoscope-taxonomies-selector1').value});

        sidebar.after_save = res => {
            //update_selector(decodeURIComponent(res.data.slug), res.data.name, sidebar);
            window.location.reload();
        };

        sidebar.after_set = async () => {
            const delete_btn = sidebar.content_container.querySelector('#botoscope-delete-taxonomy');
            delete_btn.addEventListener('click', e => {
                e.preventDefault();
                if (confirm(botoscope_lang.are_you_sure)) {
                    Helper.ajax('botoscope_delete_taxonomy', {
                        taxonomy: delete_btn.dataset.slug
                    }, () => {
                        window.location.reload();
                    }, false);
                }

                return false;
            });
        };

        return false;
    });

    //+++



    table.format_custom_header = format_custom_header;
    const language_selector = Functions.init_app_language_functionality(table, slug, languages);
    if (languages.selected_language !== languages.default_language) {
        language_selector.dispatchEvent(new Event('change'));
    }
    ;

    //+++

    return table;
}

function update_parent_term(term_id, table, slug) {
    Helper.ajax('botoscope_taxonomies_set_parent', {
        what: slug,
        parent_id: term_id
    }, () => {
        Functions.reload_table_data(table, 0, slug);
        update_breadcrumb(term_id);
    }, false);
}

function update_breadcrumb(term_id) {

    if (parseInt(term_id) === 0) {
        document.getElementById('botoscope-taxonomies-breadcrumb').innerHTML = '';
        return;
    }

    Helper.ajax('botoscope_taxonomies_get_breadcumb', {}, (res) => {
        document.getElementById('botoscope-taxonomies-breadcrumb').innerHTML = res;
    }, false);
}

function move(table, data, terms_ids, row_index, direction) {

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

    terms_ids = [];
    data.cell.table.data.rows.forEach((value, index) => {
        terms_ids.push(parseInt(value.extra.id));
    });

    table.redraw();

    //+++

    Helper.ajax('botoscope_edit_cell', {
        what: 'taxonomies',
        value: terms_ids.join(','),
        key: 'menu_order'
    }, null, false);

}

function format_custom_header(type = 1) {

    let header = [];

    switch (type) {
        case 1:

            header = [
                {
                    value: botoscope_lang.order,
                    width: '5%',
                    key: 'menu_order'
                },
                {
                    value: 'ID',
                    width: '5%',
                    key: 'id'
                },
                {
                    value: botoscope_lang.title,
                    width: '48%',
                    key: 'title'
                },
                {
                    value: botoscope_lang.icon,
                    width: '7%',
                    key: 'icon'
                },
                {
                    value: botoscope_lang.child_terms,
                    width: '15%',
                    key: 'childs'
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
                    key: 'id'
                },
                {
                    value: botoscope_lang.original,
                    width: '40%',
                    key: 'original'
                },
                {
                    value: botoscope_lang.child_terms,
                    width: '15%',
                    key: 'childs'
                },
                {
                    value: botoscope_lang.translated,
                    width: '40%',
                    key: 'title'
                }
            ];

            break;
    }

    return header;
}

function update_selector(value, name, sidebar) {
    const selector = document.querySelector('#botoscope-taxonomies-selector1');
    const option = Helper.create_element('option', {
        value
    }, name);
    selector.appendChild(option);
    selector.value = option.value;
    selector.dispatchEvent(new Event('change'));
    sidebar.close();
}

document.querySelector('#bs-taxonomies-activate-all-terms').addEventListener('click', async e => {
    e.preventDefault();

    if (Object.values(active_switchers).length > 0) {
        Functions.message(botoscope_lang.loading, 'warning', -1);
        for (let sw of active_switchers) {
            if (!sw.is_checked()) {
                await sw.activate();
            }
        }
        Functions.message(botoscope_lang.done);
    }

    return false;
});


document.querySelector('#bs-taxonomies-deactivate-all-terms').addEventListener('click', async e => {
    e.preventDefault();

    if (Object.values(active_switchers).length > 0) {
        Functions.message(botoscope_lang.loading, 'warning', -1);
        for (let sw of active_switchers) {
            if (sw.is_checked()) {
                await sw.deactivate();
            }
        }
        Functions.message(botoscope_lang.done);
    }

    return false;
});


