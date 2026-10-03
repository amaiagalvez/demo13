<?php

namespace App\Transformers;

use App\Models\Epic;
use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class EpicListTransformer extends ListTransformer
{
    /**
     * @param  LengthAwarePaginator<int, Epic>  $epics
     * @param  array{active: int, inactive: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $epics, string $search, ?array $counts = null): array
    {
        return [
            'resource' => __('Epics'),
            'state' => 'active',
            'breadcrumbs' => [
                ['label' => __('Dashboard'), 'url' => route('dashboard')],
                ['label' => __('Epics'), 'url' => null],
            ],
            'extraDateHeading' => __('Created at'),
            'emptyMessage' => $search === ''
                ? __('No epics yet.')
                : __('No epics match your search.'),
            'search' => $this->search(route('epics.index'), $search, __('Search epics...')),
            'tabs' => $this->tabs('active', $counts),
            'create' => true,
            'rows' => collect($epics->items())->map(fn (Epic $epic): array => [
                ...$this->columns($epic),
                'extraDate' => $epic->created_at?->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'form-modal',
                        'label' => __('Edit'),
                        'icon' => 'pencil-square',
                        'test' => 'epic-edit-'.$epic->id,
                        'epic' => $this->editPayload($epic),
                    ],
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Delete'),
                        'icon' => 'trash',
                        'test' => 'epic-delete-'.$epic->id,
                        'danger' => true,
                        'action' => route('epics.destroy', $epic),
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
     * @param  LengthAwarePaginator<int, Epic>  $epics
     * @param  array{active: int, inactive: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function inactive(LengthAwarePaginator $epics, string $search, ?array $counts = null): array
    {
        return [
            'resource' => __('Epics'),
            'state' => 'inactive',
            'breadcrumbs' => [
                ['label' => __('Dashboard'), 'url' => route('dashboard')],
                ['label' => __('Epics'), 'url' => route('epics.index')],
                ['label' => __('Inactive'), 'url' => null],
            ],
            'extraDateHeading' => __('Updated at'),
            'emptyMessage' => $search === '' ? __('No inactive records.') : __('No epics match your search.'),
            'search' => $this->search(route('epics.inactive.index'), $search, __('Search epics...')),
            'tabs' => $this->tabs('inactive', $counts),
            'create' => false,
            'rows' => collect($epics->items())->map(fn (Epic $epic): array => [
                ...$this->columns($epic),
                'extraDate' => $epic->updated_at?->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Reactivate'),
                        'icon' => 'lock-open',
                        'test' => 'epic-reactivate-'.$epic->id,
                        'danger' => false,
                        'action' => route('epics.inactive.reactivate', $epic),
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
     * @param  LengthAwarePaginator<int, Epic>  $epics
     * @param  array{active: int, inactive: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function trash(LengthAwarePaginator $epics, string $search, ?array $counts = null): array
    {
        return [
            'resource' => __('Epics'),
            'state' => 'trash',
            'breadcrumbs' => [
                ['label' => __('Dashboard'), 'url' => route('dashboard')],
                ['label' => __('Epics'), 'url' => route('epics.index')],
                ['label' => __('Trash'), 'url' => null],
            ],
            'extraDateHeading' => __('Deleted at'),
            'emptyMessage' => $search === ''
                ? __('Trash is empty.')
                : __('No epics match your search.'),
            'search' => $this->search(route('epics.trash.index'), $search, __('Search epics...')),
            'tabs' => $this->tabs('trash', $counts),
            'create' => false,
            'rows' => collect($epics->items())->map(fn (Epic $epic): array => [
                ...$this->columns($epic),
                'extraDate' => $epic->deleted_at?->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Restore'),
                        'icon' => 'arrow-path',
                        'test' => 'epic-restore-'.$epic->id,
                        'danger' => false,
                        'action' => route('epics.trash.restore', $epic->id),
                        'method' => 'PATCH',
                        'confirmTitle' => __('Restore record?'),
                        'confirmText' => $epic->active
                            ? __('The record will return to the active list.')
                            : __('The record will return to the inactive list.'),
                        'confirmLabel' => __('Restore'),
                    ],
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Delete permanently'),
                        'icon' => 'trash',
                        'test' => 'epic-force-delete-'.$epic->id,
                        'danger' => true,
                        'action' => route('epics.trash.destroy', $epic->id),
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
                'label' => __('Epics'),
                'url' => route('epics.index'),
                'current' => $state === 'active',
                'count' => $counts['active'] ?? null,
                'test' => 'epic-active-link',
            ],
            [
                'label' => __('Inactive'),
                'url' => route('epics.inactive.index'),
                'current' => $state === 'inactive',
                'count' => $counts['inactive'] ?? null,
                'test' => 'epic-inactive-link',
            ],
            [
                'label' => __('Trash'),
                'url' => route('epics.trash.index'),
                'current' => $state === 'trash',
                'count' => $counts['trashed'] ?? null,
                'test' => 'epic-trash-link',
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, project: string, customer: string, startDate: string, endDate: string, commentsCount: int, editPayload: array{id: int, name: string, start_date: string, end_date: string, project_id: int, project_label: string, commentAction: string, commentsUrl: string, commentsCount: int}}
     */
    private function columns(Epic $epic): array
    {
        $project = $epic->project;

        return [
            'id' => $epic->id,
            'name' => $epic->name,
            'project' => $project->name ?? '—',
            'customer' => $project->customer->name ?? '—',
            'startDate' => $epic->start_date?->format('Y-m-d') ?? '',
            'endDate' => $epic->end_date?->format('Y-m-d') ?? '',
            'commentsCount' => (int) $epic->comments_count,
            'editPayload' => $this->editPayload($epic),
        ];
    }

    /**
     * Fields the form needs to open in edit mode. Shared by the row name button and the row edit
     * action so both always open the drawer with the very same data.
     *
     * @return array{id: int, name: string, start_date: string, end_date: string, project_id: int, project_label: string, commentAction: string, commentsUrl: string, commentsCount: int}
     */
    private function editPayload(Epic $epic): array
    {
        return [
            'id' => $epic->id,
            'name' => $epic->name,
            'start_date' => $epic->start_date?->toDateString() ?? '',
            'end_date' => $epic->end_date?->toDateString() ?? '',
            'project_id' => $epic->project_id,
            'project_label' => $this->projectLabel($epic),
            'commentAction' => route('epics.comments.store', $epic),
            'commentsUrl' => route('epics.comments.index', $epic),
            'commentsCount' => (int) $epic->comments_count,
        ];
    }

    private function projectLabel(Epic $epic): string
    {
        $project = $epic->project;

        if (! $project instanceof Project) {
            return '—';
        }

        return $project->name.' ('.($project->customer->name ?? '—').')';
    }
}
