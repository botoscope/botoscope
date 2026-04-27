let Functions, Helper, Table;
//17-03-2026
export default class BookingSlotsTargeted {
    constructor() {
        this.init();
    }

    async init() {
        this.draw();
    }

    async draw() {
        if (botoscope_is_no_cart || botoscope_no_bot) {
            return false;
        }

        const [Functions, Helper, Table, Switcher, Calendar23, BookingCalendar, BookingSlots, Sidebar] = await loadModules();

        let slug = 'booking_slots_targeted';
        let wrapper = document.getElementById(`botoscope-${slug}-w`);
        let raw_rows_data = [];
        let start_time, end_time, product_id = 0;

        let data = {
            attributes: {
                class: slug,
                'data-data-table': slug,
                'data-order': 'desc',
                'data-order-by': 'id',
                'data-per-page': -1,
                'data-records-count': -1,
                id: `the_table_${slug}`,
                stop_col_move: true
            },
            header: [
                {
                    value: botoscope_lang.start_h,
                    width: '15%',
                    key: 'start_h'
                },
                {
                    value: botoscope_lang.start_m,
                    width: '15%',
                    key: 'start_m'
                },
                {
                    value: botoscope_lang.finish_h,
                    width: '15%',
                    key: 'finish_h'
                },
                {
                    value: botoscope_lang.finish_m,
                    width: '15%',
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
                    value: botoscope_lang.active,
                    width: '10%',
                    key: 'is_active'
                },
                {
                    value: botoscope_lang.hold,
                    width: '10%',
                    key: 'hold'
                }
            ],
            raw_rows_data,
            format_data: function (rd) {
                const {id, is_active, ...rest} = rd;

                return {
                    ...rest,
                    is_active: {
                        //el_type: "switcher",
                        value: is_active
                    },
                    extra: {
                        editable: ['start_h', 'start_m', 'finish_h', 'finish_m', 'price', 'capacity'],
                        id: id
                    }
                };
            }
        };

        //+++

        wrapper.innerHTML = '';
        let table = new Table(wrapper, data, slug, {
            cell_content_drawn: data => {

                const parent_cell = data.cell;

                switch (data.key) {
                    case 'hold':

                        if (parseInt(parent_cell.get_sibling_value('is_reserved'))) {
                            if (parseInt(parent_cell.get_sibling_value('order_id')) > 0) {
                                parent_cell.draw_content(`<a href="/wp-admin/post.php?post=${parent_cell.get_sibling_value('order_id')}&action=edit" target="_blank">&lt;${parent_cell.get_sibling_value('order_id')}&gt;</a>`);
                            } else {
                                parent_cell.draw_content('-');
                            }
                        } else {
                            let icon = 'icon-play';
                            parent_cell.set_node(Helper.create_element('a', {
                                href: '#',
                                class: 'button button-primary'
                            }, `<span class="${icon}"></span>`, {
                                name: 'click',
                                callback: async e => {
                                    const button = e.target;
                                    const row_id = parseInt(data.extra.id);

                                    let sidebar = new Sidebar(slug, row_id, botoscope_lang.hold_customer, botoscope_is_mobile ? '100%' : '97%');

                                    //console.log(data.table.data.raw_rows_data);

                                    sidebar.after_set = async () => {
                                        Functions.message(botoscope_lang.loading, 'warning', -1);
                                        Helper.ajax('botoscope_booking_get_users', {}, async  (res) => {
                                            Functions.message(botoscope_lang.done);
                                            let table_slug = 'booking_users';

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
                                                        value: 'ID',
                                                        width: '10%',
                                                        key: 'oid'
                                                    },
                                                    {
                                                        value: botoscope_lang.title,
                                                        width: '70%',
                                                        key: 'display_name'
                                                    },
                                                    {
                                                        value: botoscope_lang.select,
                                                        width: '20%',
                                                        key: 'select'
                                                    }
                                                ],
                                                raw_rows_data: res.data,
                                                format_data: function (rd) {
                                                    const {id, is_active, ...rest} = rd;
                                                    return {
                                                        ...rest,
                                                        is_active: {
                                                            el_type: "switcher",
                                                            value: is_active
                                                        },
                                                        extra: {
                                                            editable: [],
                                                            id: id
                                                        }
                                                    };
                                                }
                                            };


                                            sidebar.content_container.innerHTML = '';

                                            const search_input = Helper.create_element('input', {
                                                type: 'search',
                                                style: 'width: 100%; box-sizing: border-box;',
                                                class: ''
                                            }, '', {
                                                name: 'input',
                                                callback: e => {
                                                    e.stopPropagation();
                                                    const searchText = e.target.value.toLowerCase().trim();

                                                    // Find all the rows in the table
                                                    const tableRows = document.querySelectorAll('#the_table_booking_users data-table-row');

                                                    tableRows.forEach(row => {
                                                        // Find the cell with product_title
                                                        const titleCell = row.querySelector('data-table-cell[data-key="display_name"]');

                                                        if (titleCell) {
                                                            const titleText = titleCell.textContent.toLowerCase();

                                                            if (searchText === '' || titleText.includes(searchText)) {
                                                                row.style.display = '';
                                                            } else {
                                                                row.style.display = 'none';
                                                            }
                                                        }
                                                    });

                                                    return false;
                                                }
                                            });
                                            
                                            sidebar.content_container.appendChild(search_input);
                                            
                                            //+++

                                            const users_table = new Table(sidebar.content_container, data, table_slug, {
                                                cell_content_drawn: async data => {

                                                    const cell = data.cell;
                                                    const id = data.extra.id;
                                                    switch (data.key) {
                                                        case 'select':
                                                            {
                                                                let btn = Helper.create_element('a', {
                                                                    href: '#',
                                                                    class: 'button button-primary'
                                                                }, `<span class="${icon}"></span>`, {
                                                                    name: 'click',
                                                                    callback: e => {
                                                                        if (confirm(botoscope_lang.are_you_sure)) {
                                                                            sidebar.close();
                                                                            Functions.message(botoscope_lang.loading, 'warning', -1);
                                                                            Helper.ajax('botoscope_booking_reserve_slot', {
                                                                                hash_key: parent_cell.get_sibling_value('hash_key')??'',
                                                                                user_id: id,
                                                                                slot_id: parent_cell.extra.id,
                                                                                product_id: parent_cell.get_sibling_value('product_id'),
                                                                                start_h: parent_cell.get_sibling_value('start_h'),
                                                                                start_m: parent_cell.get_sibling_value('start_m'),
                                                                                finish_h: parent_cell.get_sibling_value('finish_h'),
                                                                                finish_m: parent_cell.get_sibling_value('finish_m'),
                                                                                start_time
                                                                            }, (answer) => {
                                                                                //console.log(answer);
                                                                                Functions.message(botoscope_lang.done);
                                                                                booking_calendar_container_slots.querySelector('.calendar23-focused').click();
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

                                        });
                                    };


                                    sidebar.after_set();
                                }
                            }));
                        }
                        break;

                    case 'start_h':
                    case 'finish_h':
                    case 'finish_m':
                    case 'start_m':
                        {
                            if (Object.values(table.data.raw_rows_data).length === 0) {
                                return;
                            }

                            if (parseInt(parent_cell.get_sibling_value('is_reserved'))) {
                                parent_cell.avoid_click_edit = true;
                            }

                            let value = parseInt(parent_cell.value);
                            let update = false;
                            let max = 23;
                            if (data.key === 'finish_m' || data.key === 'start_m') {
                                max = 59;
                            }

                            if (!Functions.isNumeric(parent_cell.value)) {
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
                                parent_cell.draw_content(value);
                                parent_cell.set_value(value, false);
                            }

                            if (value < 10) {
                                parent_cell.draw_content(`0${value}`);
                            }

                            this.bs.check_rows_overlap(table);
                        }
                        break;


                    case 'price':
                        if (parseInt(parent_cell.get_sibling_value('is_reserved'))) {
                            parent_cell.avoid_click_edit = true;
                        }
                        break;

                    case 'capacity':
                        if (parseInt(parent_cell.get_sibling_value('is_reserved'))) {
                            parent_cell.avoid_click_edit = true;
                        }
                        break;

                    case 'is_active':
                        {

                            if (parseInt(parent_cell.get_sibling_value('is_reserved'))) {
                                parent_cell.draw_content('-');
                            } else {
                                let switcher = new Switcher(this.id, parseInt(parent_cell.table.data.rows[parent_cell.row_index].is_active?.value), parent_cell.container);

                                switcher.setEvent('click', async(e, input) => {
                                    parent_cell.value = input.checked ? 1 : 0;
                                    const sw_data = {...data, key: 'is_active', value: parent_cell.value};
                                    const hash_key = parent_cell.get_sibling_value('hash_key');
                                    await Functions.edit_table_cell(sw_data, slug, {timestamp: start_time, hash_key: hash_key??'', product_id}, table, true);

                                    if (!hash_key) {
                                        table.create_loader();
                                        booking_calendar_container_slots.querySelector('.calendar23-focused').click();
                                    }
                                });
                            }

                        }
                        break;


                    case 'delete':
                        {
                            let btn = Helper.create_element('a', {
                                href: '#',
                                class: 'button button-primary'
                            }, 'X', {
                                name: 'click',
                                callback: e => call_delete_cell(btn, data.extra.id)
                            });

                            parent_cell.set_node(btn);

                            function call_delete_cell(btn, row_id) {
                                if (confirm(botoscope_lang.are_you_sure)) {
                                    table.remove_row(row_id);

                                    Helper.ajax('botoscope_delete_row', {
                                        what: slug,
                                        row_id
                                    }, () => {
                                        table.redraw();
                                    }, false);

                                }
                            }
                        }
                        break;
                }
            }
        });
        this.bs = new BookingSlots(table, 0);
        //+++

        table.data_is_mutated = async (operation, data) => {
            switch (operation) {
                case 'edit_cell':
                    if (['start_h', 'finish_h', 'finish_m', 'start_m'].includes(data.key) && table.data.rows.length > 0) {

                        let value = parseInt(data.value);
                        let max = 23;
                        if (data.key === 'finish_m' || data.key === 'start_m') {
                            max = 59;
                        }

                        if (!Functions.isNumeric(data.value)) {
                            data.value = 0;
                        }

                        if (data.value < 0) {
                            data.value = 0;
                        }

                        if (data.value > max) {
                            data.value = max;
                        }
                    }

                    await Functions.edit_table_cell(data, slug, {timestamp: start_time, hash_key: data.hash_key??'', product_id});

                    if (!data.hash_key) {
                        table.create_loader();
                        booking_calendar_container_slots.querySelector('.calendar23-focused').click();
                    }
                    break;
                case 'move_col_right':
                case 'move_col_left':
                    table.set_table_col_positions(data.positions, data.key, data.index);
                    break;
            }
        };

        //+++

        document.getElementById(`botoscope_create_${slug}_disposable`).addEventListener('click', async e => {
            if (product_id) {
                table.create_loader();

                await Helper.ajax('botoscope_booking_create_disposable_slot', {timestamp: start_time, product_id}, () => {
                    table.redraw();
                }, false);

                booking_calendar_container_slots.querySelector('.calendar23-focused').click();
            }
        });

        table.rebuild = (options) => {
            table.redraw();
        };

        //+++

        const booking_calendar_container_slots = document.getElementById('booking_calendar_nav_slots')
        const bc = new BookingCalendar(booking_calendar_container_slots, 0);

        const original_fill_container = bc.fill_container;
        bc.fill_container = function () {
            table.redraw({}, true);
            original_fill_container.call(this);

            let input = Helper.create_element('input', {
                type: 'search',
                style: 'width: 100%; box-sizing: border-box;',
                class: ''
            }, '', {
                name: 'input',
                callback: e => {
                    e.stopPropagation();
                    const searchText = e.target.value.toLowerCase().trim();

                    const calendarItems = e.target.parentNode.querySelectorAll('.calendar23-day');

                    calendarItems.forEach(item => {
                        const itemText = item.textContent.toLowerCase();

                        if (searchText === '' || itemText.includes(searchText)) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    return false;
                }
            });

            booking_calendar_container_slots.querySelector('.calendar23-month .calendar23-label.calendar23-label-booking-day')?.after(input);
        };

        bc.apply_scene = function (container, num) {
            table.redraw({}, true);
            const date = new Date(this.selected_date);
            const startUtc = Date.UTC(
                    date.getUTCFullYear(),
                    date.getUTCMonth(),
                    date.getUTCDate(),
                    0, 0, 0, 0
                    );
            const endUtc = Date.UTC(
                    date.getUTCFullYear(),
                    date.getUTCMonth(),
                    date.getUTCDate(),
                    23, 59, 59, 999
                    );

            start_time = Math.floor(startUtc / 1000);
            end_time = Math.floor(endUtc / 1000);

            Functions.message(botoscope_lang.loading, 'warning', -1);

            Helper.ajax('botoscope_booking_get_all_virtual_products', {}, res => {
                Functions.message(botoscope_lang.done);
                const products = res.data;

                for (let i = 0; i < products.length; ++i) {
                    this.cells[i] = document.createElement('div');
                    this.cells[i].className = 'calendar23-big calendar23-day';
                    this.cells[i].setAttribute('data-product-id', products[i].id);
                    this.cells[i].innerText = products[i].title;
                    container.appendChild(this.cells[i]);

                    this.cells[i].addEventListener('click', e => {
                        e.stopPropagation();
                        table.create_loader();
                        product_id = parseInt(products[i].id);

                        this.cells.forEach((cell, index) => {
                            cell.classList.toggle('calendar23-focused', index === i);
                        });

                        //+++

                        Functions.message(botoscope_lang.loading, 'warning', -1);

                        Helper.ajax('botoscope_booking_job_slots', {
                            product_id,
                            start_time,
                            end_time
                        }, res => {
                            Functions.message(botoscope_lang.done);
                            raw_rows_data = res.data;
                            table.redraw(raw_rows_data, true);
                        }, true);

                        return false;
                    });
                }


            }, true);
        };


        setTimeout(() => {
            booking_calendar_container_slots.querySelector('.calendar23-focused')?.click();
            setTimeout(() => {
                booking_calendar_container_slots.querySelector('.calendar23-day')?.click();
            }, 999);
        }, 1999);




        //+++

        return table;
    }
}

async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js'),
        import(botoscope_url + 'assets/js/table/ae/switcher.js'),
        import(botoscope_url + 'assets/js/lib/calendar23.js'),
        import(botoscope_url + 'assets/js/lib/calendar23-booking.js'),
        import(botoscope_url + 'exts/booking/assets/js/booking-slots.js'),
        import(botoscope_url + 'assets/js/lib/sidebar.js'),
    ]);

    return modules.map(mod => mod.default || mod);
}

