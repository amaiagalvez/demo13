import '../../vendor/amaia/basics13/resources/js/app.js';
import $ from 'jquery';
import select2 from 'select2';
import {
    initializeCustomerSelects,
    observeCustomerSelects,
} from '../../vendor/amaia/customer-projects13/resources/js/customer-select.js';

window.$ = window.jQuery = $;
select2(window, $);

initializeCustomerSelects();
observeCustomerSelects();
