'use strict';
import Helper from '../table/lib/helper.js';
import * as Functions from './functions.js';
//13-01-2025
export default class Sidebar {
    constructor(data_table, row_id, title = '', width = 400) {
        this.element = null;//dom element
        this.content_container = null;
        this.data_table = data_table;
        this.row_id = row_id;
        this.title = title;
        this.width = width;

        this.draw();
    }

    draw() {
        let template = this.get_template();
        this.element = (new DOMParser).parseFromString(template, 'text/html').querySelector('div');
        document.body.appendChild(this.element);
        this.transparent(true);
        this.content_container = this.element.querySelector('section');

        setTimeout(() => {
            this.element.classList.add('botoscope-static-sidebar-opened');
        }, 23);

        this.element.querySelector('.botoscope-sidebar-close').addEventListener('click', e => {
            e.preventDefault();

            this.close();

            return false;
        });
    }

    close() {
        this.element.classList.remove('botoscope-static-sidebar-opened');
        this.transparent(false);

        setTimeout(() => {
            this.element.remove();
        }, 777);
    }

    transparent(is = true) {
        is ? document.querySelector('.transparent-window').classList.add('active')
                : document.querySelector('.transparent-window').classList.remove('active');
    }

    get_template() {

        let header = '';

        if (this.title.length > 0) {
            header = `<header>
                <h3>${this.title}</h3>
            </header>`;
        }

        return `
<div class="botoscope-static-sidebar" style="width: ${this.width};">

    <div class="botoscope-sidebar-close">
        <a href="#" title="${botoscope_lang.close}" rel="nofollow" class="icon-cancel-circled">${botoscope_lang.close}</a>
    </div>

    <div class="botoscope-static-sidebar-widget">

        ${header}

        <section>${botoscope_lang.loading}</section>

    </div>
</div>
`;
    }

    clear() {
        this.content_container.innerHTML = '';
    }

    set_content(template_name, more_data = {}, save_callback = null) {
        Helper.ajax('botoscope_get_sidebar_html', {
            what: this.data_table,
            template_name,
            id: this.row_id,
            more_data: JSON.stringify(more_data)
        }, html => {
            this.content_container.innerHTML = html;

            this.after_set();

            if (this.content_container.querySelector('form')) {
                this.content_container.querySelector('form').addEventListener('submit', e => {
                    e.preventDefault();

                    const formData = new FormData(e.target);
                    const formProps = Object.fromEntries(formData);

                    try {
                        if (save_callback) {
                            save_callback(this.data_table, this.row_id, formProps);
                        } else {
                            Functions.message(botoscope_lang.loading, 'warning', -1);
                            Helper.ajax('botoscope_edit_row', {
                                what: this.data_table,
                                id: this.row_id,
                                data: formProps
                            }, res => {
                                if (typeof res === 'object') {
                                    if (res.success) {
                                        Functions.message(botoscope_lang.saved);
                                        this.after_save(res);
                                    } else {
                                        Functions.message(res.data, 'error', 3000);
                                    }
                                } else {
                                    Functions.message(botoscope_lang.saved);
                                    this.after_save(res);
                                }

                            }, true);
                        }

                    } catch (e) {
                        Functions.message(botoscope_lang.loading, 'warning', 1);
                    }

                    return false;
                });
            }

        }, false);
    }

    after_save(data) {
        //api
    }

    after_set() {
        //api
    }
}



