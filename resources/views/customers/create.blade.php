@php($isEditing = isset($customer))

<x-layouts::app :title="$isEditing ? __('Edit customer') : __('New customer')">
    @include('customers._form', ['customer' => $customer ?? null, 'inDrawer' => false])
</x-layouts::app>
