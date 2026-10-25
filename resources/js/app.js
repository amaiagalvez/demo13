import '../../vendor/amaia/basics13/resources/js/app.js';
import { watchEntitySelects } from '../../vendor/amaia/entity-projects13/resources/js/entity-select.js';

// Charts load ApexCharts on first use, in their own chunk, so pages without one never fetch it.
window.loadApexCharts = () => import('apexcharts').then((module) => module.default);

// jQuery and select2 load only on a page with an entity select; the bridge waits for them.
watchEntitySelects(async () => {
    const [{ default: $ }, { default: select2 }] = await Promise.all([import('jquery'), import('select2')]);

    window.$ = window.jQuery = $;
    select2(window, $);

    return $;
});
