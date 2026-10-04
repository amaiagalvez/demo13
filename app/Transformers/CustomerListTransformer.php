<?php

namespace App\Transformers;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends ListTransformer<Customer>
 */
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
            ...$this->envelope('active', $search, $counts),
            'rows' => collect($customers->items())->map(fn (Customer $customer): array => [
                ...$this->columns($customer),
                'extraDate' => $customer->created_at?->toIso8601String(),
                'actionHint' => $customer->projects_exists
                    ? __('Cannot be deleted while it has related records.')
                    : null,
                'actions' => [
                    $this->editAction($customer),
                    $customer->projects_exists
                        ? $this->deactivateAction($customer)
                        : $this->deleteAction($customer),
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
            ...$this->envelope('inactive', $search, $counts),
            'rows' => collect($customers->items())->map(fn (Customer $customer): array => [
                ...$this->columns($customer),
                'extraDate' => $customer->updated_at?->toIso8601String(),
                'actions' => [$this->reactivateAction($customer)],
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
            ...$this->envelope('trash', $search, $counts),
            'rows' => collect($customers->items())->map(fn (Customer $customer): array => [
                ...$this->columns($customer),
                'extraDate' => $customer->deleted_at?->toIso8601String(),
                'actions' => [
                    $this->restoreAction($customer, $customer->active),
                    $this->forceDeleteAction($customer),
                ],
            ])->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, notes: string|null, projectsCount: int, projectsUrl: string|null, epicsCount: int, commentsCount: int, editPayload: array{id: int, name: string, notes: string|null}}
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
     * @param  Customer  $record
     * @return array{id: int, name: string, notes: string|null}
     */
    protected function editPayload(Model $record): array
    {
        return $record->only(['id', 'name', 'notes']);
    }

    protected function resourceLabel(): string
    {
        return __('Customers');
    }

    protected function noMatchMessage(): string
    {
        return __('No customers match your search.');
    }

    protected function noRecordsMessage(): string
    {
        return __('No customers yet.');
    }

    protected function searchPlaceholder(): string
    {
        return __('Search customers...');
    }

    protected function resourceKey(): string
    {
        return 'customer';
    }

    /**
     * @return array{active: string, inactive: string, trash: string, destroy: string, deactivate: string, reactivate: string, restore: string, trashDestroy: string}
     */
    protected function routes(): array
    {
        return [
            'active' => 'customers.index',
            'inactive' => 'customers.inactive.index',
            'trash' => 'customers.trash.index',
            'destroy' => 'customers.destroy',
            'deactivate' => 'customers.deactivate',
            'reactivate' => 'customers.inactive.reactivate',
            'restore' => 'customers.trash.restore',
            'trashDestroy' => 'customers.trash.destroy',
        ];
    }
}
