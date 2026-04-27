'use strict';
import Calendar23 from './calendar23.js';
//<div class="calendar23-selector" data-date="1729807200" data-name="new_data"></div>
//31-07-2025
export default class Calendar23_Selector {
    constructor(selector = null, scene = 0, unix_time_stamp = null, placeholder = '', additional = {}) {
        let _this = this;
        this.scene = scene;
        this.unix_time_stamp = null;
        this.placeholder = placeholder;
        this.additional = additional;

        if (!selector) {
            if (!document.querySelectorAll('.calendar23-selector').length) {
                return;
            }

            document.querySelectorAll('.calendar23-selector').forEach(item => {
                this.init(item, unix_time_stamp);
            });
        } else {
            this.init(selector, unix_time_stamp);
        }

        //***

        this.input.addEventListener('focus', function (e) {
            e.stopPropagation();
            this.opened = Math.floor(Date.now() / 1000);//fix to avoid immidate calendar closing

            if (!_this.calendar_wrapper.style.display || _this.calendar_wrapper.style.display === 'none') {
                _this.calendar_wrapper.style.display = 'block';

                document.dispatchEvent(new CustomEvent('calendar23-date-focused', {detail: {calendar_id: _this.calendar_id}}));

            } else {
                _this.calendar_wrapper.style.display = 'none';
            }

            this.blur();
            return true;
        });

        document.addEventListener('click', e => {
            const now = Math.floor(Date.now() / 1000);

            if ((now - (this.opened || 0)) > 1000) {
                this.opened = now;//fix to avoid immidate calendar closing
                return true;
            }

            this.opened = 0;
            let close = true;

            if (e.target.closest('.calendar23-selector')) {
                close = false;
            }

            if (e.target.closest('.calendar23-month')) {
                close = false;
            }

            if (e.target.closest('.calendar23-calendar-wrapper')) {
                close = false;
            }

            if (close) {
                this.calendar_wrapper.style.display = 'none';
            }
        });

        document.addEventListener('calendar23-date-selected', e => {
            e.stopPropagation();
            if (this.calendar_id === e.detail.calendar_id) {
                let unix_time_stamp = e.detail.date;
                let hour = e.detail.hour??0;
                let minute = e.detail.minute??0;

                const date = new Date(unix_time_stamp * 1000);

                date.setUTCHours(hour);
                date.setUTCMinutes(minute);
                date.setUTCSeconds(0);

                unix_time_stamp = Math.floor(date.getTime() / 1000);
                this.input.setAttribute('data-selected-date', unix_time_stamp);
                this.input.setAttribute('value', unix_time_stamp);
                this.set_input_value(unix_time_stamp, hour, minute);
                this.calendar_wrapper.style.display = 'none';

                //***

                this.unix_time_stamp = unix_time_stamp;
                this.selected();
                document.dispatchEvent(new CustomEvent('calendar23-selector-date-selected', {detail: {date: unix_time_stamp, hour, minute, selector: this}}));
            }
        });
    }

    create_id(prefix = '') {
        return prefix + Math.random().toString(36).substring(7);
    }

    selected() {
        //should be reloaded by business logic!!
    }

    init(item, unix_time_stamp) {

        this.draw_html(item);

        //***

        this.input = item.querySelector('.calendar23-data-input');
        this.calendar_wrapper = item.querySelector('.calendar23-calendar-wrapper');

        //close another calendars if current one is focused
        document.addEventListener('calendar23-date-focused', (e) => {
            if (e.detail.calendar_id !== this.calendar_id) {
                this.calendar_wrapper.style.display = 'none';
            }
        });

        this.calendar_id = this.create_id('calendar23-');//for get selected

        //***

        if (this.input.getAttribute('data-selected-date').length > 0) {
            this.unix_time_stamp = parseInt(this.input.getAttribute('data-selected-date'), 10);
        }

        if (unix_time_stamp) {
            this.unix_time_stamp = unix_time_stamp;//more prioritet
        }

        if (this.unix_time_stamp) {
            const date = new Date(Date.UTC(
                    new Date(unix_time_stamp * 1000).getUTCFullYear(),
                    new Date(unix_time_stamp * 1000).getUTCMonth(),
                    new Date(unix_time_stamp * 1000).getUTCDate(),
                    new Date(unix_time_stamp * 1000).getUTCHours(),
                    new Date(unix_time_stamp * 1000).getUTCMinutes(),
                    new Date(unix_time_stamp * 1000).getUTCSeconds()
                    ));

            this.set_input_value(this.unix_time_stamp, date.getUTCHours(), date.getUTCMinutes());
        }

        //***

        this.calendar = new Calendar23(this.calendar_wrapper.querySelector('div'), this.scene, this.calendar_id, this.unix_time_stamp, this.additional);

    }

    set_input_value(unix_time_stamp, hour, minute) {
        const locale = navigator.language || 'en-US';
        this.input.value = new Date(unix_time_stamp * 1000).toLocaleDateString(locale, {
            timeZone: 'UTC',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        });

        if (this.additional.show_time) {
            let hh = hour > 10 ? hour : `0${hour}`;
            let mm = minute > 10 ? minute : `0${minute}`;

            this.input.value += ` ${hh}:${mm}`;
        }
    }

    draw_html(container) {
        let input = document.createElement('input');
        input.setAttribute('type', 'text');
        input.setAttribute('readonly', 'readonly');
        input.className = 'calendar23-data-input';
        input.setAttribute('data-selected-date', '');
        input.setAttribute('value', '');
        input.setAttribute('placeholder', this.placeholder);

        if (container.hasAttribute('data-date')) {
            input.setAttribute('data-selected-date', container.getAttribute('data-date'));
        }

        if (container.hasAttribute('data-name')) {
            input.setAttribute('name', container.getAttribute('data-name'));
        }

        //***

        let wrapper = document.createElement('div');
        wrapper.className = 'calendar23-calendar-wrapper';

        let div = document.createElement('div');

        wrapper.appendChild(div);
        container.appendChild(input);

        //clear button
        let a = document.createElement('a');
        a.setAttribute('href', '#');
        a.className = 'calendar23-clear-btn';
        a.innerHTML = 'clear';
        container.appendChild(a);

        a.addEventListener('click', e => {
            e.preventDefault();
            this.input.value = null;
            this.unix_time_stamp = null;
            this.selected();
            return false;
        });

        container.appendChild(wrapper);
    }
}

