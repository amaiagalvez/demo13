@php
    $editingEpic = $list['create'] && str_starts_with(old('_epic_form', ''), 'edit-');
    $deletedEpicConflict = session('deleted_epic_conflict');
    $findEpicPayload = fn(mixed $id): ?array => collect($list['rows'])->firstWhere('id', (int) $id)[
        'actions'
    ][0]['epic'] ?? null;
    $hasCommentErrors = $errors->getBag('comment')->any();
    $commentedEpic = $list['create']
        ? $findEpicPayload(
            session('commented_epic_id') ?? ($hasCommentErrors ? old('_comment_epic_id') : null),
        )
        : null;
    $editingEpicPayload = $editingEpic ? $findEpicPayload(old('_epic_id')) : null;
    $initialForm = $list['create']
        ? [
            'id' => $editingEpic ? old('_epic_id') : null,
            'name' => old('name', ''),
            'start_date' => old('start_date', ''),
            'end_date' => old('end_date', ''),
            'project_id' => old('project_id', ''),
            'comments' => [],
            'commentsCount' => $editingEpicPayload['commentsCount'] ?? 0,
            'commentsLoading' => false,
            'commentsError' => '',
            'commentAction' => $editingEpicPayload['commentAction'] ?? '',
            'commentBody' => '',
            'context' => $editingEpic ? old('_epic_form') : 'create',
            'method' => $editingEpic ? 'PUT' : 'POST',
            'action' => $editingEpic
                ? route('epics.update', old('_epic_id'))
                : route('epics.store'),
            'title' => $editingEpic ? __('Edit epic') : __('New epic'),
            'subtitle' => $editingEpic
                ? __('Update the epic details.')
                : __('Add an epic to a project.'),
            'submitLabel' => $editingEpic ? __('Update epic') : __('Save epic'),
        ]
        : null;
@endphp

<x-layouts::app :title="$list['resource']">
    <div x-data="{
        form: @js($initialForm),
        projectOptions: @js($selectedProjectOption ? [$selectedProjectOption] : []),
        projectOptionsUrl: @js(route('projects.options', [], false)),
        projectOptionsRequest: null,
        projectOptionsLoading: false,
        projectOptionsSearched: false,
        projectOptionsResultCount: 0,
        projectOptionsError: '',
        confirmation: {
            action: '',
            method: 'DELETE',
            title: '',
            text: '',
            label: '',
            danger: false,
        },
        storeUrl: @js(route('epics.store')),
        updateUrl: @js(route('epics.update', '__EPIC__')),
        async searchProjects() {
            this.projectOptionsRequest?.abort();
            const controller = new AbortController();
            this.projectOptionsRequest = controller;
            this.projectOptionsLoading = true;
            this.projectOptionsError = '';

            const url = new URL(this.projectOptionsUrl, window.location.href);
            url.searchParams.set('q', '');

            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error();
                }

                const result = await response.json();
                const selectedProject = this.projectOptions.find(
                    (project) => String(project.id) === String(this.form.project_id),
                );
                const results = result.results;
                this.projectOptionsResultCount = results.length;
                this.projectOptions = selectedProject && !results.some(
                    (project) => String(project.id) === String(selectedProject.id),
                ) ? [selectedProject, ...results] : results;
                this.projectOptionsSearched = true;
            } catch {
                if (!controller.signal.aborted) {
                    this.projectOptionsError = @js(__('Unable to load projects.'));
                }
            } finally {
                if (this.projectOptionsRequest === controller) {
                    this.projectOptionsLoading = false;
                }
            }
        },
        createEpic() {
            this.projectOptionsRequest?.abort();
            this.projectOptions = [];
            this.projectOptionsSearched = false;
            this.projectOptionsResultCount = 0;
            this.projectOptionsError = '';
            this.form = {
                id: null,
                name: '',
                start_date: '',
                end_date: '',
                project_id: '',
                comments: [],
                commentsCount: 0,
                commentsLoading: false,
                commentsError: '',
                commentAction: '',
                commentBody: '',
                context: 'create',
                method: 'POST',
                action: this.storeUrl,
                title: @js(__('New epic')),
                subtitle: @js(__('Add an epic to a project.')),
                submitLabel: @js(__('Save epic')),
            };
        },
        editEpic(epic, commentBody = '') {
            this.commentRequest?.abort();
            this.projectOptionsRequest?.abort();
            this.projectOptions = [{
                id: String(epic.project_id),
                text: epic.project_label,
            }];
            this.projectOptionsSearched = false;
            this.projectOptionsResultCount = 0;
            this.projectOptionsError = '';
            this.form = {
                ...epic,
                comments: [],
                commentsLoading: false,
                commentsError: '',
                project_id: String(epic.project_id),
                commentBody,
                context: `edit-${epic.id}`,
                method: 'PUT',
                action: this.updateUrl.replace('__EPIC__', epic.id),
                title: @js(__('Edit epic')),
                subtitle: @js(__('Update the epic details.')),
                submitLabel: @js(__('Update epic')),
            };
            this.loadEpicComments();
        },
        async loadEpicComments() {
            this.commentRequest?.abort();
            const controller = new AbortController();
            const epicId = this.form.id;
            this.commentRequest = controller;
            this.form.comments = [];
            this.form.commentsLoading = true;
            this.form.commentsError = '';

            try {
                const response = await fetch(this.form.commentsUrl, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error();
                }

                const result = await response.json();

                if (this.form.id === epicId) {
                    this.form.comments = result.comments;
                }
            } catch {
                if (!controller.signal.aborted && this.form.id === epicId) {
                    this.form.commentsError = @js(__('Unable to load comments.'));
                }
            } finally {
                if (this.form.id === epicId) {
                    this.form.commentsLoading = false;
                }

                if (this.commentRequest === controller) {
                    this.commentRequest = null;
                }
            }
        },
        confirmAction(action) {
            this.confirmation = {
                action: action.action,
                method: action.method,
                title: action.confirmTitle,
                text: action.confirmText,
                label: action.confirmLabel,
                danger: action.danger,
            };
        },
    }"
        @if ($commentedEpic) x-init="editEpic(@js($commentedEpic), @js($hasCommentErrors ? old('body', '') : '')); $nextTick(() => $dispatch('modal-show', { name: 'epic-form' }))"
        @elseif ($errors->any())
            x-init="@if ($editingEpicPayload) loadEpicComments(); @endif $nextTick(() => $dispatch('modal-show', { name: 'epic-form' }))"
        @elseif ($deletedEpicConflict)
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'epic-name-conflict' }))" @endif
        class="flex flex-col gap-4">
        <x-list.header :list="$list" prefix="epic" :create-label="__('New epic')"
            create-click="createEpic()" />

        @if ($list['create'] && ! $hasProjects)
            <flux:callout icon="exclamation-triangle" variant="warning">
                {{ __('No active projects are available. Create a project before adding epics.') }}
            </flux:callout>
        @endif

        <x-list.flash prefix="epic" />

        @fragment('list-results')
        <x-list.searchable-results :search="$list['search']">
            <x-list.search :search="$list['search']" prefix="epic" />

            <x-list.table prefix="epic" :paginator="$epics">
                <flux:table.columns>
                    <flux:table.column class="resource-list-actions">
                        <span class="sr-only">{{ __('Actions') }}</span>
                    </flux:table.column>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Project') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Start date') }}</flux:table.column>
                    <flux:table.column>{{ __('End date') }}</flux:table.column>
                    <flux:table.column>{{ __('Comments') }}</flux:table.column>
                    @if ($list['extraDateHeading'])
                        <flux:table.column>{{ $list['extraDateHeading'] }}</flux:table.column>
                    @endif
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($list['rows'] as $row)
                        <flux:table.row :key="$row['id']"
                            class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <flux:table.cell class="resource-list-actions">
                                <x-list.row-actions :actions="$row['actions']" prefix="epic"
                                    payload-key="epic" edit-handler="editEpic" />
                            </flux:table.cell>
                            <flux:table.cell class="max-w-[16rem] truncate font-medium" :title="$row['name']">
                                {{ $row['name'] }}
                            </flux:table.cell>
                            <flux:table.cell class="max-w-[16rem] truncate" :title="$row['project']">
                                {{ $row['project'] }}
                            </flux:table.cell>
                            <flux:table.cell class="max-w-[16rem] truncate" :title="$row['customer']">
                                {{ $row['customer'] }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <x-list.local-time :datetime="$row['startDate']" format="date" />
                            </flux:table.cell>
                            <flux:table.cell>
                                <x-list.local-time :datetime="$row['endDate']" format="date" />
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" icon="chat-bubble-left"
                                    :data-test="'epic-comments-count-'.$row['id']">
                                    {{ $row['commentsCount'] }}
                                </flux:badge>
                            </flux:table.cell>
                            @if ($list['extraDateHeading'])
                                <flux:table.cell>
                                    <x-list.local-time :datetime="$row['extraDate']"
                                        format="datetime" />
                                </flux:table.cell>
                            @endif
                        </flux:table.row>
                    @empty
                        <x-list.empty-state :colspan="$list['create'] ? 7 : 8" :message="$list['emptyMessage']" />
                    @endforelse
                </flux:table.rows>
            </x-list.table>
        </x-list.searchable-results>
        @endfragment

        @if ($list['create'])
            <flux:modal name="epic-form" aria-labelledby="epic-form-heading" variant="flyout" position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="form-drawer max-w-none">
                @include('epics.form')
            </flux:modal>

            @if ($deletedEpicConflict)
                <x-name-conflict-modal name="epic-name-conflict" :title="__('Epic name already in trash')" :message="__('A deleted epic in this project already uses the name :name.', [
                    'name' => $deletedEpicConflict['name'],
                ])"
                    :create-action="route('epics.store')" :restore-action="route('epics.trash.restore', $deletedEpicConflict['id'])" :create-fields="[
                        'name' => old('name', $deletedEpicConflict['name']),
                        'start_date' => old('start_date'),
                        'end_date' => old('end_date'),
                        'project_id' => old('project_id'),
                    ]" :create-label="__('Create a new epic')"
                    :restore-label="__('Restore the deleted epic instead')" create-test="epic-conflict-create-new"
                    restore-test="epic-conflict-restore" />
            @endif
        @endif

        <x-list.confirm-modal prefix="epic" />
    </div>
</x-layouts::app>
