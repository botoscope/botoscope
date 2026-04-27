'use strict';
import Helper from './lib/helper.js';
import Table from './table.js';
import Input from './ae/input.js';
import Checkbox from './ae/checkbox.js';
import Switcher from './ae/switcher.js';
import Color from './ae/color.js';
//import Image from './ae/image.js';
//import Ranger from './ae/ranger.js';
import Select from './ae/select.js';
import Link from './ae/link.js';
import Textarea from './ae/textarea.js';

//04-07-2025
export default class Cell {
    constructor(table, wrapper, header_data, key, value, index, row_index = - 1, extra = {}) {
        this.table = table;
        this.wrapper = wrapper;
        this.header_data = header_data;
        this.key = key;

        if (value === null) {
            value = '';//fix for null as it is object for js
        }

        this.value = value;
        this.index = index;
        this.row_index = row_index;
        this.extra = extra;

        this.columns_count = this.header_data.length;
        this.instance_key = Helper.generate_key('c-');//!! for attaching document events
        this.draw();
    }

    redraw() {
        this.container.innerHTML = '';
        this.draw_elements();
    }

    draw() {
        this.container = Helper.create_element('data-table-cell');
        this.container.dataset.key = this.key;

        if (this.header_data && this.header_data[this.index]) {
            if (this.header_data[this.index].width) {
                this.width = this.header_data[this.index].width;
            }

            if (this.header_data[this.index].action) {
                this.action = this.header_data[this.index].action;
            }
        }

        if (!this.width) {
            this.width = parseFloat(100 / this.columns_count) + '%';
        }

        this.container.style.setProperty('--width', this.width);

        this.draw_elements();

        this.wrapper.appendChild(this.container);

        if (this.extra.elements) {
            this.extra.elements.forEach(element => {
                this.container.appendChild(element);
            });
        }

        //cell css apply
        if (this.extra && this.extra.cell_css) {
            for (const [key, value] of Object.entries(this.extra.cell_css)) {
                this.container.style.setProperty(key, value);
            }
        }

        this.activate_editable();
    }

    draw_elements() {
        switch (typeof this.value) {
            case 'function':

                setInterval(() => {
                    this.draw_content(this.value(this));//custom items drawned by callback
                }, 111);

                break;

            case 'object':
                if (!this.value.callback) {
                    this.container.innerHTML = ''
                    this.draw_active_element(this.value.el_type, this.value);//active element
                } else {
                    this.value.callback(this);
                }
                break;

            default:
                //simple string
                if (typeof this.value === 'string' && this.value) {
                    this.draw_content(this.value.replace(/,(\S)/g, ', $1'));
                } else {
                    this.draw_content(this.value);
                }

                break;
        }

        this.cast('data-table-cell-content-drawn');

        this?.table?.callbacks?.cell_content_drawn?.({
            cell: this,
            value: this.value,
            key: this.key,
            extra: this.extra,
            cell_index: this.index,
            table: this.table
        });
    }

    activate_editable() {
        if (this.avoid_click_edit) {
            return;
        }

        if (this?.extra?.editable && this.extra.editable.includes(this.key)) {
            this.container.addEventListener('click', e => {
                if (e.target === this.container) {
                    this.draw_content('');
                    let input = this.draw_active_element(this.editable_type || this.extra.editable_types?.[this.key] || 'input');
                    input?.input.setSelectionRange(-1, -1);//set focus on the string value end
                }
            });

            this.container.classList.add('data-table-is-editable');
        }
    }

    draw_content(value) {
        this.container.innerHTML = value;
    }

    clear() {
        this.draw_content('');
    }

    append_node(node) {
        if (node instanceof Node) {
            this.container.appendChild(node);
        }

        return node;
    }

    set_node(node) {
        if (node instanceof Node) {
            this.clear();
            this.append_node(node);
        }
    }

    async set_value(value, cast = true) {
        this.value = value;
        this.refresh(this.key, this.value);

        if (cast) {
            this.cast('data-table-cell-data-changed');
        }
    }

    //if cell value changed somewhere, here we can update (data and view)
    update(value) {
        this.set_value(value);
        this.draw_content(value);
        this.draw_elements();
    }

    draw_active_element(element_type, data = null) {
        //this.container.innerHTML = '';//!we can have more than one elemnt in the cell
        let value = null;

        if (data?.value) {
            value = data.value;
        } else {
            value = this.value;
        }


        if (typeof value === 'object') {
            value = value.value;
        }

        switch (element_type) {
            case 'input':
                {
                    let input = new Input(this.id, value, this, {type: data?.type});

                    input.setEvent('keyup', (e, input) => {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();

                        //enter
                        if (e.keyCode === 13) {
                            this.set_value(input.value);
                            if (Boolean(this?.extra.editable)) {
                                this.draw_content(input.value);
                                this.table.redraw();
                            }

                            input.blur();
                        }

                        //escape
                        if (e.keyCode === 27) {
                            if (Boolean(this?.extra.editable)) {
                                this.draw_content(this.value);
                            } else {
                                input.value = this.value;
                            }

                            this.table.redraw();
                        }

                        return false;
                    });

                    return input;
                }
                break;

            case 'text':
            case 'email':
                {
                    let input = new Input(this.id, value, this, {type: element_type});
                    input.setEvent('change', (e, input) => {
                        this.set_value(input.value);
                    });
                }
                break;

            case 'textarea':
                {
                    let input = new Textarea(this.id, value, this, {type: 'text'});
                    input.setEvent('change', (e, input) => {
                        this.set_value(input.value);
                    });

                    input.setEvent('keyup', (e, input) => {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();

                        //enter
                        if (e.keyCode === 13) {
                            if (e.ctrlKey) {
                                this.set_value(input.value);
                                if (Boolean(this?.extra.editable)) {
                                    this.draw_content(input.value);
                                    this.table.redraw();
                                }
                            } else {
                                // If Ctrl is pressed, add a new line
                                const cursorPosition = input.selectionStart;
                                const value = input.value;
                                input.value = value.slice(0, cursorPosition) + '\n' + value.slice(cursorPosition);
                                input.setSelectionRange(cursorPosition + 1, cursorPosition + 1); // Move the cursor
                            }
                        }

                        //escape
                        if (e.keyCode === 27) {
                            if (Boolean(this?.extra.editable)) {
                                this.draw_content(this.value);
                            } else {
                                input.value = this.value;
                            }

                            this.table.redraw();
                        }

                        return true;
                    });
                }
                break;

            case 'number':
                {
                    let input = new Input(this.id, value, this, {type: 'number'});
                    input.setEvent('input', (e, input) => {
                        this.set_value(input.value);
                    });


                    input.setEvent('keyup', (e, input) => {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();

                        //enter
                        if (e.keyCode === 13) {
                            this.set_value(input.value);
                            this.draw_content(input.value);
                            this.table.redraw();
                        }

                        //escape
                        if (e.keyCode === 27) {
                            this.draw_content(this.value);
                            this.table.redraw();
                        }

                        return false;
                    });
                }
                break;

            case 'checkbox':

                (new Checkbox(this.id, value, this)).setEvent('click', (e, input) => {
                    this.set_value(input.checked ? 1 : 0);
                });

                break;

            case 'switcher':
                let yes = 1;
                let no = 0;
                let action = '';
                let type = '';
                let value_is_special = false;

                if (typeof data.value === 'object' && 'yes' in data.value) {
                    yes = data.value.yes;
                    value_is_special = true;
                }

                if (typeof data.value === 'object' && 'no' in data.value) {
                    no = data.value.no;
                }

                if (typeof data.value === 'object' && 'action' in data.value) {
                    action = data.value.action;
                }

                if (typeof data.value === 'object' && 'type' in data.value) {
                    type = data.value.type;
                }

                if (!value_is_special) {
                    value = parseInt(value);
                } else {
                    if (value === yes) {
                        value = 1;
                    } else {
                        value = 0;
                    }
                }

                if (!data.callback) {
                    (new Switcher(this.id, value, this, type, action)).setEvent('click', (e, input) => {
                        this.set_value(input.checked ? yes : no);
                    });
                } else {
                    (new Switcher(this.id, value, this, type, action)).setEvent('click', (e, input) => data.callback(input.checked ? yes : no));
                }

                break;


            case 'color':

                (new Color(this.id, value, this)).setEvent('input', (e, input) => {
                    this.set_value(input.value);
                });

                break;

            case 'ranger':
                {
                    let el = Helper.create_element('div', {class: 'ranger23-track'});

                    for (const [kk, vv] of Object.entries({
                        'data-min': this.value.value.min,
                        'data-max': this.value.value.max,
                        'data-selected-min': value[0],
                        'data-selected-max': value[1]
                    })) {
                        el.setAttribute(kk, vv);
                    }

                    let slider = new Ranger(el, null, 30, {
                        instant_cast: true,
                        disable_handler_left: this.value.value.solo
                    });

                    slider.draw_inputs(this.container);//num inputs

                    let slider_timer = null;
                    slider.onSelect = () => {
                        if (slider_timer) {
                            clearTimeout(slider_timer);
                        }

                        slider_timer = setTimeout(() => {
                            this.set_value(slider.value);
                        }, 333);
                    }

                    this.container.appendChild(el);
                }
                break;

            case 'select':

                if (Object.values(this.value.value.options).length > 0) {

                    let select = new Select(this.id, value, this, this.value.value);
                    select.setEvent('change', (e, input) => {
                        this.set_value(input.value);
                    });

                } else {
                    console.log('Select should has options!');
                }

                break;

            case 'link':

                if (!data.callback) {
                    (new Link(this.id, data.value, this.container)).setEvent('click', (e, input) => {
                        //this.set_value(input.value);
                        this.set_value(this.extra.id);
                    });
                } else {
                    (new Link(this.id, data.value, this.container)).setEvent('click', (e, input) => data.callback(this.extra.id));
                }

                break;


            case 'image':
                {
                    //todo
                    let image = new Image(this.id, value, this);
                    //image.title = this.row?.data[1]?.value;
                    image.setEvent('change', (e, input) => {
                        this.set_value(input.value);
                    });
                }
                break;

            default:
                //console.warn(`Type ${element_type} doesn exists!`);
                break;
    }
    }

    cast(event_name) {
        Helper.cast(event_name, {
            data: {
                cell: this,
                value: this.value,
                key: this.key,
                extra: this.extra,
                cell_index: this.index,
                table_instance_key: this.table.instance_key
            }
        });
    }

    //!!
    refresh(key, new_data) {
        this.table.data.raw_rows_data[this.row_index][key] = new_data;
        this.table.data.rows[this.row_index][key] = new_data;
    }

    get_sibling_value(key) {
        return this.table.data.rows[this.row_index][key];
    }
}
