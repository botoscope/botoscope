'use strict';
import Helper from '../lib/helper.js';
//05-03-2025
export default class Pagination {
    constructor(table_instance_key, wrapper, per_page, records_count, append = true) {
        if (per_page <= 0) {
            return null;
        }

        this.table_instance_key = table_instance_key;
        this.wrapper = wrapper;
        this.per_page = per_page;
        this.records_count = records_count;
        this.append = append;
        this.current_page = 0;
        this.container = Helper.create_element('div', {class: 'data-table-pagination'});
        this.draw();
    }

    redraw(new_records_count = - 1) {
        this.container.innerHTML = '';
        if (new_records_count !== -1) {
            this.records_count = new_records_count;
        }
        this.draw();
    }

    draw() {
        this.pages_count = Math.ceil(this.records_count / this.per_page);

        if (this.pages_count > 1) {
            const fragment = document.createDocumentFragment();

            if (this.pages_count <= 10) {
                const prev = this.__create_navigation_button(
                        '⬅',
                        this.current_page > 0 ? this.current_page - 1 : null,
                        this.current_page === 0
                        );
                fragment.appendChild(prev);

                for (let i = 0; i < this.pages_count; i++) {
                    const btn = this.__create_page_button(i + 1, i, i === this.current_page);
                    fragment.appendChild(btn);
                }

                const next = this.__create_navigation_button(
                        '➡',
                        this.current_page < this.pages_count - 1 ? this.current_page + 1 : null,
                        this.current_page === this.pages_count - 1
                        );
                fragment.appendChild(next);
            } else {
                const prev = this.__create_navigation_button(
                        '⬅',
                        this.current_page > 0 ? this.current_page - 1 : null,
                        this.current_page === 0
                        );
                fragment.appendChild(prev);

                // Add first pages if current page is not at the beginning
                if (this.current_page > 3) {
                    for (let i = 0; i < 3; i++) {
                        const btn = this.__create_page_button(i + 1, i);
                        fragment.appendChild(btn);
                    }
                    const dots = this.__create_dots();
                    fragment.appendChild(dots);
                }

                // Adding central pages
                let start = Math.max(0, this.current_page - 2);
                if (this.current_page > 3) {
                    start = Math.max(0, this.current_page - 1);
                }
                const end = Math.min(this.pages_count, this.current_page + 2);

                for (let i = start; i < end; i++) {
                    const btn = this.__create_page_button(i + 1, i, i === this.current_page);
                    fragment.appendChild(btn);
                }

                // Add last pages if current page is not at the end
                if (this.current_page < this.pages_count - 4) {
                    const dots = this.__create_dots();
                    fragment.appendChild(dots);
                    for (let i = this.pages_count - 3; i < this.pages_count; i++) {
                        const btn = this.__create_page_button(i + 1, i);
                        fragment.appendChild(btn);
                    }
                }

                const next = this.__create_navigation_button(
                        '➡',
                        this.current_page < this.pages_count - 1 ? this.current_page + 1 : null,
                        this.current_page === this.pages_count - 1
                        );
                fragment.appendChild(next);
            }

            this.container.appendChild(fragment);

            if (this.append) {
                this.wrapper.appendChild(this.container);
            } else {
                this.wrapper.prepend(this.container);
            }
        }
    }

    __create_page_button(text, page, is_current = false) {
        const btn = Helper.create_element('div', {class: 'data-table-pagination-btn'});

        if (is_current) {
            const span = Helper.create_element('span', {
                class: 'button button-disabled',
            }, text.toString());
            btn.appendChild(span);
        } else {
            const a = Helper.create_element('a', {
                href: '#',
                class: 'button button-primary',
                'data-page': page,
            }, text.toString(), {
                name: 'click',
                callback: () => {
                    Helper.cast('data-table-set-page', {
                        data: {
                            page,
                            table_instance_key: this.table_instance_key,
                        },
                    });

                    this.current_page = page;
                    this.redraw();
                },
            });
            btn.appendChild(a);
        }

        return btn;
    }

    __create_navigation_button(text, page, is_disabled = false) {
        const btn = Helper.create_element('div', {class: 'data-table-pagination-btn'});

        if (is_disabled) {
            const span = Helper.create_element('span', {
                class: 'button button-disabled',
            }, text);
            btn.appendChild(span);
        } else {
            const a = Helper.create_element('a', {
                href: '#',
                class: 'button button-primary',
                'data-page': page,
            }, text, {
                name: 'click',
                callback: () => {
                    Helper.cast('data-table-set-page', {
                        data: {
                            page,
                            table_instance_key: this.table_instance_key,
                        },
                    });

                    this.current_page = page;
                    this.redraw();
                },
            });
            btn.appendChild(a);
        }

        return btn;
    }

    __create_dots() {
        return Helper.create_element('span', {
            class: 'data-table-pagination-dots',
        }, '&nbsp;...&nbsp;&nbsp;');
    }

    hide() {
        if (this.container) {
            this.container.style.display = 'none';
        }
    }

    show() {
        if (this.container) {
            this.container.style.display = '';
        }
    }
}
