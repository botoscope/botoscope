'use strict';
import Helper from '../lib/helper.js';
import Element from './element.js';
//02-08-2023
export default class Link extends Element {
    draw() {
        this.input = Helper.create_element('a', {
            href: 'javascript: void(0);'
        }, this.value.title ? this.value.title : this.value.value);

        if (Object.keys(this.value).length > 0) {
            for (const [key, value] of Object.entries(this.value)) {
                if (['title', 'action', 'element', 'value'].includes(key)) {
                    continue;
                }

                this.input.setAttribute(key, value);                
            }
        }
        
        this.input.classList.add('data-table-cell-link');
        this.wrapper.appendChild(this.input);
        return this.input;
    }
}
