let Functions, Helper, Table;
//26-06-2025
export default class BookingSlots {
    constructor(parent_table, parent_product_id, parent_sidebar = null, parent_call_btn = null) {
        this.parent_table = parent_table;
        this.parent_product_id = parent_product_id;
        this.parent_call_btn = parent_call_btn;
        this.parent_sidebar = parent_sidebar;
        this.overlap_check_timer = null;
        this.selected_weekday = 1;
        this.init();
    }

    async init() {
        [Functions, Helper, Table] = await loadModules();
    }

    async draw(raw_rows_data = null) {
        const [Functions, Helper, Table] = await loadModules();
        const wrapper = this.parent_sidebar.content_container.querySelector('#botoscope_product_booking_slots_wrapper');

        const table_slug = 'booking_slots';
        if (!raw_rows_data) {
            raw_rows_data = JSON.parse(wrapper.textContent);
        }

        const data = {
            attributes: {
                class: table_slug,
                'data-data-table': table_slug,
                'data-order': 'asc',
                'data-order-by': 'start_h',
                'data-per-page': -1,
                'data-records-count': -1,
                id: `the_table_${table_slug}`,
                stop_col_move: true
            },
            header: [
                {
                    value: botoscope_lang.start_h,
                    width: '18%',
                    key: 'start_h'
                },
                {
                    value: botoscope_lang.start_m,
                    width: '18%',
                    key: 'start_m'
                },
                {
                    value: botoscope_lang.finish_h,
                    width: '18%',
                    key: 'finish_h'
                },
                {
                    value: botoscope_lang.finish_m,
                    width: '18%',
                    key: 'finish_m'
                },
                {
                    value: botoscope_lang.price,
                    width: '10%',
                    key: 'price'
                },
                {
                    value: botoscope_lang.capacity,
                    width: '10%',
                    key: 'capacity'
                },
                {
                    value: botoscope_lang.delete,
                    width: '8%',
                    key: 'delete'
                }
            ],
            raw_rows_data,
            format_data: function (rd) {
                const {id, is_active, ...rest} = rd;
                return {
                    ...rest,
                    is_active: {
                        el_type: "switcher",
                        value: is_active
                    },
                    extra: {
                        editable: ['start_h', 'start_m', 'finish_h', 'finish_m', 'price', 'capacity'],
                        id: id
                    }
                };
            }
        };
        wrapper.innerHTML = '';
        wrapper.style.display = 'block';
        const table = new Table(wrapper, data, table_slug, {
            cell_content_drawn: async data => {

                const cell = data.cell;
                const id = data.extra.id;
                switch (data.key) {
                    case 'start_h':
                    case 'finish_h':
                    case 'finish_m':
                    case 'start_m':
                        {
                            if (raw_rows_data.length === 0) {
                                return;
                            }

                            let value = parseInt(cell.value);
                            let update = false;
                            let max = 23;
                            if (data.key === 'finish_m' || data.key === 'start_m') {
                                max = 59;
                            }

                            if (!Functions.isNumeric(cell.value)) {
                                value = 0;
                                update = true;
                            }

                            if (value < 0) {
                                value = 0;
                                update = true;
                            }

                            if (value > max) {
                                value = max;
                                update = true;
                            }

                            if (update) {
                                cell.draw_content(value);
                                setTimeout(() => cell.set_value(value), 111);
                            }

                            if (value < 10) {
                                cell.draw_content(`0${value}`);
                            }

                            this.check_rows_overlap(table);
                        }
                        break;
                    case 'delete':
                        {
                            let btn = Helper.create_element('a', {
                                href: '#',
                                class: 'button button-primary'
                            }, 'X', {
                                name: 'click',
                                callback: e => {
                                    if (confirm(botoscope_lang.are_you_sure)) {
                                        table.remove_row(id);
                                        Functions.message(botoscope_lang.loading, 'warning', -1);
                                        Helper.ajax('botoscope_booking_delete_slot', {
                                            product_id: this.parent_product_id,
                                            id
                                        }, res => {
                                            Functions.message(botoscope_lang.saved);
                                            table.records_count -= 1;
                                            //this.parent_call_btn.innerText = this.parent_call_btn.innerText.replace(/\(\d+\)/, `(${table.records_count})`);
                                            //table.redraw();
                                        }, false);
                                    }
                                }
                            });
                            cell.set_node(btn);
                        }
                        break;
                }
            }
        });
        //+++

        table.data_is_mutated = (operation, data) => {
            switch (operation) {
                case 'edit_cell':
                    Functions.edit_table_cell(data, table_slug, {product_id: this.parent_product_id});
                    break;
                case 'move_col_right':
                case 'move_col_left':
                    table.set_table_col_positions(data.positions, data.key, data.index);
                    break;
            }
        };
        //+++

        const add_slots_btn = Helper.create_element('a', {
            href: '#',
            class: 'botoscope_products_add_attachments tips'
        }, botoscope_lang.add, {
            name: 'click',
            callback: (e) => {
                Functions.message(botoscope_lang.loading, 'warning', -1);
                Helper.ajax('botoscope_booking_create_slot', {
                    product_id: this.parent_product_id,
                    weekday: this.selected_weekday
                }, async  (res) => {
                    Functions.message(botoscope_lang.saved);
                    this.draw(Object.values(res.data));
                    //this.parent_call_btn.innerText = this.parent_call_btn.innerText.replace(/\(\d+\)/, `(${Object.values(res.data).length})`);
                });
            }
        });
        wrapper.appendChild(add_slots_btn);


        //+++

        if (raw_rows_data.length === 0) {

            const weekdays = {1: botoscope_lang.mon, 2: botoscope_lang.tue, 3: botoscope_lang.wed,
                4: botoscope_lang.thu, 5: botoscope_lang.fri, 6: botoscope_lang.sat, 7: botoscope_lang.sun};

            delete weekdays[this.selected_weekday];

            const week_sel = Helper.create_html_select(weekdays, data.value, {
                class: '',
                style: 'width: 150px; display: inline-block !important; margin: 5px 5px 0 0;'
            }, {
                name: 'change',
                callback:
                        async e => {
                            //+++
                        }
            });

            wrapper.appendChild(week_sel);

            const b = Helper.create_element('a', {
                href: '#',
                class: 'botoscope-button',
                style: 'margin-top: 5px; display: inline-block !important;'
            }, botoscope_lang.clone, {
                name: 'click',
                callback: (e) => {
                    Functions.message(botoscope_lang.loading, 'warning', -1);
                    Helper.ajax('botoscope_booking_clone_slots', {
                        product_id: this.parent_product_id,
                        weekday: this.selected_weekday,
                        copy_from: week_sel.value
                    }, async  (res) => {
                        Functions.message(botoscope_lang.saved);
                        this.draw(Object.values(res.data));
                    });
                }
            });

            wrapper.appendChild(b);
    }

    }

    draw_weekdays_buttons() {
        //lets draw week days buttons
        const weekdays_wrapper = this.parent_sidebar.content_container.querySelector('#botoscope-booking-weekdays-list');
        const weekdays = [botoscope_lang.mon, botoscope_lang.tue, botoscope_lang.wed, botoscope_lang.thu, botoscope_lang.fri, botoscope_lang.sat, botoscope_lang.sun];

        weekdays.forEach((wd, i) => {
            let li = Helper.create_element('li');
            li.style.order = i;

            let a = Helper.create_element('a');
            a.href = '#';
            a.innerHTML = wd;
            a.className = 'botoscope-button';

            if (i === 0) {
                a.classList.add('selected');
            }
            li.appendChild(a);
            weekdays_wrapper.appendChild(li);

            a.addEventListener('click', e => {
                e.preventDefault();
                const aa = e.target.closest('ul').querySelectorAll('a');

                for (let link of aa) {
                    link.classList.remove('selected');
                }

                a.classList.add('selected');
                this.selected_weekday = i + 1;

                Functions.message(botoscope_lang.loading, 'warning', -1);
                Helper.ajax('botoscope_booking_get_slots', {
                    product_id: this.parent_product_id,
                    weekday: this.selected_weekday
                }, async res => {
                    Functions.message(botoscope_lang.done);
                    this.draw(Object.values(res.data));
                });

                return false;
            });
        });
    }

    toMinutes(h, m) {
        return parseInt(h, 10) * 60 + parseInt(m, 10);
    }

    getOverlappingSlotIds(slots) {
        const conflicts = new Set();

        // Preparation: convert to numerical ranges and filter by validity
        const ranges = slots.map(s => {
            const from = this.toMinutes(s.start_h, s.start_m);
            const to = this.toMinutes(s.finish_h, s.finish_m);
            return {
                id: parseInt(s.id),
                from,
                to
            };
        });

        // Mark invalid ranges: start >= end
        ranges.forEach(range => {
            if (range.from >= range.to) {
                conflicts.add(range.id);
            }
        });

        // Checking intersections
        for (let i = 0; i < ranges.length; i++) {
            for (let j = i + 1; j < ranges.length; j++) {
                const a = ranges[i];
                const b = ranges[j];

                if (a.from < b.to && b.from < a.to) {
                    conflicts.add(a.id);
                    conflicts.add(b.id);
                }
            }
        }

        return Array.from(conflicts);
    }

    check_rows_overlap(table) {
        if (this.overlap_check_timer) {
            clearTimeout(this.overlap_check_timer);
        }

        this.overlap_check_timer = setTimeout(() => {
            const intersept = this.getOverlappingSlotIds(table.data.raw_rows_data);
            if (intersept.length > 0) {
                table.rows.forEach(row => {
                    if (intersept.includes(parseInt(row.data.extra.id))) {
                        row.container.classList.add('booking_slot_overlap');
                    }
                });
            }
        }, 777);
    }

}

async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js'),
        import(botoscope_url + 'assets/js/lib/sidebar.js')
    ]);
    return modules.map(mod => mod.default || mod);
}

