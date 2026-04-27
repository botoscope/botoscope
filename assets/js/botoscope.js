"use strict";
import Helper from './table/lib/helper.js';
import * as Functions from './lib/functions.js';
const apps = [
    'payment',
    'controls',
    'taxonomies',
    'extensions'
];
//02-07-2025
async function loadExtensions() {
    if (botoscope_active_extensions.length > 0) {
        for (const key of botoscope_active_extensions) {
            let ext_slug = `ext_${key}`;
            if (!apps.includes(ext_slug)) {
                apps.push(ext_slug);
            }
        }
    }
}

async function start() {
    await loadExtensions();
    let all_tables = {};
    for (const app_name of apps) {
        let app;
        if (app_name.startsWith('ext_')) {
            let a = app_name.slice(4);
            try {
                if (a.startsWith('custom_')) {
                    app = await import(`${botoscope_custom_ext_url}/${a}/assets/js/app.js`);
                } else {
                    app = await import(`${botoscope_url}exts/${a}/assets/js/app.js`);
                }
            } catch (e) {
                console.warn("File not found or import error:", e);
            }

        } else {
            app = await import(`./apps/${app_name}.js`);
        }

        if (app && app?.default && typeof app.default === 'function') {
            let t = app?.default();
            if (t) {
                all_tables[t.constant_key] = t;
            }
        }
    }

    init(all_tables);
}


addEventListener('DOMContentLoaded', function (e) {
    start();

    //+++

    const botoscope_form_nonce = document.getElementById('botoscope_form_nonce').value;
    //puls
    setInterval(async () => {
        await Helper.ajax('botoscope_check_nonce', {
            botoscope_form_nonce
        }, res => {
            if (parseInt(res) === 0) {
                window.location.reload();
            }
        }, false);
    }, 1000 * 60 * 1);
});

//+++

function init(all_tables) {
    let language_selector = document.getElementById('botoscope_products_language_selector').querySelector('select')??null;

    Helper.addSingleEventListener('botoscope-selected-bot-languages', {instance_key: 1}, e => {
        let languages = e.detail.data.languages;
        //all_tables.elogios.rebuild({languages});//code as an example

        if (languages.length) {
            language_selector.style.display = 'inline';
            let languages_list = JSON.parse(document.getElementById('botoscope_languages_list').innerHTML);
            languages.unshift(e.detail.data.default_language); //prepend default language

            Functions.redraw_drop_down(language_selector, e.detail.data.languages, languages_list);

            language_selector.dataset.default = e.detail.data.default_language;//!!
        } else {
            language_selector.style.display = 'none';
        }


        Helper.cast('botoscope-actual-languages', {
            data: {
                default_language: e.detail.data.default_language,
                languages
            }
        });
    });
}
