<?php

namespace App\Queries\Projects;

use App\Models\Project;
use App\Queries\ListQueryBase;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends ListQueryBase<Project>
 */
final class ProjectSelectOptionsQuery extends ListQueryBase
{
    private const RESULTS_LIMIT = 20;

    /**
     * @return Collection<int, array{id: int<0, max>, text: non-falsy-string}>
     */
    public function search(string $search): Collection
    {
        $pattern = $this->searchPattern($search);

        return Project::query()
            ->where('projects.active', true)
            ->with('customer:id,name')
            ->when($search !== '', function (Builder $query) use ($pattern): void {
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereRaw(
                        'projects.name LIKE ? ESCAPE \'' . self::LIKE_ESCAPE . '\'',
                        [$pattern],
                    )->orWhereHas('customer', fn(Builder $customerQuery) => $customerQuery->whereRaw(
                        'customers.name LIKE ? ESCAPE \'' . self::LIKE_ESCAPE . '\'',
                        [$pattern],
                    ));
                });
            })
            ->orderBy('projects.name')
            ->orderBy('projects.id')
            ->limit(self::RESULTS_LIMIT)
            ->get(['id', 'name', 'customer_id'])
            ->map(fn(Project $project): array => [
                'id' => $project->id,
                'text' => $project->name . ' (' . ($project->customer->name ?? '—') . ')',
            ]);
    }
}
