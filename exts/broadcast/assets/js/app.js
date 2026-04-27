const languages = {selected_language: null, default_language: null};
//08-04-2025
export default async function init_app() {

    if (botoscope_no_bot) {
        return false;
    }

    const [Functions, Helper, Table, Switcher, Sidebar] = await loadModules();
    let slug = 'broadcast';
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
                extra: {
                    editable: ['title'],
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

                case 'oid':
                    if (data.extra.id > 0) {
                        cell.draw_content(data.extra.id);
                    }
                    break;

                case 'message':
                    {
                        const message_id = parseInt(data.extra.id);

                        if (false) {
                            cell.draw_content(`<b>${botoscope_lang.added}</b>`);
                        } else {

                            let icon = 'icon-play';
                            if (parseInt(cell.get_sibling_value('is_sent'))) {
                                icon = 'icon-eye';
                            }

                            cell.set_node(Helper.create_element('a', {
                                href: '#',
                                class: 'button button-primary'
                            }, `<span class="${icon}"></span>`, {
                                name: 'click',
                                callback: async e => {
                                    const button = e.target;

                                    let sidebar = new Sidebar(slug, message_id, `${botoscope_lang.message} #${message_id}`, botoscope_is_mobile ? '100%' : '97%');
                                    sidebar.set_content('sidebar-broadcast-message', {}, function (data_table, row_id, formProps) {
                                        Functions.message(botoscope_lang.loading, 'warning', -1);

                                        Helper.ajax('botoscope_edit_row', {
                                            what: data_table,
                                            id: row_id,
                                            data: formProps
                                        }, xxx => {
                                            Functions.message(botoscope_lang.saved);
                                        }, false);
                                    });

                                    if (!parseInt(cell.get_sibling_value('is_sent'))) {
                                        sidebar.after_set = async () => {

                                            const textarea = sidebar.content_container.querySelector('#botoscope-broadcast-description');

                                            sidebar.content_container.querySelector('#botoscope_broadcast_description_ai').addEventListener('click', e => {
                                                e.preventDefault();
                                                text_by_ai(textarea, 'Generate a short promotional message based on the provided input. Keep it in the original language. Make the message engaging and friendly, optimized for Telegram, and include emojis where appropriate. Do not exceed 3 sentences. Return only the generated message without any explanations.');
                                                return false;
                                            });

                                            sidebar.content_container.querySelector('#botoscope_broadcast_grammar_ai').addEventListener('click', e => {
                                                e.preventDefault();
                                                text_by_ai(textarea, 'You are a text corrector. Preserve the original language of the text, correct its grammar and lexical errors, and return only the corrected text without any explanations.');
                                                return false;
                                            });


                                            sidebar.content_container.querySelector('#botoscope_broadcast_send').addEventListener('click', e => {
                                                e.preventDefault();

                                                if (textarea.value.length === 0) {
                                                    return false;
                                                }

                                                Functions.message(botoscope_lang.loading, 'warning', -1);

                                                if (confirm(botoscope_lang.are_you_sure)) {

                                                    const formData = new FormData(sidebar.content_container.querySelector('form'));
                                                    const formProps = Object.fromEntries(formData);
                                                    sidebar.close();

                                                    const cell_container = button.closest('data-table-cell');
                                                    cell_container.innerText = botoscope_lang.loading;

                                                    Helper.ajax('botoscope_edit_row', {
                                                        what: slug,
                                                        id: message_id,
                                                        data: formProps
                                                    }, xxx => {
                                                        Helper.ajax('botoscope_broadcast_message', {
                                                            value: textarea.value,
                                                            message_id
                                                        }, count => {
                                                            Functions.message(botoscope_lang.sent);
                                                            cell_container.innerText = `${botoscope_lang.sent} (${count})`;
                                                        }, false);
                                                    }, false);


                                                }

                                                return false;
                                            });


                                        };
                                    }
                                }
                            }));
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
        table.redraw();
    };

    //+++

    table.format_custom_header = format_custom_header;

    return table;
}

async function text_by_ai(textarea, command_text) {
    const [Functions, Helper] = await loadModules();

    if (textarea.value.length === 0) {
        alert(botoscope_lang.provide_some_text);
        return false;
    }

    Functions.message(botoscope_lang.loading, 'warning', -1);
    Helper.ajax('botoscope_products_process_text_by_ai', {
        command: '',
        command_text,
        value: textarea.value
    }, async function (res) {
        Functions.message(botoscope_lang.done);

        if (res === '-1') {
            alert(botoscope_lang.require_openai_key);
            return;
        }

        textarea.focus();
        document.execCommand('selectAll', false);
        document.execCommand('insertText', false, res);
    }, false);
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
                    value: botoscope_lang.title,
                    width: '75%',
                    key: 'title'
                },
                {
                    value: botoscope_lang.message,
                    width: '20%',
                    key: 'message'
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
                    width: '95%',
                    key: 'title'
                }
            ];

            break;
    }

    return header;
}



async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js'),
        import(botoscope_url + 'assets/js/table/ae/switcher.js'),
        import(botoscope_url + 'assets/js/lib/sidebar.js'),
    ]);

    return modules.map(mod => mod.default || mod);
}
