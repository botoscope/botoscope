'use strict';
//02-04-2026
window.addEventListener('load', function () {
    const chat = document.getElementById('chat');
    if (chat) {
        chat.scrollTop = chat.scrollHeight;
    }
});
function sendMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (!message) {
        return;
    }

    const chat = document.getElementById('chat');
    const msgDiv = document.createElement('div');
    msgDiv.className = 'message you';
    const now = new Date();
    const timeString = now.toLocaleString('en-US', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
    msgDiv.innerHTML = `
              ${message.replace(/\n/g, '<br/>')}
              <div class="meta">You · ${timeString}</div>
            `;
    chat.appendChild(msgDiv);
    chat.scrollTop = chat.scrollHeight;
    input.value = '';
    autoResize(input);
    //+++

    ajax('botoscope_business_in_pocket_answer_to_customer', {
        ticket_id: botoscope_ticket_id,
        message
    }, null, false);
}

function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 100) + 'px';
}

function toggleDropdown() {
    const dropdown = document.getElementById('dropdownContent');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

function toggle_ticket(ticket_id, hash_key) {
    window.location.href = `${page_url}?botoscope_chat=1&ticket_id=${ticket_id}&hash_key=${hash_key}`;
}

async function ajax(action, data, callback = null, json = true, custom_ajaxurl = null, signal = null) {
    function appendFormData(fd, data, parentKey = '') {
        if (data && typeof data === 'object' && !Array.isArray(data)) {
            for (let key in data) {
                const fullKey = parentKey ? `${parentKey}[${key}]` : key;
                appendFormData(fd, data[key], fullKey);
            }
        } else if (Array.isArray(data)) {
            data.forEach((value, index) => {
                const fullKey = `${parentKey}[${index}]`;
                appendFormData(fd, value, fullKey);
            });
        } else {
            fd.append(parentKey, data);
    }
    }

    const fd = new FormData();
    appendFormData(fd, {...{action}, ...data});
    fd.append('botoscope_form_nonce', botoscope_nonce);
    const response = await fetch((custom_ajaxurl ? custom_ajaxurl : ajaxurl), {
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
