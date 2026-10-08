<?php

namespace App\Transformers;

use Illuminate\Database\Eloquent\Model;

/**
 * Builds the list views of every resource.
 *
 * Customers, projects and epics share the same page skeleton: the state tabs, the breadcrumbs, the
 * empty and search messages and the six row actions only differ in the resource they name. This
 * base owns all of that; a transformer only describes its own columns and edit payload.
 *
 * @template TRecord of Model
 */
abstract class ListTransformer
{
    /**
     * @return array{action: string, value: string, placeholder: string}
     */
    final protected function search(string $action, string $value, string $placeholder): array
    {
        return [
            'action' => $action,
            'value' => $value,
            'placeholder' => $placeholder,
        ];
    }

    /**
     * Everything a list view needs above its rows.
     *
     * @param  'active'|'archived'|'trash'  $state
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    final protected function envelope(string $state, string $search, ?array $counts): array
    {
        return [
            'resource' => $this->resourceLabel(),
            'breadcrumbs' => $this->breadcrumbs($state),
            'extraDateHeading' => match ($state) {
                'active' => __('Created at'),
                'archived' => __('Updated at'),
                'trash' => __('Deleted at'),
            },
            'emptyMessage' => $this->emptyMessage($state, $search),
            'search' => $this->search(
                route($this->routes()[$state]),
                $search,
                $this->searchPlaceholder(),
            ),
            'tabs' => $this->tabs($state, $counts),
            'create' => $state === 'active',
        ];
    }

    /**
     * State tabs shown under the page heading: the resource itself plus its other states, each one
     * with the number of records it holds.
     *
     * @param  'active'|'archived'|'trash'  $state
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return list<array{label: string, url: string, current: bool, count: int|null, test: string}>
     */
    final protected function tabs(string $state, ?array $counts): array
    {
        return [
            $this->tab($this->resourceLabel(), route($this->routes()['active']), 'active', 'active', $state, $counts),
            $this->tab(__('Archived'), route($this->routes()['archived']), 'archived', 'archived', $state, $counts),
            $this->tab(__('Trash'), route($this->routes()['trash']), 'trash', 'trashed', $state, $counts),
        ];
    }

    /**
     * @param  array{active: int, archived: int, trashed: int}|null  $counts
     * @return array{label: string, url: string, current: bool, count: int|null, test: string}
     */
    private function tab(
        string $label,
        string $url,
        string $tab,
        string $count,
        string $state,
        ?array $counts,
    ): array {
        return [
            'label' => $label,
            'url' => $url,
            'current' => $state === $tab,
            'count' => $counts[$count] ?? null,
            'test' => $this->resourceKey().'-'.$tab.'-link',
        ];
    }

    /**
     * @param  'active'|'archived'|'trash'  $state
     * @return list<array{label: string, url: string|null}>
     */
    private function breadcrumbs(string $state): array
    {
        $dashboard = ['label' => __('Dashboard'), 'url' => route('dashboard')];
        $resource = ['label' => $this->resourceLabel(), 'url' => route($this->routes()['active'])];

        return match ($state) {
            'active' => [$dashboard, ['label' => $this->resourceLabel(), 'url' => null]],
            'archived' => [$dashboard, $resource, ['label' => __('Archived'), 'url' => null]],
            'trash' => [$dashboard, $resource, ['label' => __('Trash'), 'url' => null]],
        };
    }

    /**
     * @param  'active'|'archived'|'trash'  $state
     */
    private function emptyMessage(string $state, string $search): string
    {
        if ($search !== '') {
            return $this->noMatchMessage();
        }

        return match ($state) {
            'active' => $this->noRecordsMessage(),
            'archived' => __('No archived records.'),
            'trash' => __('Trash is empty.'),
        };
    }

    /**
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    final protected function editAction(Model $record): array
    {
        return [
            'type' => 'form-modal',
            'label' => __('Edit'),
            'icon' => 'pencil-square',
            'test' => $this->resourceKey().'-edit-'.$this->key($record),
            $this->resourceKey() => $this->editPayload($record),
        ];
    }

    /**
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    final protected function deleteAction(Model $record): array
    {
        return $this->confirmAction(
            $record,
            __('Delete'),
            'trash',
            'delete',
            route($this->routes()['destroy'], $record),
            'DELETE',
            __('Delete record?'),
            __('You can restore it from the trash.'),
            danger: true,
        );
    }

    /**
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    final protected function archiveAction(Model $record): array
    {
        return $this->confirmAction(
            $record,
            __('Archive'),
            'archive-box',
            'archive',
            route($this->routes()['archive'], $record),
            'PATCH',
            __('Archive record?'),
            __('You can activate it from the archived list.'),
            danger: true,
        );
    }

    /**
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    final protected function activateAction(Model $record): array
    {
        return $this->confirmAction(
            $record,
            __('Activate'),
            'archive-box-arrow-down',
            'activate',
            route($this->routes()['activate'], $record),
            'PATCH',
            __('Activate record?'),
            __('The record will return to the active list.'),
            danger: false,
        );
    }

    /**
     * A trashed record goes back to the list its active flag sends it to.
     *
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    final protected function restoreAction(Model $record, bool $active): array
    {
        return $this->confirmAction(
            $record,
            __('Restore'),
            'arrow-path',
            'restore',
            route($this->routes()['restore'], $this->key($record)),
            'PATCH',
            __('Restore record?'),
            $active
                ? __('The record will return to the active list.')
                : __('The record will return to the archived list.'),
            danger: false,
        );
    }

    /**
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    final protected function forceDeleteAction(Model $record): array
    {
        return $this->confirmAction(
            $record,
            __('Delete permanently'),
            'trash',
            'force-delete',
            route($this->routes()['trashDestroy'], $this->key($record)),
            'DELETE',
            __('Permanently delete record?'),
            __('This action cannot be undone.'),
            danger: true,
        );
    }

    /**
     * @return array{type: string, label: string, icon: string, test: string, hint: string}
     */
    final protected function blockedAction(string $label, string $icon, string $test, string $hint): array
    {
        return [
            'type' => 'blocked',
            'label' => $label,
            'icon' => $icon,
            'test' => $test,
            'hint' => $hint,
        ];
    }

    /**
     * @param  TRecord  $record
     * @return array{type: string, label: string, icon: string, test: string, danger: bool, action: string, method: string, confirmTitle: string, confirmText: string, confirmLabel: string}
     */
    private function confirmAction(
        Model $record,
        string $label,
        string $icon,
        string $action,
        string $url,
        string $method,
        string $confirmTitle,
        string $confirmText,
        bool $danger,
    ): array {
        return [
            'type' => 'confirm-modal',
            'label' => $label,
            'icon' => $icon,
            'test' => $this->resourceKey().'-'.$action.'-'.$this->key($record),
            'danger' => $danger,
            'action' => $url,
            'method' => $method,
            'confirmTitle' => $confirmTitle,
            'confirmText' => $confirmText,
            'confirmLabel' => $label,
        ];
    }

    /**
     * Key of the record as the data-test attributes spell it.
     */
    private function key(Model $record): string
    {
        $key = $record->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * Translated placeholder of the search box. Shared by every resource: the box lives in the
     * common page skeleton, so it does not name the resource it filters.
     */
    protected function searchPlaceholder(): string
    {
        return __('Search record');
    }

    /**
     * Translated plural name of the resource.
     */
    abstract protected function resourceLabel(): string;

    /**
     * Translated message shown when a search returns nothing.
     */
    abstract protected function noMatchMessage(): string;

    /**
     * Translated message shown when the resource has no records yet.
     */
    abstract protected function noRecordsMessage(): string;

    /**
     * Singular slug of the resource. It names the data-test attributes of the row actions, the
     * state tabs and the payload key the edit action carries.
     */
    abstract protected function resourceKey(): string;

    /**
     * Routes this transformer links to, indexed by list state and by action.
     *
     * @return array{active: string, archived: string, trash: string, destroy: string, archive: string, activate: string, restore: string, trashDestroy: string}
     */
    abstract protected function routes(): array;

    /**
     * Fields the form needs to open in edit mode. Shared by the row name button and the row edit
     * action so both always open the drawer with the very same data.
     *
     * @param  TRecord  $record
     * @return array<string, mixed>
     */
    abstract protected function editPayload(Model $record): array;
}
