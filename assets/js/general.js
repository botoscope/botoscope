//09-04-2026
addEventListener('DOMContentLoaded', function (e) {
    init_tabs_menu();
    jQuery('.botoscope-tabs').botoscopeTabs();

    const isMobile = window.matchMedia('(max-width: 768px)').matches;

    if (isMobile) {
        alert('⚠ ️' + botoscope_lang.works_better_on);
    }
});

function botoscope_close_ps_sidebar(obj) {
    obj.closest('.botoscope-static-sidebar-opened').querySelector('.botoscope-sidebar-close').dispatchEvent(new Event('click'));
    return true;
}

function toggleStockQuantity(selectElement) {
    const input = selectElement.parentNode.parentNode.querySelector('input[name="stock_quantity"]');
    const is_in_stock = selectElement.parentNode.parentNode.querySelector('select[name="is_in_stock"]');

    if (parseInt(selectElement.value) === 1) {
        input.parentNode.style.display = '';
        is_in_stock.parentNode.style.display = 'none';
    } else {
        input.parentNode.style.display = 'none';
        is_in_stock.parentNode.style.display = '';
    }
}

function botoscope_init_tabs(tabs_container) {
    if (tabs_container) {
        const tabs = tabs_container.querySelectorAll('a');
        const containers = tabs_container.parentNode.querySelectorAll('.botoscope-tab-container');

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', e => {
                e.preventDefault();

                tabs.forEach(aa => aa.classList.remove('selected'));
                containers.forEach(c => c.style.display = 'none');
                tab.classList.add('selected');
                containers[index].style.display = 'block';

                return true;
            });
        });
    }
}

function init_tabs_menu() {
    setTimeout(() => {
        if (botoscope_tabs_count > botoscope_menu_limit) {

            const menuItems = document.querySelectorAll(`#botoscope_menu_manager_menu > li:nth-child(n+${botoscope_menu_limit})`);

            menuItems.forEach((item, index) => {
                const offset = item.dataset.offset ? parseInt(item.dataset.offset, 10) : (index + 1) * 10;
                item.style.top = `${offset}px`;
            });

            const hamburgerButton = document.querySelector(".botoscope_menu_manager_hamburger");
            const menu = document.querySelector("#botoscope_menu_manager_menu");
            const hiddenMenuItems = document.querySelectorAll("#botoscope_menu_manager_menu > li");

            // Function to toggle the visibility of hidden menu items
            function toggleMenu() {
                hiddenMenuItems.forEach(item => {
                    item.classList.toggle("botoscope_menu_manager_show");
                });
            }

            // Hamburger Button Click Handler
            hamburgerButton.addEventListener("click", (e) => {
                e.stopPropagation();
                toggleMenu();
            });

            // Document click handler to hide menu when clicked outside
            document.addEventListener("click", (e) => {
                if (!menu.contains(e.target) && !hamburgerButton.contains(e.target)) {
                    hiddenMenuItems.forEach(item => item.classList.remove("botoscope_menu_manager_show"));
                }
            });

            // Closing the menu by clicking on the menu items themselves
            hiddenMenuItems.forEach(item => {
                item.addEventListener("click", () => {
                    hiddenMenuItems.forEach(i => i.classList.remove("botoscope_menu_manager_show"));
                });
            });

            //mobile menu
            if (document.body.classList.contains('botoscope-mobile')) {

                const menuItems = document.querySelectorAll('#botoscope_menu_manager_menu > li');

                menuItems.forEach((item, index) => {
                    item.addEventListener('click', e => {
                        setTimeout(() => {
                            const mi = document.querySelectorAll('#botoscope_menu_manager_menu > li:not(.tab-current)');
                            let offset = 50;
                            mi.forEach((item, index) => {
                                item.style.top = `${offset}px`;
                                offset += 50;
                            });

                        }, 111);
                    });
                });

            }

        }
    }, 999);
}