<?php

namespace App\Transformers;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerListTransformer extends ListTransformer
{
    /**
     * @param  LengthAwarePaginator<int, Customer>  $customers
     * @param  array{active: int, inactive: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $customers, string $search, ?array $counts = null): array
    {
        return [
            'resource' => __('Customers'),
            'state' => 'active',
            'breadcrumbs' => [
                ['label' => __('Dashboard'), 'url' => route('dashboard')],
                ['label' => __('Customers'), 'url' => null],
            ],
            'extraDateHeading' => __('Created at'),
            'emptyMessage' => $search === ''
                ? __('No customers yet.')
                : __('No customers match your search.'),
            'search' => $this->search(route('customers.index'), $search, __('Search customers...')),
            'tabs' => $this->tabs('active', $counts),
            'create' => true,
            'rows' => collect($customers->items())->map(fn (Customer $customer): array => [
                ...$this->columns($customer),
                'extraDate' => $customer->created_at?->toIso8601String(),
                'actionHint' => $customer->projects_exists
                    ? __('Cannot be deleted while it has related records.')
                    : null,
                'actions' => [
                    [
                        'type' => 'form-modal',
                        'label' => __('Edit'),
                        'icon' => 'pencil-square',
                        'test' => 'customer-edit-'.$customer->id,
                        'customer' => $this->editPayload($customer),
                    ],
                    $customer->projects_exists ? [
                        'type' => 'confirm-modal',
                        'label' => __('Deactivate'),
                        'icon' => 'lock-closed',
                        'test' => 'customer-deactivate-'.$customer->id,
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
                        'test' => 'customer-delete-'.$customer->id,
                        'danger' => true,
                        'action' => route('customers.destroy', $customer),
                        'method' => 'DELETE',
                        'confirmTitle' => __('Delete record?'),
                        'confirmText' => __('You can restore it from the trash.'),
                        'confirmLabel' => __('Delete'),
                    ],
                ],
            ])->all(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Customer>  $customers
     * @param  array{active: int, inactive: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function inactive(LengthAwarePaginator $customers, string $search, ?array $counts = null): array
    {
        return [
            'resource' => __('Customers'),
            'state' => 'inactive',
            'breadcrumbs' => [
                ['label' => __('Dashboard'), 'url' => route('dashboard')],
                ['label' => __('Customers'), 'url' => route('customers.index')],
                ['label' => __('Inactive'), 'url' => null],
            ],
            'extraDateHeading' => __('Updated at'),
            'emptyMessage' => $search === '' ? __('No inactive records.') : __('No customers match your search.'),
            'search' => $this->search(route('customers.inactive.index'), $search, __('Search customers...')),
            'tabs' => $this->tabs('inactive', $counts),
            'create' => false,
            'rows' => collect($customers->items())->map(fn (Customer $customer): array => [
                ...$this->columns($customer),
                'extraDate' => $customer->updated_at?->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Reactivate'),
                        'icon' => 'lock-open',
                        'test' => 'customer-reactivate-'.$customer->id,
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
     * @param  array{active: int, inactive: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function trash(LengthAwarePaginator $customers, string $search, ?array $counts = null): array
    {
        return [
            'resource' => __('Customers'),
            'state' => 'trash',
            'breadcrumbs' => [
                ['label' => __('Dashboard'), 'url' => route('dashboard')],
                ['label' => __('Customers'), 'url' => route('customers.index')],
                ['label' => __('Trash'), 'url' => null],
            ],
            'extraDateHeading' => __('Deleted at'),
            'emptyMessage' => $search === ''
                ? __('Trash is empty.')
                : __('No customers match your search.'),
            'search' => $this->search(route('customers.trash.index'), $search, __('Search customers...')),
            'tabs' => $this->tabs('trash', $counts),
            'create' => false,
            'rows' => collect($customers->items())->map(fn (Customer $customer): array => [
                ...$this->columns($customer),
                'extraDate' => $customer->deleted_at?->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Restore'),
                        'icon' => 'arrow-path',
                        'test' => 'customer-restore-'.$customer->id,
                        'danger' => false,
                        'action' => route('customers.trash.restore', $customer->id),
                        'method' => 'PATCH',
                        'confirmTitle' => __('Restore record?'),
                        'confirmText' => $customer->active
                            ? __('The record will return to the active list.')
                            : __('The record will return to the inactive list.'),
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
                        'confirmTitle' => __('Permanently delete record?'),
                        'confirmText' => __('This action cannot be undone.'),
                        'confirmLabel' => __('Delete permanently'),
                    ],
                ],
            ])->all(),
        ];
    }

    /**
     * State tabs shown under the page heading: the resource itself plus its other states, each one
     * with the number of records it holds.
     *
     * @param  array{active: int, inactive: int, trashed: int}|null  $counts
     * @return list<array{label: string, url: string, current: bool, count: int|null, test: string}>
     */
    private function tabs(string $state, ?array $counts): array
    {
        return [
            [
                'label' => __('Customers'),
                'url' => route('customers.index'),
                'current' => $state === 'active',
                'count' => $counts['active'] ?? null,
                'test' => 'customer-active-link',
            ],
            [
                'label' => __('Inactive'),
                'url' => route('customers.inactive.index'),
                'current' => $state === 'inactive',
                'count' => $counts['inactive'] ?? null,
                'test' => 'customer-inactive-link',
            ],
            [
                'label' => __('Trash'),
                'url' => route('customers.trash.index'),
                'current' => $state === 'trash',
                'count' => $counts['trashed'] ?? null,
                'test' => 'customer-trash-link',
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, projectsCount: int, projectsUrl: string|null, epicsCount: int, commentsCount: int, editPayload: array{id: int, name: string}}
     */
    private function columns(Customer $customer): array
    {
        $projectsCount = (int) $customer->projects_count;

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'projectsCount' => $projectsCount,
            // The projects list searches customer names, so the row count can link to its own slice.
            'projectsUrl' => $projectsCount > 0
                ? route('projects.index', ['search' => $customer->name])
                : null,
            'epicsCount' => (int) $customer->epics_count,
            'commentsCount' => (int) $customer->comments_count,
            'editPayload' => $this->editPayload($customer),
        ];
    }

    /**
     * Fields the form needs to open in edit mode. Shared by the row name button and the row edit
     * action so both always open the drawer with the very same data.
     *
     * @return array{id: int, name: string}
     */
    private function editPayload(Customer $customer): array
    {
        return $customer->only(['id', 'name']);
    }
}
