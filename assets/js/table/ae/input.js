'use strict';
import Helper from '../lib/helper.js';
import Element from './element.js';
//03-03-2025
export default class Input extends Element {
    constructor(key, value, wrapper, params) {
        super(key, value, wrapper, params);
    }

    draw() {
        this.input = Helper.create_element('input', {
            form: 'fakeForm',
            enterkeyhint: 'done',
            tabindex: -1,
            type: this.type && this.type !== 'undefined' ? this.type : 'text',
            value: this.value
        });

        if (this?.type && this.type === 'number') {
            this.input.value = parseFloat(this.value);

            if (this.cell?.value?.value?.min) {
                this.input.setAttribute('min', this.cell.value.value.min);
            }

            if (this.cell?.value?.value?.max) {
                this.input.setAttribute('max', this.cell.value.value.max);
            }

            if (this.cell?.value?.value?.step) {
                this.input.setAttribute('step', this.cell.value.value.step);
            }
        }

        const wrapper = document.createElement('div');
        //wrapper.setAttribute('onsubmit', 'return false;');
        wrapper.className = 'input-div-wrapper';
        wrapper.appendChild(this.input);

        this.wrapper.appendChild(wrapper);
        this.input.focus();
        setTimeout(() => this.input.select(), 77);
        return this.input;
    }
}
