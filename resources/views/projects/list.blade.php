@php
    $editingProject = $list['create'] && str_starts_with(old('_project_form', ''), 'edit-');
    $deletedProjectConflict = session('deleted_project_conflict');
    $initialForm = $list['create']
        ? [
            'id' => $editingProject ? old('_project_id') : null,
            'name' => old('name', ''),
            'start_date' => old('start_date', ''),
            'end_date' => old('end_date', ''),
            'customer_id' => old('customer_id', ''),
            'customerCreateError' => '',
            'customerCreating' => false,
            'context' => $editingProject ? old('_project_form') : 'create',
            'method' => $editingProject ? 'PUT' : 'POST',
            'action' => $editingProject
                ? route('projects.update', old('_project_id'))
                : route('projects.store'),
            'title' => $editingProject ? __('Edit project') : __('New project'),
            'subtitle' => $editingProject
                ? __('Update the project details.')
                : __('Add a project to your records.'),
            'submitLabel' => $editingProject ? __('Update project') : __('Save project'),
        ]
        : null;
@endphp

<x-layouts::app :title="$list['title']">
    <div data-project-form-root x-data="{
        form: @js($initialForm),
        confirmation: {
            action: '',
            method: 'DELETE',
            title: '',
            text: '',
            label: '',
            danger: false,
        },
        storeUrl: @js(route('projects.store')),
        updateUrl: @js(route('projects.update', '__PROJECT__')),
        createProject() {
            this.form = {
                id: null,
                name: '',
                start_date: '',
                end_date: '',
                customer_id: '',
                customerCreateError: '',
                customerCreating: false,
                context: 'create',
                method: 'POST',
                action: this.storeUrl,
                title: @js(__('New project')),
                subtitle: @js(__('Add a project to your records.')),
                submitLabel: @js(__('Save project')),
            };
        },
        editProject(project) {
            this.form = {
                ...project,
                customerCreateError: '',
                customerCreating: false,
                context: `edit-${project.id}`,
                method: 'PUT',
                action: this.updateUrl.replace('__PROJECT__', project.id),
                title: @js(__('Edit project')),
                subtitle: @js(__('Update the project details.')),
                submitLabel: @js(__('Update project')),
            };
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
        @if ($errors->any())
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'project-form' }))"
        @elseif ($deletedProjectConflict)
            x-init="$nextTick(() => $dispatch('modal-show', { name: 'project-name-conflict' }))"
        @endif
        class="flex flex-col gap-6">
        <x-list.header :list="$list" prefix="project" :create-label="__('New project')"
            create-click="createProject()" />

        @if ($list['create'] && $availableCustomers->isEmpty())
            <flux:callout icon="exclamation-triangle" variant="warning">
                {{ __('No active customers are available. Create one from the project form.') }}
            </flux:callout>
        @endif

        <x-list.flash prefix="project" />

        <x-list.search :search="$list['search']" prefix="project" />

        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Start date') }}</flux:table.column>
                    <flux:table.column>{{ __('End date') }}</flux:table.column>
                    <flux:table.column class="text-end">
                        <span class="sr-only">{{ __('Actions') }}</span>
                    </flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($list['rows'] as $row)
                        <flux:table.row :key="$row['id']">
                            <flux:table.cell class="font-medium">{{ $row['name'] }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $row['customer'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['startDate'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['endDate'] }}</flux:table.cell>
                            <flux:table.cell class="text-end">
                                <x-list.row-actions :actions="$row['actions']" prefix="project"
                                    payload-key="project" edit-handler="editProject" />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">
                                {{ $list['emptyMessage'] }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($projects->hasPages())
            <nav aria-label="{{ __('Pagination') }}" data-test="project-pagination">
                {{ $projects->links() }}
            </nav>
        @endif

        @if ($list['create'])
            <flux:modal name="project-form" variant="flyout" position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="project-drawer max-w-none">
                @include('projects.form')
            </flux:modal>

            @if ($deletedProjectConflict)
                <x-name-conflict-modal
                    name="project-name-conflict"
                    :title="__('Project name already in trash')"
                    :message="__('A deleted project already uses the name :name.', ['name' => $deletedProjectConflict['name']])"
                    :create-action="route('projects.store')"
                    :restore-action="route('projects.trash.restore', $deletedProjectConflict['id'])"
                    :create-fields="[
                        'name' => old('name', $deletedProjectConflict['name']),
                        'start_date' => old('start_date'),
                        'end_date' => old('end_date'),
                        'customer_id' => old('customer_id'),
                    ]"
                    :create-label="__('Create a new project')"
                    :restore-label="__('Restore the deleted project instead')"
                    create-test="project-conflict-create-new"
                    restore-test="project-conflict-restore" />
            @endif
        @endif

        <x-list.confirm-modal prefix="project" />
    </div>
</x-layouts::app>
