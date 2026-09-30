<?php

namespace App\Transformers;

use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectListTransformer
{
    /**
     * @param  LengthAwarePaginator<int, Project>  $projects
     * @return array<string, mixed>
     */
    public function active(LengthAwarePaginator $projects, string $search): array
    {
        return [
            'title' => __('Projects'),
            'subtitle' => __('Manage your projects.'),
            'emptyMessage' => $search === ''
                ? __('No projects yet.')
                : __('No projects match your search.'),
            'search' => $this->search(route('projects.index'), $search),
            'navigation' => [
                'label' => __('Trash'),
                'url' => route('projects.trash.index'),
                'icon' => 'trash',
                'test' => 'project-trash-link',
            ],
            'create' => true,
            'rows' => collect($projects->items())->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'customer' => $project->customer->name,
                'startDate' => $project->start_date->format('Y-m-d'),
                'endDate' => $project->end_date?->format('Y-m-d') ?? '',
                'actions' => [
                    [
                        'type' => 'form-modal',
                        'label' => __('Edit'),
                        'icon' => 'pencil-square',
                        'test' => 'project-edit-'.$project->id,
                        'project' => [
                            'id' => $project->id,
                            'name' => $project->name,
                            'start_date' => $project->start_date->toDateString(),
                            'end_date' => $project->end_date?->toDateString() ?? '',
                            'customer_id' => $project->customer_id,
                        ],
                    ],
                    [
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
     * @return array<string, mixed>
     */
    public function trash(LengthAwarePaginator $projects, string $search): array
    {
        return [
            'title' => __('Project trash'),
            'subtitle' => __('Restore projects or delete them permanently.'),
            'emptyMessage' => $search === ''
                ? __('Trash is empty.')
                : __('No projects match your search.'),
            'search' => $this->search(route('projects.trash.index'), $search),
            'navigation' => [
                'label' => __('Projects'),
                'url' => route('projects.index'),
                'icon' => 'arrow-left',
                'test' => null,
            ],
            'create' => false,
            'rows' => collect($projects->items())->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'customer' => $project->customer->name,
                'startDate' => $project->start_date->format('Y-m-d'),
                'endDate' => $project->end_date?->format('Y-m-d') ?? '',
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
                        'confirmText' => __('The project will return to the active list.'),
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
     * @return array{action: string, value: string, placeholder: string}
     */
    private function search(string $action, string $value): array
    {
        return [
            'action' => $action,
            'value' => $value,
            'placeholder' => __('Search projects...'),
        ];
    }
}
