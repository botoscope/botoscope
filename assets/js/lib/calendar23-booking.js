'use strict';
import Calendar23 from './calendar23.js';
//<div id="calendar1" class="booking-calendar"></div>
//new Calendar23_Booking(document.getElementById('calendar1'), 0);
//11-06-2025
export default class Calendar23_Booking extends Calendar23 {

    constructor(container, scene = 0, calendar_id = null, unix_time_stamp = null, additional = {}) {
        super(container, scene, calendar_id, unix_time_stamp, additional);
    }

    focus(cell) {
        super.focus(cell);
        this.scene = 3;
        this.fill_container();
    }

    fill_scene() {
        let container = document.createElement('div');
        container.className = 'calendar23-month';
        container.appendChild(this.create_label());
        this.apply_scene(container, this.scene);
        this.container.appendChild(container);
    }

    apply_scene(container, num) {
        //reload
    }

    create_label() {
        let label = document.createElement('div');
        label.className = 'calendar23-label calendar23-label-booking-day';


        switch (this.scene) {
            case 3:

                let span = document.createElement('span');
                span.innerText = `${this.month_names[this.selected_date.getMonth()]} ${this.selected_date.getDate()}, ${this.selected_date.getFullYear()}`;
                label.appendChild(span);

                //***

                let prev = document.createElement('a');
                prev.setAttribute('href', '#');
                prev.className = 'calendar23-prev';
                prev.addEventListener('click', e => {
                    e.stopPropagation();
                    e.preventDefault();
                    this.scene = 0;
                    this.fill_container();
                    return false;
                });

                label.appendChild(prev);

                break;
        }


        return label;
    }

    rich_cell_content(cell) {
        const date = new Date(parseInt(cell.dataset.date) * 1000);
        const utcDay = date.getUTCDate();

        if (this.count_data && this.count_data[utcDay] > 0) {
            let span = document.createElement('span');
            span.className = 'booking-cell-count';
            span.innerHTML = this.count_data[utcDay];
            cell.appendChild(span);
        }
    }
}

