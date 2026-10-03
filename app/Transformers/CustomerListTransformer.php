<?php

namespace App\Transformers;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerListTransformer extends ListTransformer
{
    /**
     * @param  LengthAwarePaginator<int, Customer>  $customers
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $customers, string $search): array
    {
        return [
            'resource' => __('Customers'),
            'state' => 'active',
            'extraDateHeading' => null,
            'inactiveUrl' => route('customers.inactive.index'),
            'emptyMessage' => $search === ''
                ? __('No customers yet.')
                : __('No customers match your search.'),
            'search' => $this->search(route('customers.index'), $search, __('Search customers...')),
            'navigation' => [
                'label' => __('Trash'),
                'url' => route('customers.trash.index'),
                'icon' => 'trash',
                'test' => 'customer-trash-link',
            ],
            'create' => true,
            'rows' => collect($customers->items())->map(fn(Customer $customer): array => [
                ...$this->columns($customer),
                'actions' => [
                    [
                        'type' => 'form-modal',
                        'label' => __('Edit'),
                        'icon' => 'pencil-square',
                        'test' => 'customer-edit-' . $customer->id,
                        'customer' => $customer->only(['id', 'name']),
                    ],
                    $customer->projects_exists ? [
                        'type' => 'confirm-modal',
                        'label' => __('Deactivate'),
                        'icon' => 'lock-closed',
                        'test' => 'customer-deactivate-' . $customer->id,
                        'danger' => true,
                        'action' => route('customers.deactivate', $customer),
                        'method' => 'PATCH',
                        'confirmTitle' => __('Deactivate record?'),
                        'confirmText' => __('You can reactivate it from the inactive list.'),
                        'confirmLabel' => __('Deactivate'),
                    ] : [
                        'type' => 'confirm-modal',
                        'label' => __('Delete'),
                        'icon' => 'trash',
                        'test' => 'customer-delete-' . $customer->id,
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
    public function inactive(LengthAwarePaginator $customers, string $search): array
    {
        return [
            'resource' => __('Customers'),
            'state' => 'inactive',
            'extraDateHeading' => __('Updated at'),
            'emptyMessage' => $search === '' ? __('No inactive records.') : __('No customers match your search.'),
            'search' => $this->search(route('customers.inactive.index'), $search, __('Search customers...')),
            'navigation' => [
                'label' => __('Customers'),
                'url' => route('customers.index'),
                'icon' => 'arrow-left',
                'test' => null,
            ],
            'create' => false,
            'rows' => collect($customers->items())->map(fn(Customer $customer): array => [
                ...$this->columns($customer),
                'extraDate' => $customer->updated_at?->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Reactivate'),
                        'icon' => 'lock-open',
                        'test' => 'customer-reactivate-' . $customer->id,
                        'danger' => false,
                        'action' => route('customers.inactive.reactivate', $customer),
                        'method' => 'PATCH',
                        'confirmTitle' => __('Reactivate record?'),
                        'confirmText' => __('The record will return to the active list.'),
                        'confirmLabel' => __('Reactivate'),
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
            'state' => 'trash',
            'extraDateHeading' => __('Deleted at'),
            'emptyMessage' => $search === ''
                ? __('Trash is empty.')
                : __('No customers match your search.'),
            'search' => $this->search(route('customers.trash.index'), $search, __('Search customers...')),
            'navigation' => [
                'label' => __('Customers'),
                'url' => route('customers.index'),
                'icon' => 'arrow-left',
                'test' => 'customer-list-link',
            ],
            'create' => false,
            'rows' => collect($customers->items())->map(fn(Customer $customer): array => [
                ...$this->columns($customer),
                'extraDate' => $customer->deleted_at?->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Restore'),
                        'icon' => 'arrow-path',
                        'test' => 'customer-restore-' . $customer->id,
                        'danger' => false,
                        'action' => route('customers.trash.restore', $customer->id),
                        'method' => 'PATCH',
                        'confirmTitle' => __('Restore customer?'),
                        'confirmText' => $customer->active
                            ? __('The customer will return to the active list.')
                            : __('The record will return to the inactive list.'),
                        'confirmLabel' => __('Restore'),
                    ],
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Delete permanently'),
                        'icon' => 'trash',
                        'test' => 'customer-force-delete-' . $customer->id,
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
     * @return array{id: int, name: string, projectsCount: int, epicsCount: int, commentsCount: int}
     */
    private function columns(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'projectsCount' => (int) $customer->projects_count,
            'epicsCount' => (int) $customer->epics_count,
            'commentsCount' => (int) $customer->comments_count,
        ];
    }
}
