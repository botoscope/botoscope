const languages = {selected_language: null, default_language: null};
//08-04-2026
export default async function init_booking() {
    if (botoscope_is_no_cart || botoscope_no_bot) {
        return false;
    }

    //tabs
    setTimeout(() => botoscope_init_tabs(document.getElementById('botoscope-booking-tabs')), 999);

    const [Functions, Helper, Table, Switcher, Calendar23, BookingCalendar, BookingSlotsTargeted] = await loadModules();
    let slug = 'booking';
    let wrapper = document.getElementById(`botoscope-${slug}-w`);
    //let raw_rows_data = JSON.parse(wrapper.textContent);
    let raw_rows_data = [];
    let start_time, end_time;

    const bst = new BookingSlotsTargeted();
    bst.init();

    init_on_off(Switcher, Helper, Functions);

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
                value: 'ID',
                width: '10%',
                key: 'order_id'
            },
            {
                value: botoscope_lang.title,
                width: '50%',
                key: 'product_title'
            },
            {
                value: botoscope_lang.time,
                width: '30%',
                key: 'time'
            },
            {
                value: botoscope_lang.cancel,
                width: '10%',
                key: 'delete'
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
                    editable: [''],
                    id: id
                }
            };
        }
    };

    //+++

    wrapper.innerHTML = '';
    let table = new Table(wrapper, data, slug, {
        cell_content_drawn: data => {

            const cell = data.cell;

            switch (data.key) {

                case 'order_id':
                    if (parseInt(cell.value) > 0) {
                        cell.draw_content(`<a href="/wp-admin/post.php?post=${cell.value}&action=edit" target="_blank">&lt;${cell.value}&gt;</a>`);
                    } else {
                        cell.draw_content(botoscope_lang.no_data);
                    }
                    break;

                case 'time':
                    {
                        if (raw_rows_data.length === 0) {
                            return;
                        }

                        let start_h = parseInt(cell.get_sibling_value('start_h'));
                        if (start_h < 10) {
                            start_h = `0${start_h}`;
                        }
                        let start_m = parseInt(cell.get_sibling_value('start_m'));
                        if (start_m < 10) {
                            start_m = `0${start_m}`;
                        }
                        let finish_h = parseInt(cell.get_sibling_value('finish_h'));
                        if (finish_h < 10) {
                            finish_h = `0${finish_h}`;
                        }
                        let finish_m = parseInt(cell.get_sibling_value('finish_m'));
                        if (finish_m < 10) {
                            finish_m = `0${finish_m}`;
                        }

                        cell.draw_content(`<div class="botoscope-booking-reservation-time"><span>${start_h}:${start_m}</span>&nbsp;&nbsp;-&nbsp;&nbsp;<span>${finish_h}:${finish_m}</span></div>`);
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

                        cell.set_node(btn);

                        function call_delete_cell(btn, row_id) {
                            if (confirm(botoscope_lang.are_you_sure)) {
                                window.open(`/wp-admin/post.php?post=${cell.get_sibling_value('order_id')}&action=edit`, '_blank');
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

    //+++

    table.data_is_mutated = async (operation, data) => {
        switch (operation) {
            case 'edit_cell':
                await Functions.edit_table_cell(data, slug);
                break;
            case 'move_col_right':
            case 'move_col_left':
                table.set_table_col_positions(data.positions, data.key, data.index);
                break;
        }
    };

    //+++

    table.rebuild = (options) => table.redraw();

    //+++

    const booking_calendar_container = document.getElementById('booking_calendar_nav_reservations')
    const bc = new BookingCalendar(booking_calendar_container, 0);

    bc.fill_scene = async function () {
        table.create_loader();
        const d = new Date(Date.UTC(this.get_year(), this.get_month(), 1));
        const unix = Math.floor(d.getTime() / 1000);

        await Helper.ajax('botoscope_booking_get_reservation_counts', {
            start_time: unix
        }, res => bc.count_data = res.data, true);

        this.fill_scene_0();
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

        Helper.ajax('botoscope_booking_get_reservations', {
            start_time,
            end_time
        }, res => {
            Functions.message(botoscope_lang.done);
            raw_rows_data = res.data;
            table.redraw(raw_rows_data, true);
        }, true);
    };

    setTimeout(() => {
        booking_calendar_container.querySelector('.calendar23-today').click();
    }, 999);

    //+++

    //search in reservations by worker
    const searchInput = document.getElementById('botoscope-booking-reservations-search');

    if (searchInput) {
        searchInput.addEventListener('input', function (e) {
            e.stopPropagation();
            const searchText = e.target.value.toLowerCase().trim();

            // Find all the rows in the table
            const tableRows = document.querySelectorAll('#botoscope-booking-w data-table-row');

            tableRows.forEach(row => {
                // Find the cell with product_title
                const titleCell = row.querySelector('data-table-cell[data-key="product_title"]');

                if (titleCell) {
                    const titleText = titleCell.textContent.toLowerCase();

                    if (searchText === '' || titleText.includes(searchText)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });
    }

    //+++

    return table;
}

function init_on_off(Switcher, Helper, Functions) {
    const container = document.getElementById('botoscope-booking-on-off-container');

    Helper.ajax('botoscope_booking_on_off_get_state', {}, res => {
        let value = parseInt(res);
        let switcher = new Switcher('bbooc', value, container);

        switcher.setEvent('click', (e, input) => {
            value = input.checked ? 1 : 0;
            Functions.message(botoscope_lang.saving, 'warning', -1);
            Helper.ajax('botoscope_booking_on_off_set_state', {value}, res => {
                if (value) {
                    Functions.message(botoscope_lang.booking_is_on);
                } else {
                    Functions.message(botoscope_lang.booking_is_off, 'error');
                }
            }, false);
        });
    }, false);
}

async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js'),
        import(botoscope_url + 'assets/js/table/table.js'),
        import(botoscope_url + 'assets/js/table/ae/switcher.js'),
        import(botoscope_url + 'assets/js/lib/calendar23.js'),
        import(botoscope_url + 'assets/js/lib/calendar23-booking.js'),
        import(botoscope_url + 'exts/booking/assets/js/booking-slots-targeted.js')
    ]);

    return modules.map(mod => mod.default || mod);
}


