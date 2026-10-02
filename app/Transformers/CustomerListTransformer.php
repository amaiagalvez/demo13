<?php

namespace App\Transformers;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerListTransformer
{
    /**
     * @param  LengthAwarePaginator<int, Customer>  $customers
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $customers, string $search): array
    {
        return [
            'resource' => __('Customers'),
            'dateHeading' => __('Created at'),
            'emptyMessage' => $search === ''
                ? __('No customers yet.')
                : __('No customers match your search.'),
            'search' => $this->search(route('customers.index'), $search),
            'navigation' => [
                'label' => __('Trash'),
                'url' => route('customers.trash.index'),
                'icon' => 'trash',
                'test' => 'customer-trash-link',
            ],
            'create' => true,
            'rows' => collect($customers->items())->map(fn (Customer $customer): array => [
                ...$this->columns($customer),
                'actions' => [
                    [
                        'type' => 'form-modal',
                        'label' => __('Edit'),
                        'icon' => 'pencil-square',
                        'test' => 'customer-edit-'.$customer->id,
                        'customer' => $customer->only(['id', 'name']),
                    ],
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Delete'),
                        'icon' => 'trash',
                        'test' => 'customer-delete-'.$customer->id,
                        'danger' => true,
                        'action' => route('customers.destroy', $customer),
                        'method' => 'DELETE',
                        'confirmTitle' => __('Delete customer?'),
                        'confirmText' => __('You can restore it from the trash.'),
                        'confirmLabel' => __('Delete'),
                    ],
                ],
            ])->all(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Customer>  $customers
     * @return array<string, mixed>
     */
    public function trash(LengthAwarePaginator $customers, string $search): array
    {
        return [
            'resource' => __('Customers'),
            'dateHeading' => __('Created at'),
            'emptyMessage' => $search === ''
                ? __('Trash is empty.')
                : __('No customers match your search.'),
            'search' => $this->search(route('customers.trash.index'), $search),
            'navigation' => [
                'label' => __('Customers'),
                'url' => route('customers.index'),
                'icon' => 'arrow-left',
                'test' => null,
            ],
            'create' => false,
            'rows' => collect($customers->items())->map(fn (Customer $customer): array => [
                ...$this->columns($customer),
                'deletedAt' => $customer->deleted_at?->format('Y-m-d'),
                'actions' => [
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Restore'),
                        'icon' => 'arrow-path',
                        'test' => 'customer-restore-'.$customer->id,
                        'danger' => false,
                        'action' => route('customers.trash.restore', $customer->id),
                        'method' => 'PATCH',
                        'confirmTitle' => __('Restore customer?'),
                        'confirmText' => __('The customer will return to the active list.'),
                        'confirmLabel' => __('Restore'),
                    ],
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Delete permanently'),
                        'icon' => 'trash',
                        'test' => 'customer-force-delete-'.$customer->id,
                        'danger' => true,
                        'action' => route('customers.trash.destroy', $customer->id),
                        'method' => 'DELETE',
                        'confirmTitle' => __('Permanently delete customer?'),
                        'confirmText' => __('This action cannot be undone.'),
                        'confirmLabel' => __('Delete permanently'),
                    ],
                ],
            ])->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, date: ?string}
     */
    private function columns(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'date' => $customer->created_at?->format('Y-m-d'),
        ];
    }

    /**
     * @return array{action: string, value: string, placeholder: string}
     */
    private function search(string $action, string $value): array
    {
        return [
            'action' => $action,
            'value' => $value,
            'placeholder' => __('Search customers...'),
        ];
    }
}
