//14-04-2026
export default async function init_shopify_sync() {
    const [Functions, Helper] = await loadModules();

    document.getElementById('botoscope_shopify_sync_all')?.addEventListener('click', async () => {
        const btn = document.getElementById('botoscope_shopify_sync_all');
        const progress = document.getElementById('botoscope_shopify_sync_progress');
        const bar = document.getElementById('botoscope_shopify_sync_bar');
        const status = document.getElementById('botoscope_shopify_sync_status');
        let synced = 0;

        const showError = (msg) => {
            status.style.color = '#e53935';
            status.textContent = `⚠️ ${msg}`;
            btn.disabled = false;
        };

        const showSuccess = (msg) => {
            status.style.color = '#43a047';
            status.textContent = `✅ ${msg}`;
            btn.disabled = false;

            const now = new Date();
            const pad = n => String(n).padStart(2, '0');
            const dateStr = `${pad(now.getDate())}.${pad(now.getMonth() + 1)}.${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())}`;
            const dateEl = document.getElementById('botoscope_shopify_last_sync_date');
            const countEl = document.getElementById('botoscope_shopify_last_sync_count');
            if (dateEl)
                dateEl.innerHTML = dateStr;
            if (countEl)
                countEl.innerHTML = String(synced);
        };

        btn.disabled = true;
        progress.style.display = 'block';
        status.style.color = '';
        status.textContent = botoscope_lang.shopify_sync_ch_webhooks;

        const checkData = await Helper.ajax('botoscope_shopify_webhooks_check', {});
        if (!checkData.success) {
            showError('Failed to check webhooks. Check Shopify credentials.');
            return;
        }

        if (!checkData.data?.registered) {
            status.textContent = botoscope_lang.shopify_sync_reg_webhooks;
            const regData = await Helper.ajax('botoscope_shopify_register_webhooks', {});
            const failed = Object.entries(regData.data || {}).filter(([, v]) => v !== 'ok');
            if (failed.length > 0) {
                showError(`Webhook registration failed: ${failed.map(([k]) => k).join(', ')}. Check your Shopify app settings.`);
                return;
            }
        }

        status.textContent = botoscope_lang.shopify_sync_get_prod_count;
        const countData = await Helper.ajax('botoscope_shopify_count', {});
        if (!countData.success) {
            showError('Failed to get product count from Shopify.');
            return;
        }

        const total = countData.data?.count || 0;
        if (total === 0) {
            showError('No active products found in Shopify.');
            return;
        }

        const sprintf = (str, ...args) => str.replace(/%(\d+)\$s/g, (_, n) => args[n - 1]).replace(/%s/g, () => args.shift());

        status.textContent = sprintf(botoscope_lang.shopify_sync_found_products, total);


        const batch = 5;
        const allErrors = [];

        for (let offset = 0; offset < total; offset += batch) {
            const data = await Helper.ajax('botoscope_shopify_run_sync', {offset, limit: batch});
            synced += data.data?.synced || 0;

            if (data.data?.errors?.length) {
                allErrors.push(...data.data.errors);
            }

            const pct = Math.min(100, Math.round(((offset + batch) / total) * 100));
            bar.style.width = pct + '%';
            status.textContent = sprintf(botoscope_lang.shopify_sync_synced_of, synced, total);
        }

        await Helper.ajax('botoscope_shopify_flush', {total_synced: synced});
        bar.style.width = '100%';
        if (allErrors.length > 0) {
            status.style.color = '#e65100';
            status.textContent = `⚠️ ${sprintf(botoscope_lang.shopify_sync_done, synced, total)} Warnings: ${allErrors.join(' | ')}`;
            btn.disabled = false;
        } else {
            showSuccess(sprintf(botoscope_lang.shopify_sync_done, synced, total));
        }
    });
}

async function loadModules() {
    const modules = await Promise.all([
        import(botoscope_url + 'assets/js/lib/functions.js'),
        import(botoscope_url + 'assets/js/table/lib/helper.js')
    ]);
    return modules.map(mod => mod.default || mod);
}

