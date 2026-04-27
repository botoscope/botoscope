const ms_module = await import(`./marketing_strategies_app.js`);
const mc_module = await import(`./marketing_campaigns_app.js`);
//08-04-2026
export default async function init_marketing() {
    await ms_module.default();
    await mc_module.default();

    //tabs
    setTimeout(() => botoscope_init_tabs(document.getElementById('botoscope-marketing-tabs')), 999);
}
