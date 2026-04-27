'use strict';
import Helper from '../lib/helper.js';
import Element from './element.js';
//02-08-2023
export default class Select extends Element {
    draw() {
        this.input = Helper.create_element('select');

        for (const [value, title] of Object.entries(this.options)) {

            let option = Helper.create_element('option', {
                value: value
            }, title.toString());
            if (this.value == value) {
                option.setAttribute('selected', true);
            }

            this.input.appendChild(option);
        }

        this.wrapper.appendChild(this.input);
        return this.input;
    }
}
