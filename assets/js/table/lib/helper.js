'use strict';
//12-03-2025
export default class Helper {
    static cast(event, data) {
        document.dispatchEvent(new CustomEvent(event, {detail: data}));
    }

    static async ajax(action, data, callback = null, json = true, custom_ajaxurl = null, signal = null) {
        // Function for recursively converting objects to flattened form
        function appendFormData(fd, data, parentKey = '') {
            if (data && typeof data === 'object' && !Array.isArray(data)) {
                // Processing the object
                for (let key in data) {
                    const fullKey = parentKey ? `${parentKey}[${key}]` : key;
                    appendFormData(fd, data[key], fullKey);
                }
            } else if (Array.isArray(data)) {
                // Processing an array
                data.forEach((value, index) => {
                    const fullKey = `${parentKey}[${index}]`;
                    appendFormData(fd, value, fullKey);
                });
            } else {
                // Processing primitive values
                fd.append(parentKey, data);
        }
        }

        const fd = new FormData();
        appendFormData(fd, {...{action}, ...data});

        fd.append('botoscope_form_nonce', document.getElementById('botoscope_form_nonce').value);

        const response = await fetch(custom_ajaxurl ? custom_ajaxurl : ajaxurl, {
            signal: signal,
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        });

        const result = json ? await response.json() : await response.text();

        if (callback) {
            callback(result);
        }

        return result;
    }

    static create_element(type, data = {}, content = '', event = null) {
        let item = document.createElement(type);

        if (Object.values(data)) {
            for (const [key, value] of Object.entries(data)) {
                item.setAttribute(key, value);
            }
        }

        if (content) {
            if (typeof content === 'string') {
                item.innerHTML = content;
            }

            if (typeof content === 'object') {
                item.appendChild(content);
            }
        }

        if (event) {
            item.addEventListener(event.name, e => {
                e.preventDefault();//!!
                e.stopPropagation();
                event.callback(e);
                return false;
            });
        }

        return item;
    }

    static create_html_select(options, selected = null, data = {}, event = null) {
        let select = Helper.create_element('select', data, '', event);

        if (!options) {
            return null;
        }

        const is_array = Array.isArray(options);

        for (const [k, v] of Object.entries(options)) {
            const value = is_array ? v : k;

            let option = Helper.create_element('option', {
                value: typeof v === 'object' ? v.id : value
            }, typeof v === 'object' ? v.title : v);
            if (selected) {
                if (Array.isArray(selected)) {
                    if (selected.map(String).includes(value.toString())) {
                        option.selected = true;
                        option.setAttribute('selected', true);
                    }
                } else {
                    if (value == selected) {//!! ==, no ===
                        option.selected = true;
                    }
                }
            }

            if (data.disabled) {
                if (data.disabled.map(String).includes(option.value.toString())) {
                    option.disabled = true;
                }
            }

            select.appendChild(option);
        }

        return select;
    }

    static generate_key(prefix = 't') {
        return prefix + '-' + Math.random().toString(36).substring(7);
    }

    //avoid multiple reactions for reinited objects which uses document attached events
    static events = [];
    static addSingleEventListener(event_name, instance, callback) {

        if (!instance.instance_key) {
            console.error(`Instance ${instance.constructor.name} not has instance_key field!`);
            return;
        }

        if (!Helper.events[instance.instance_key]) {
            Helper.events[instance.instance_key] = [];
        }

        if (!Helper.events[instance.instance_key][event_name]) {
            Helper.events[instance.instance_key][event_name] = callback.bind(instance);
            document.addEventListener(event_name, e => Helper.events[instance.instance_key][event_name](e));
        } else {
            Helper.events[instance.instance_key][event_name] = callback.bind(instance);
        }
    }

    static sortMediaByOrder(mediaArray, orderArray, key) {
        // Create an object for quick access to elements by key
        const mediaMap = mediaArray.reduce((map, item) => {
            map[item[key]] = item;
            return map;
        }, {});

        return orderArray.map(aid => mediaMap[aid]).filter(Boolean);
    }

}

