'use strict';
import Helper from './lib/helper.js';
import Cell from './cell.js';

//05-06-2025
export default class Header {
    constructor(table, wrapper, data) {
        this.table = table;
        this.table_instance_key = this.table.instance_key;
        this.wrapper = wrapper;
        this.data = data;
        this.cells = [];
        this.instance_key = Helper.generate_key('h-');//!! for attaching document events
        this.container = Helper.create_element('data-table-head');

        this.draw();
    }

    draw() {
        Object.values(this.data).forEach((data, counter) => {
            let elements = [];
            let can_move_left = false;
            let can_move_right = false;

            if (!this.table.data.attributes.stop_col_move) {
                if (counter > 0 && this.data.length > 1) {
                    can_move_left = true;
                }

                if (counter < this.data.length - 1) {
                    can_move_right = true;
                }
            }

            if (can_move_left) {
                elements.push(Helper.create_element('a', {
                    href: '#',
                    class: 'data-table-move-col-left'
                }, '<span class="icon-left-open"></span>', {
                    name: 'click',
                    callback: () => {
                        Helper.cast('data-table-move-col-left', {
                            data: {
                                key: data.key,
                                table_instance_key: this.table_instance_key
                            }
                        });
                    }
                }));
            }

            if (can_move_right) {
                elements.push(Helper.create_element('a', {
                    href: '#',
                    class: 'data-table-move-col-right'
                }, '<span class="icon-right-open"></span>', {
                    name: 'click',
                    callback: () => {
                        Helper.cast('data-table-move-col-right', {
                            data: {
                                key: data.key,
                                table_instance_key: this.table_instance_key
                            }
                        });
                    }
                }));
            }

            //+++

            if (data.order) {
                elements.push(Helper.create_element('a', {
                    href: '#',
                    class: `data-table-order-btn data-table-order-btn-${data.order.toLowerCase()}`,
                    'data-order': data.order.toLowerCase()
                }, '', {
                    name: 'click',
                    callback: (e) => {
                        let btn = e.target;

                        let order = btn.dataset.order;
                        let new_order = order === 'asc' ? 'desc' : 'asc';

                        Helper.cast('data-table-order-col-data', {
                            data: {
                                key: data.key,
                                order: new_order,
                                table_instance_key: this.table_instance_key
                            }
                        });

                        btn.classList.remove(`data-table-order-btn-${order}`);
                        btn.classList.add(`data-table-order-btn-${new_order}`);
                        btn.dataset.order = new_order;
                    }
                }));
            }

            //+++

            elements.push(this.create_mover());

            //+++

            const cell = this.cells[this.cells.length] = new Cell(this, this.container, this.data, 'title', `<span class="data-table-hf-cell">${data.value}</span>`, this.cells.length, -1, {
                elements: elements
            });

            cell.container.querySelector('.data-table-hf-cell').addEventListener('click', e => {
                let btn = null;

                if (btn = cell.container.querySelector('.data-table-order-btn')) {
                    btn.click();
                }

                return false;
            });

            if (data.callback) {
                data.callback(cell);
            }
        });

        //***

        this.wrapper.appendChild(this.container);
    }

    create_mover() {
        let can_move = false;
        let timer = null;

        let mover = Helper.create_element('div', {
            href: '#',
            class: 'data-table-col-width'
        }, '', {
            name: 'mousemove',
            callback: e => {
                //***
            }
        });

        //because mover added into cell container after this function return
        setTimeout(() => {
            let start = mover.offsetLeft;
            let elementStyle = mover?.parentNode?.style;
            let width_start = parseInt(elementStyle?.getPropertyValue(elementStyle?.[0]));//50%

            document.addEventListener('mousemove', e => {
                if (can_move) {
                    if (e.which === 1 && e.clientX > 0 && e.target === mover?.parentNode) {
                        let diff = start - e.layerX;
                        mover.style.setProperty('right', diff + 'px');

                        //let percentage = parseFloat((diff / start) * 100);
                        //let new_width = parseInt(width_start - percentage);
                        //mover.parentNode.style.setProperty("width", `calc(var(--width) - ${diff}px)`);
                    }
                    //+++

                    if (timer) {
                        clearInterval(timer);
                    }

                    timer = setTimeout(() => {
                        //todo: save data on server
                    });
                }
            });

            mover.addEventListener('mousedown', e => {
                can_move = true;
            });

            document.addEventListener('mouseup', e => {
                can_move = false;
            });
        }, 333);


        return mover;

    }
}

