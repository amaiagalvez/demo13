@props(['search'])

<div data-list-results
    x-data="listSearch(@js($search['value']))"
    x-on:input.debounce.400ms="searchInput($event)"
    x-on:submit.prevent="submitSearch($event)"
    x-on:click="handleClick($event)"
    class="flex flex-col gap-4">
    {{ $slot }}
</div>
