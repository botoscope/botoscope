import Popup from './popup-23.js';
import Helper from '../table/lib/helper.js';
import Table from '../table/table.js';
//09-04-2026
export function create_popup(data) {

    let default_data = {
        id: 'p' + Math.floor(Math.random() * 99999),
        title: '',
        title_logo: botoscope_url + 'assets/img/dolphin.svg',
        title_top_info: botoscope_lang.botoscope,
        hide_backdrop: true,
        mousemove: true,
        /*
         top: '25%',
         bottom: '25%',
         left: '25%',
         right: '25%',
         */
        top: '25%',
        left: '25%',
        width: '800px',
        height: '600px',
        close_word: botoscope_lang.close,
        start_content: botoscope_lang.loading,
        left_button_link: '#',
        left_button_word: '',
        left_button_callback: null,
        footer_buttons_change_sides: 0
    };

    return new Popup({...default_data, ...data});
}

export function create_element(type, data = {}, content = '', event = null) {
    let item = document.createElement(type);

    if (Object.values(data)) {
        for (const [key, value] of Object.entries(data)) {
            item.setAttribute(key, value);
        }
    }

    if (content) {
        if (typeof content === 'string') {
            item.innerHTML = content;
        }

        if (typeof content === 'object') {
            item.appendChild(content);
        }
    }

    if (event) {
        item.addEventListener(event.name, e => {
            e.preventDefault();//!!
            e.stopPropagation();
            event.callback(e);
            return false;
        });
    }

    return item;
}

export function create_html_select22(options, selected = null, data = {}, event = null) {
    let select = Helper.create_element('select', data, '', event);

    for (const [k, v] of Object.entries(options)) {
        let option = Helper.create_element('option', {
            value: k
        }, typeof v === 'object' ? v.title : v);
        if (k == selected) {//!! ==, no ===
            option.selected = true;
        }

        select.appendChild(option);
    }

    return select;
}

export function message(message_txt, type = 'notice', duration = 0) {
    if (duration === 0) {
        duration = 1777;
    }

    //***

    let container = null;

    if (!document.querySelectorAll('#growls').length) {
        container = document.createElement('div');
        container.setAttribute('id', 'growls');
        container.className = 'default';
        document.querySelector('body').appendChild(container);
    } else {
        container = document.getElementById('growls');
    }

    //***


    let wrapper = document.createElement('div');
    wrapper.className = 'growl growl-large growl-' + type;

    let title = document.createElement('div');
    title.className = 'growl-title';
    let title_text = '';

    switch (type) {
        case 'warning':
            title_text = 'Warning';
            break;

        case 'error':
            title_text = 'Error';
            break;

        default:
            title_text = 'Notice';
            break;
    }

    title.innerHTML = title_text;

    let message = document.createElement('div');
    message.className = 'growl-message';
    message.innerHTML = message_txt;

    //***

    //wrapper.appendChild(close);
    wrapper.appendChild(title);
    wrapper.appendChild(message);

    container.innerHTML = '';
    container.appendChild(wrapper);

    wrapper.addEventListener('click', function (e) {
        e.stopPropagation();
        this.remove();
        return false;
    });

    if (duration !== -1) {
        setTimeout(function () {
            wrapper.style.opacity = 0;
            setTimeout(function () {
                wrapper.remove();
            }, 777);
        }, duration);
}

}

export function make_sortable(sortableList, callback = null, direction = 'horizontal') {
    const items = sortableList.querySelectorAll(".sortable-item");

    items.forEach(item => {
        item.addEventListener("dragstart", e => {
            setTimeout(() => item.classList.add("dragging"), 120);
        });

        item.addEventListener("dragend", e => {
            item.classList.remove("dragging");

            if (typeof callback === "function") {
                callback();
            }
        });
    });


    sortableList.addEventListener("dragover", (e) => {
        e.preventDefault();

        let siblings = [...sortableList.querySelectorAll(".sortable-item:not(.dragging)")];

        let drag = sortableList.querySelector(".dragging");

        if (drag && drag.nodeType === Node.ELEMENT_NODE) {
            sortableList.insertBefore(drag, siblings.find(sibling => {

                if (direction === 'horizontal') {
                    //e.clientX
                    return e.offsetX <= sibling.offsetLeft + sibling.offsetWidth / 3;
                }

                return e.clientY <= sibling.offsetTop + sibling.offsetHeight / 3;//vertical
            }));
        }

        return false;
    });

    sortableList.addEventListener("dragenter", e => e.preventDefault());
}

export async function add_table_row(table, what, additional_params = {}, callback = null) {
    return await Helper.ajax('botoscope_add_row', {
        what,
        additional_params
    }, async answer => {
        if (!callback) {
            await table.add_row(answer);
        } else {
            await callback(answer);
        }

        return answer;
    });
}

let async_waiter = 0;

export async function edit_table_cell(data, what, additional_params = {}, table = null, hard = false) {
    await botoscope_sleep(333 + async_waiter);
    async_waiter += 5;

    if (data?.cell) {
        data.cell.value = data.value;
        data.cell.refresh(data.key, data.value);
    }

    if (table) {
        if (hard) {
            table.update_raw_rows_data(data.extra.id, data.key, data.value);
        } else {
            table.update_rows_data(data.extra.id, data.key, data.value);
        }
    }

    if (data?.extra?.id) {
        await save_cell_to_db(data.key, data.extra.id, data.value, what, additional_params);
    }

    return true;
}

export async function save_cell_to_db(key, id, value, what, additional_params = {}){
    await Helper.ajax('botoscope_edit_cell', {
        key,
        id,
        value,
        what,
        additional_params
    }, null, false);
}

function botoscope_sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

export function generateRandomString(length) {
    const charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    const randomValues = new Uint8Array(length);
    window.crypto.getRandomValues(randomValues);
    return Array.from(randomValues).map(value => charset[value % charset.length]).join('');
}

//do not used, leaved as an example of code
export function init_translation_cell(data, table, parent_app, parent_cell_name, call_popup_only = false) {
    {
        let btn = Helper.create_element('a', {
            href: '#',
            class: 'button button-primary'
        }, 'Manage', {
            name: 'click',
            callback:
                    e => call_popup(data.extra.id)
        });

        if (call_popup_only) {
            call_popup(data.extra.id);
        } else {
            data.cell.set_node(btn);
        }

        //+++

        function call_popup(parent_row_id) {
            const popup = create_popup({
                title: 'Translation',
                title_logo: botoscope_url + 'assets/img/dolphin.svg',
                title_top_info: table.data.rows[data.cell.row_index].title,
                left_button_word: '',
                close_word: 'Close',
                footer_buttons_change_sides: 0,
                width: botoscope_is_mobile ? '100%' : '75%',
                height: botoscope_is_mobile ? '90%' : '75%',
                top: '12%',
                bottom: '12%',
                left: botoscope_is_mobile ? 0 : '12%',
                right: '12%',
                mousemove: true
            });

            let wrapper = create_element('div', {
                class: 'formulas-wrapper'
            });

            popup.clear_content();
            popup.append_content(wrapper);

            Helper.ajax('botoscope_get_translations', {
                parent_app,
                parent_cell_name,
                parent_row_id
            }, function (server_data) {
                let slug = 'translations';

                let formulas_data = {
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
                            value: 'Language',
                            width: '20%',
                            key: 'language'
                        },
                        {
                            value: 'Value',
                            width: '80%',
                            key: 'value'
                        }
                    ],
                    raw_rows_data: server_data,
                    format_data(rd) {
                        const {id, ...rest} = rd;

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

                let translations_table = new Table(wrapper, formulas_data, slug);

                translations_table.data_is_mutated = (operation, data) => {
                    switch (operation) {
                        case 'edit_cell':
                            edit_table_cell(data, slug, {
                                parent_row_id: parent_row_id,
                                parent_table: parent_app,
                                parent_cell: 'translations'
                            });
                            break;

                    }
                };
            }, true);

        }
}
}

export async function reload_table_data(table, page_num, what, more = {}) {
    table.create_loader();

    await Helper.ajax('botoscope_get_page_data', {
        what,
        page_num,
        search: table.search,
        order_by: table.get_attribute_value('order-by'),
        order: table.get_attribute_value('order'),
        more
    }, res => {
        if (res.hasOwnProperty('found_posts')) {
            table.set_attribute_value('records-count', res.found_posts);
            table.records_count = parseInt(res.found_posts);
            table.redraw(res.posts, true);
            table.pagination_1.current_page = 0;
            table.pagination_2.current_page = 0;
        } else {
            table.redraw(res, true);
        }
    });

    table.delete_loader();
}

export function redraw_drop_down(drop_down, keys, list) {
    let selected = drop_down.value;
    //console.log(`selected: ${selected}`);
    drop_down.innerHTML = '';
    Object.values(keys).forEach(key => {
        let option = document.createElement('option');
        option.value = key;
        option.textContent = list[key];
        if (selected === key) {
            //console.log(`here: ${key}`);
            option.setAttribute('selected', true);
        }

        drop_down.appendChild(option);
    });
}

export function draw_translatable_cell(table, slug, data, languages) {
    if (languages.selected_language !== languages.default_language) {
        let btn_translation = Helper.create_element('a', {
            href: '#',
            class: 'button button-primary botoscope_cell_translation_btn'
        }, '🌍', {
            name: 'click',
            callback: e => {
                message(botoscope_lang.translating, 'warning', -1);
                Helper.ajax('botoscope_translate_string', {
                    value: data.value,
                    selected_language: languages.selected_language,
                    default_language: languages.default_language
                }, (res) => {
                    message(botoscope_lang.translating, 'warning', 1);
                    if (res === "-1") {
                        alert(botoscope_lang.require_openai_key);
                    } else {
                        data.cell.draw_content(res);
                        data.value = res;
                        edit_table_cell(data, slug, {}, table);
                    }
                }, false);
            }
        });

        data.cell.append_node(btn_translation);
    }
}

export function init_app_language_functionality(table, slug, languages) {
    let language_selector = document.querySelector(`#botoscope-${slug}-lang-selector`);

    if (language_selector) {
        languages.selected_language = language_selector.value;
        languages.default_language = language_selector.dataset.defaultLanguage;

        if (language_selector.options.length <= 1) {
            language_selector.style.display = 'none';
        }

        let display = 'inline-block';
        let header_type = 1;

        if (languages.selected_language !== languages.default_language) {
            header_type = 0;
            display = 'none';
        }

        if (typeof table.format_custom_header === 'function') {
            //console.log(`header_type: ${header_type}`);
            table.data.header = table.format_custom_header(header_type);
            table.redraw();//!!
        }

        if (document.getElementById(`botoscope_create_${slug}`)) {
            document.getElementById(`botoscope_create_${slug}`).style.display = display;
        }

        //+++

        language_selector.addEventListener('change', e => {
            languages.selected_language = e.target.value??language_selector.dataset.defaultLanguage;
            languages.default_language = language_selector.dataset.defaultLanguage;

            Helper.cast('botoscope-selected-language', {
                data: {
                    language: languages.selected_language
                }
            });

            message(botoscope_lang.loading, 'warning', -1);

            Helper.ajax(`botoscope_${slug}_set_current_language`, {
                what: slug,
                language: languages.selected_language
            }, () => {
                header_type = 1;
                display = 'inline-block';

                if (languages.selected_language !== languages.default_language) {
                    header_type = 0;
                    display = 'none';
                }

                if (typeof table.format_custom_header === 'function') {
                    table.data.header = table.format_custom_header(header_type);
                }

                if (document.getElementById(`botoscope_create_${slug}`)) {
                    document.getElementById(`botoscope_create_${slug}`).style.display = display;
                }

                reload_table_data(table, 0, slug);

                message(botoscope_lang.done);
            }, false);
        });


        Helper.addSingleEventListener('botoscope-actual-languages', {instance_key: table.instance_key}, e => {
            if (e.detail.data.languages.length > 1) {
                language_selector.style.display = 'inline';
                let languages_list = JSON.parse(document.getElementById('botoscope_languages_list').innerHTML);
                redraw_drop_down(language_selector, e.detail.data.languages, languages_list);
            } else {
                language_selector.style.display = 'none';
            }
        });

        //deactivated, reload page activated
        Helper.addSingleEventListener('botoscope-default-language', {instance_key: table.instance_key}, e => {
            languages.selected_language = document.querySelector(`#botoscope-${slug}-lang-selector`).value;
            languages.default_language = e.detail.data.value;
            language_selector.dataset.defaultLanguage = e.detail.data.value;

            if (languages.selected_language !== languages.default_language) {
                header_type = 0;
                display = 'none';
            }

            if (typeof table.format_custom_header === 'function') {
                table.data.header = table.format_custom_header(header_type);
            }

            if (document.getElementById(`botoscope_create_${slug}`)) {
                document.getElementById(`botoscope_create_${slug}`).style.display = display;
            }

            languages.default_language = e.detail.data.value;
            language_selector.value = e.detail.data.value;
            language_selector.dispatchEvent(new Event('change'));

            //table.redraw();
            reload_table_data(table, 0, slug);
        });


    }

    return language_selector;
}


export function cast(event, data) {
    document.dispatchEvent(new CustomEvent(event, {detail: data}));
}

export function are_objects_equal(obj1, obj2) {
    const keys1 = Object.keys(obj1);
    const keys2 = Object.keys(obj2);

    // Check if the number of keys is the same
    if (keys1.length !== keys2.length) {
        return false;
    }

    // Compare the values ​​of all keys
    for (const key of keys1) {
        if (String(obj1[key]) !== String(obj2[key])) {
            return false; // If the values ​​of at least one key are not equal, return false
        }
    }

    return true;
}

export function format_string(str, ...values) {
    let i = 0;
    return str.replace(/%s/g, () => values[i++] ?? '%s');
}

export async function load_taxonomies_terms(taxonomy = 'product_cat') {
    return await Helper.ajax('botoscope_taxonomies_get_hierarchical_terms', {taxonomy}, async answer => answer);
}


export async function draw_terms_select(terms, disabled, selected_ids = [], callback) {
    terms = flat_taxonomy_terms(terms);
    let select_options = {};

    if (terms.length > 0) {
        terms.forEach(t => {
            select_options[parseInt(t.id)] = t.title;
        });
    }

    let select = Helper.create_html_select(select_options, selected_ids, {
        class: '',
        multiple: 'multiple',
        disabled
    }, {
        name: 'change',
        callback
    });

    return select;
}

export function flat_taxonomy_terms(terms, level = 0, parentPath = '') {
    const result = [];

    terms.forEach(term => {
        // Forming a full path for the current term
        const fullPath = parentPath ? `${parentPath} / ${term.title}` : term.title;

        let d = {
            id: parseInt(term.id),
            title: `${'-'.repeat(level)} ${fullPath}`.trim() // Add the current path with the nesting level
        };

        result.push(d);

        // If the term has children, start the recursion
        if (term.children && term.children.length > 0) {
            const children = flat_taxonomy_terms(term.children, level + 1, fullPath);
            result.push(...children);
        }
    });

    return result;
}

export function get_disabled_terms(terms) {
    const result = [];

    terms.forEach(term => {
        if (term.children && term.children.length > 0) {
            result.push(parseInt(term.id));
        }

        if (term.children && term.children.length > 0) {
            const childResults = get_disabled_terms(term.children);
            result.push(...childResults);
        }
    });

    return result;
}


export function isNumeric(val) {
    return !isNaN(parseFloat(val)) && isFinite(val);
}


export function update_select_options(slx, items) {
    const selectedId = slx.value;
    slx.innerHTML = '';

    items.forEach(({ id, title }) => {
        const opt = document.createElement('option');
        opt.value = id;
        opt.textContent = title;
        if (String(id) === String(selectedId)) {
            opt.selected = true;
        }
        slx.appendChild(opt);
    });
}

export async function draw_product_media(table, product_id, cell_value, callback = null, cell = null) {

    if (!cell_value) {
        return;
    }

    let is = true;

    if (cell_value.length === 1) {
        if (!cell_value[0]?.aid) {
            is = false;
        }
    }

    //+++

    const ul = Helper.create_element('ul', {
        class: 'product_media'
    });

    if (is) {
        cell_value.forEach(item => {
            const li = Helper.create_element('li', {
                'data-attachment_id': item.aid
            });

            let a = Helper.create_element('a', {
                href: '#',
                class: 'delete tips'
            }, botoscope_lang.delete, {
                name: 'click',
                callback: function (e) {
                    const removed_id = parseInt(li.getAttribute("data-attachment_id"));
                    li.remove();//!!

                    let attachment_ids = [];
                    ul.querySelectorAll("li").forEach(function (li) {
                        attachment_ids.push(parseInt(li.getAttribute("data-attachment_id")));
                    });

                    if (cell) {
                        cell.value = attachment_ids;
                        let media = cell.table.data.raw_rows_data[cell.row_index].media;
                        cell.refresh('media', media.filter(item => parseInt(item.aid) !== removed_id));
                    }

                    if (!callback) {
                        Helper.ajax('botoscope_products_save_gallery', {
                            attachment_ids: attachment_ids.join(','),
                            product_id
                        });
                    } else {
                        callback(attachment_ids);
                    }

                }
            });

            //+++

            let media_item;

            if (item.type === 'image') {
                media_item = Helper.create_element('img', {
                    src: item.media,
                    width: 50,
                    alt: '',
                    loading: 'lazy',
                    class: 'botoscope-products-media-item'
                });
            } else {
                media_item = Helper.create_element('video', {
                    src: item.media,
                    width: 50,
                    controls: false,
                    autoplay: false,
                    preload: 'metadata',
                    muted: true,
                    loading: 'lazy',
                    class: 'botoscope-products-media-item'
                });
            }

            li.appendChild(media_item);
            li.appendChild(a);
            ul.appendChild(li);
        });

        jQuery(ul).sortable({
            items: 'li',
            cursor: 'move',
            scrollSensitivity: 40,
            forcePlaceholderSize: true,
            forceHelperSize: false,
            helper: 'clone',
            opacity: 0.65,
            placeholder: 'wc-metabox-sortable-placeholder',
            start: function (event, ui) {
                ui.item.css('background-color', '#f6f6f6');
            },
            stop: function (event, ui) {
                ui.item.removeAttr('style');
            },
            update: function () {
                let attachment_ids = [];
                ul.querySelectorAll("li").forEach(function (li) {
                    attachment_ids.push(parseInt(li.getAttribute("data-attachment_id")));
                });

                if (cell) {
                    const new_media = Helper.sortMediaByOrder(table.data.raw_rows_data[cell.row_index].media, attachment_ids, 'aid');
                    cell.refresh('media', new_media);
                }

                if (!callback) {
                    Helper.ajax('botoscope_products_save_gallery', {
                        attachment_ids: attachment_ids.join(','),
                        product_id
                    });

                    cell_value = attachment_ids;
                } else {
                    callback(attachment_ids);
                }
            }
        });

    } else {
        cell_value.forEach(item => {
            const li = Helper.create_element('li', {
                class: 'image'
            });

            let img = Helper.create_element('img', {
                src: item.media,
                width: 50,
                alt: '',
                class: 'botoscope-products-media-item'
            });

            li.appendChild(img);
            ul.appendChild(li);
        });
    }

    return ul;
}

export async function draw_media_add_button(product_id, ul, table, callback = null) {
    const a = Helper.create_element('a', {
        href: '#',
        class: 'botoscope_products_add_attachments tips'
    }, botoscope_lang.add, {
        name: 'click',
        callback: function (e) {

            let exclude = [];
            const button = e.currentTarget;
            const container = button.parentElement;
            const ul = container.querySelector('ul');

            ul.querySelectorAll("li").forEach(function (li) {
                if (li.getAttribute("data-attachment_id")) {
                    exclude.push(parseInt(li.getAttribute("data-attachment_id")));
                }
            });

            var media = wp.media({
                title: botoscope_lang.select_media,
                multiple: true,
                library: {
                    type: ['image', 'video'],
                    exclude: exclude.map(id => parseInt(id))
                }
            });

            // Tracking file downloads
            let uploadComplete = false;
            let uploadedCount = 0;
            let totalToUpload = 0;

            media.on('open', () => {
                uploadComplete = false;
                uploadedCount = 0;
                totalToUpload = 0;

                if (typeof wp.Uploader !== 'undefined' && typeof wp.Uploader.queue !== 'undefined') {

                    // We calculate how many files will be downloaded
                    wp.Uploader.queue.on('add', () => {
                        totalToUpload++;
                    });

                    // We track the completion of each file download
                    wp.Uploader.queue.on('reset', () => {
                        // All files have been uploaded
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
                    // Get the current frame
                    const frame = media.state();
                    const library = frame.get('library');

                    if (library) {
                        // Clear the cache and update
                        library.props.set({ignore: (+new Date())});

                        // Or you can force an update
                        // library.fetch();
                    }
                } catch (e) {
                    console.log('Media refresh error:', e);
                }
            }

            media.open().on('select', async function () {
                var selection = media.state().get('selection');
                var new_attachment_ids = [];

                selection.each(function (attachment) {
                    new_attachment_ids.push(attachment.id);
                });

                const maxFiles = 8;
                const can_be_added = maxFiles - exclude.length;

                if (can_be_added > 0) {
                    if (!callback) {
                        message(botoscope_lang.loading, 'warning', -1);
                        Helper.ajax('botoscope_products_addto_gallery', {
                            product_id,
                            attachment_ids: new_attachment_ids.slice(0, can_be_added).join(',')
                        }, async function () {
                            await table.set_page(table.pagination_1.current_page);
                            message(botoscope_lang.done);
                        });
                    } else {
                        callback(new_attachment_ids.slice(0, can_be_added));
                    }

                    if ((can_be_added - new_attachment_ids.length) < 0) {
                        message(format_string(botoscope_lang.max_files_count, maxFiles, can_be_added), 'warning', 5000);
                    } else {
                        message(botoscope_lang.done);
                    }
                    await new Promise(resolve => setTimeout(resolve, 3000));
                } else {
                    message(format_string(botoscope_lang.max_files_count_no_added, maxFiles), 'warning', 5000);
                    return false;
                }
            });

        }
    });

    return a;
}


export function pause_videos(container) {
    setTimeout(function () {
        let videos;

        if (container) {
            videos = container.querySelectorAll('video');
        } else {
            videos = document.body.querySelectorAll('video');
        }

        videos.forEach(video => {
            video.pause();
            video.currentTime = 0;
        });
    }, 111);
}

export async function text_by_ai(value, command) {
    let res = '';
    if (value.length === 0) {
        alert(botoscope_lang.provide_some_text);
        return false;
    }

    message(botoscope_lang.loading, 'warning', -1);
    await Helper.ajax('botoscope_products_process_text_by_ai', {
        command,
        value: value
    }, async function (text) {
        message(botoscope_lang.done);

        if (res === '-1') {
            alert(botoscope_lang.require_openai_key);
            return;
        }

        //textarea.focus();
        //document.execCommand('selectAll', false);
        //document.execCommand('insertText', false, text);

        res = text;
    }, false);

    return res;
}



export function tiny_textarea(id, toolbar = "") {
    if (typeof tinymce !== "undefined") {

        if (!toolbar) {
            toolbar = "formatselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | insertHtmlButton";
        }

        tinymce.remove('#' + id); // Удаляем, если уже есть
        tinymce.init({
            selector: "#" + id,
            menubar: false,
            plugins: "lists link image charmap",
            toolbar,
            branding: false,
            setup: function (editor) {
                editor.on('change', function () {
                    tinymce.triggerSave();
                });

                // Для TinyMCE 4.x
                editor.addButton('insertHtmlButton', {
                    text: botoscope_lang.insert_html,
                    icon: "code",
                    onclick: function () {
                        editor.windowManager.open({
                            title: botoscope_lang.insert_html,
                            body: [
                                {
                                    type: "textbox",
                                    name: "html_code",
                                    label: botoscope_lang.enter_html_code,
                                    multiline: true,
                                    minWidth: 400,
                                    minHeight: 200
                                }
                            ],
                            onsubmit: function (e) {
                                editor.insertContent(e.data.html_code);
                            }
                        });
                    }
                });
            }
        });
}

}


export function waitForTiny(id, timeout = 3000) {
    return new Promise((resolve) => {
        const start = Date.now();
        (function check() {
            if (window.tinymce) {
                const ed = tinymce.get(id);
                if (ed) {
                    resolve(ed);
                    return;
                }
            }
            if (Date.now() - start > timeout) {
                resolve(null);
                return;
            }
            setTimeout(check, 100);
        })();
    });
}


export async function ai_description(command, editorId = 'botoscope-product-description') {
    const editor = await waitForTiny(editorId, 2000);
    const textareaEl = document.getElementById(editorId);

    try {
        let html;
        if (editor) {
            html = editor.getContent();
        } else if (textareaEl) {
            html = textareaEl.value;
        }

        const returnedHtml = await text_by_ai(html, command);

        if (editor) {
            editor.setContent(returnedHtml);
            try {
                editor.focus();
            } catch (err) {
                //+++
            }
            tinymce.triggerSave();
        } else if (textareaEl) {
            textareaEl.value = returnedHtml;
        }

    } catch (err) {
        console.error('AI HTML transform failed:', err);
}
}



