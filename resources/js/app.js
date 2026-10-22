import '../../vendor/amaia/basics13/resources/js/app.js';
import $ from 'jquery';
import select2 from 'select2';
import {
    initializeEntitySelects,
    observeEntitySelects,
} from '../../vendor/amaia/entity-projects13/resources/js/entity-select.js';

window.$ = window.jQuery = $;
select2(window, $);

// Charts load ApexCharts on first use, in their own chunk, so pages without one never fetch it.
window.loadApexCharts = () => import('apexcharts').then((module) => module.default);

initializeEntitySelects();
observeEntitySelects();
