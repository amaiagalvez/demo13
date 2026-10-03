<?php

namespace App\Transformers;

use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectListTransformer extends ListTransformer
{
    /**
     * @param  LengthAwarePaginator<int, Project>  $projects
     * @param  array{active: int, inactive: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $projects, string $search, ?array $counts = null): array
    {
        return [
            'resource' => __('Projects'),
            'state' => 'active',
            'breadcrumbs' => [
                ['label' => __('Dashboard'), 'url' => route('dashboard')],
                ['label' => __('Projects'), 'url' => null],
            ],
            'extraDateHeading' => __('Created at'),
            'emptyMessage' => $search === ''
                ? __('No projects yet.')
                : __('No projects match your search.'),
            'search' => $this->search(route('projects.index'), $search, __('Search projects...')),
            'tabs' => $this->tabs('active', $counts),
            'create' => true,
            'rows' => collect($projects->items())->map(fn (Project $project): array => [
                ...$this->columns($project),
                'extraDate' => $project->created_at?->toIso8601String(),
                'actionHint' => $project->epics_exists
                    ? __('Project cannot be deleted while it has epics.')
                    : null,
                'actions' => [
                    [
                        'type' => 'form-modal',
                        'label' => __('Edit'),
                        'icon' => 'pencil-square',
                        'test' => 'project-edit-'.$project->id,
                        'project' => $this->editPayload($project),
                    ],
                    $project->epics_exists ? [
                        'type' => 'confirm-modal',
                        'label' => __('Deactivate'),
                        'icon' => 'lock-closed',
                        'test' => 'project-deactivate-'.$project->id,
                        'danger' => true,
                        'action' => route('projects.deactivate', $project),
                        'method' => 'PATCH',
                        'confirmTitle' => __('Deactivate record?'),
                        'confirmText' => __('You can reactivate it from the inactive list.'),
                        'confirmLabel' => __('Deactivate'),
                    ] : [
                        'type' => 'confirm-modal',
                        'label' => __('Delete'),
                        'icon' => 'trash',
                        'test' => 'project-delete-'.$project->id,
                        'danger' => true,
                        'action' => route('projects.destroy', $project),
                        'method' => 'DELETE',
                        'confirmTitle' => __('Delete project?'),
                        'confirmText' => __('You can restore it from the trash.'),
                        'confirmLabel' => __('Delete'),
                    ],
                ],
            ])->all(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Project>  $projects
     * @param  array{active: int, inactive: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function inactive(LengthAwarePaginator $projects, string $search, ?array $counts = null): array
    {
        return [
            'resource' => __('Projects'),
            'state' => 'inactive',
            'breadcrumbs' => [
                ['label' => __('Dashboard'), 'url' => route('dashboard')],
                ['label' => __('Projects'), 'url' => route('projects.index')],
                ['label' => __('Inactive'), 'url' => null],
            ],
            'extraDateHeading' => __('Updated at'),
            'emptyMessage' => $search === '' ? __('No inactive records.') : __('No projects match your search.'),
            'search' => $this->search(route('projects.inactive.index'), $search, __('Search projects...')),
            'tabs' => $this->tabs('inactive', $counts),
            'create' => false,
            'rows' => collect($projects->items())->map(fn (Project $project): array => [
                ...$this->columns($project),
                'extraDate' => $project->updated_at?->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Reactivate'),
                        'icon' => 'lock-open',
                        'test' => 'project-reactivate-'.$project->id,
                        'danger' => false,
                        'action' => route('projects.inactive.reactivate', $project),
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
     * @param  LengthAwarePaginator<int, Project>  $projects
     * @param  array{active: int, inactive: int, trashed: int}|null  $counts
     * @return array<string, mixed>
     */
    public function trash(LengthAwarePaginator $projects, string $search, ?array $counts = null): array
    {
        return [
            'resource' => __('Projects'),
            'state' => 'trash',
            'breadcrumbs' => [
                ['label' => __('Dashboard'), 'url' => route('dashboard')],
                ['label' => __('Projects'), 'url' => route('projects.index')],
                ['label' => __('Trash'), 'url' => null],
            ],
            'extraDateHeading' => __('Deleted at'),
            'emptyMessage' => $search === ''
                ? __('Trash is empty.')
                : __('No projects match your search.'),
            'search' => $this->search(route('projects.trash.index'), $search, __('Search projects...')),
            'tabs' => $this->tabs('trash', $counts),
            'create' => false,
            'rows' => collect($projects->items())->map(fn (Project $project): array => [
                ...$this->columns($project),
                'extraDate' => $project->deleted_at?->toIso8601String(),
                'actions' => [
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Restore'),
                        'icon' => 'arrow-path',
                        'test' => 'project-restore-'.$project->id,
                        'danger' => false,
                        'action' => route('projects.trash.restore', $project->id),
                        'method' => 'PATCH',
                        'confirmTitle' => __('Restore project?'),
                        'confirmText' => $project->active
                            ? __('The project will return to the active list.')
                            : __('The record will return to the inactive list.'),
                        'confirmLabel' => __('Restore'),
                    ],
                    [
                        'type' => 'confirm-modal',
                        'label' => __('Delete permanently'),
                        'icon' => 'trash',
                        'test' => 'project-force-delete-'.$project->id,
                        'danger' => true,
                        'action' => route('projects.trash.destroy', $project->id),
                        'method' => 'DELETE',
                        'confirmTitle' => __('Permanently delete project?'),
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
                'label' => __('Projects'),
                'url' => route('projects.index'),
                'current' => $state === 'active',
                'count' => $counts['active'] ?? null,
                'test' => 'project-active-link',
            ],
            [
                'label' => __('Inactive'),
                'url' => route('projects.inactive.index'),
                'current' => $state === 'inactive',
                'count' => $counts['inactive'] ?? null,
                'test' => 'project-inactive-link',
            ],
            [
                'label' => __('Trash'),
                'url' => route('projects.trash.index'),
                'current' => $state === 'trash',
                'count' => $counts['trashed'] ?? null,
                'test' => 'project-trash-link',
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, customer: string, startDate: string, endDate: string, epicsCount: int, epicsUrl: string|null, commentsCount: int, editPayload: array{id: int, name: string, start_date: string, end_date: string, customer_id: int, customer_name: string}}
     */
    private function columns(Project $project): array
    {
        $epicsCount = (int) $project->epics_count;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'customer' => $project->customer->name ?? '—',
            'startDate' => $project->start_date->format('Y-m-d'),
            'endDate' => $project->end_date?->format('Y-m-d') ?? '',
            'epicsCount' => $epicsCount,
            // The epics list searches project names, so the row count can link to its own slice.
            'epicsUrl' => $epicsCount > 0
                ? route('epics.index', ['search' => $project->name])
                : null,
            'commentsCount' => (int) $project->comments_count,
            'editPayload' => $this->editPayload($project),
        ];
    }

    /**
     * Fields the form needs to open in edit mode. Shared by the row name button and the row edit
     * action so both always open the drawer with the very same data.
     *
     * @return array{id: int, name: string, start_date: string, end_date: string, customer_id: int, customer_name: string}
     */
    private function editPayload(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'start_date' => $project->start_date->toDateString(),
            'end_date' => $project->end_date?->toDateString() ?? '',
            'customer_id' => $project->customer_id,
            'customer_name' => $project->customer->name ?? '—',
        ];
    }
}
