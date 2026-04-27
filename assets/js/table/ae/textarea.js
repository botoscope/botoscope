'use strict';
import Helper from '../lib/helper.js';
import Element from './element.js';
//27-02-2025
export default class Textarea extends Element {
    constructor(key, value, wrapper, params) {
        super(key, value, wrapper, params);
    }

    draw() {
        this.input = Helper.create_element('textarea', {
            form: 'fakeForm',
            placeholder: ''
        }, this.value);

        const wrapper = document.createElement('div');
        wrapper.className = 'input-div-wrapper';
        wrapper.appendChild(this.input);
        
        //+++

        const save = document.createElement('a');
        save.className = 'textarea-save-btn button button-primary';
        save.innerHTML = `<svg width="23" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"></polyline>
    </svg>`;

        save.addEventListener('click', e => {
            let event = new KeyboardEvent('keyup', {
                bubbles: true,
                cancelable: true,
                key: 'Enter',
                keyCode: 13,
                which: 13,
                ctrlKey: true
            });

            this.input.dispatchEvent(event);
        });


        wrapper.appendChild(save);
        
        //+++
        
        const cancel = document.createElement('a');
        cancel.className = 'textarea-cancel-btn button button-primary';
        cancel.innerText = 'x';

        cancel.addEventListener('click', e => {
            let event = new KeyboardEvent('keyup', {
                bubbles: true,
                cancelable: true,
                key: 'Enter',
                keyCode: 27,
                which: 27
            });

            this.input.dispatchEvent(event);
        });


        wrapper.appendChild(cancel);
        
        //+++

        this.wrapper.appendChild(wrapper);
        this.input.focus();
        return this.input;
    }
}
