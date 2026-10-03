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
            'customer_name' => $selectedCustomer?->name ?? '',
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

<x-layouts::app :title="$list['resource']">
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
                customer_name: '',
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
        class="flex flex-col gap-4">
        <x-list.header :list="$list" prefix="project" :create-label="__('New project')"
            create-click="createProject()" />

        @if ($list['create'] && ! $hasCustomers)
            <flux:callout icon="exclamation-triangle" variant="warning">
                {{ __('No active customers are available. Create one from the project form.') }}
            </flux:callout>
        @endif

        <x-list.flash prefix="project" />

        @fragment('list-results')
        <x-list.searchable-results :search="$list['search']">
            <x-list.search :search="$list['search']" prefix="project" />

            <x-list.table prefix="project" :paginator="$projects">
                <flux:table.columns>
                    <flux:table.column class="resource-list-actions">
                        <span class="sr-only">{{ __('Actions') }}</span>
                    </flux:table.column>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer') }}</flux:table.column>
                    <flux:table.column>{{ __('Start date') }}</flux:table.column>
                    <flux:table.column>{{ __('End date') }}</flux:table.column>
                    @if ($list['extraDateHeading'])
                        <flux:table.column>{{ $list['extraDateHeading'] }}</flux:table.column>
                    @endif
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($list['rows'] as $row)
                        <flux:table.row :key="$row['id']"
                            class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <flux:table.cell class="resource-list-actions">
                                <x-list.row-actions :actions="$row['actions']" prefix="project"
                                    payload-key="project" edit-handler="editProject" />
                            </flux:table.cell>
                            <flux:table.cell class="font-medium">{{ $row['name'] }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $row['customer'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['startDate'] }}</flux:table.cell>
                            <flux:table.cell>{{ $row['endDate'] }}</flux:table.cell>
                            @if ($list['extraDateHeading'])
                                <flux:table.cell>{{ $row['extraDate'] }}</flux:table.cell>
                            @endif
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell :colspan="$list['create'] ? 5 : 6" class="py-8 text-center text-zinc-500">
                                <div class="flex flex-col items-center gap-3 px-4 py-4">
                                    <flux:icon.magnifying-glass class="size-8 text-zinc-400 dark:text-zinc-500"
                                        aria-hidden="true" />
                                    <span>{{ $list['emptyMessage'] }}</span>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </x-list.table>
        </x-list.searchable-results>
        @endfragment

        @if ($list['create'])
            <flux:modal name="project-form" aria-labelledby="project-form-heading" variant="flyout" position="right"
                x-on:close="window.clearForm($el.querySelector('form')); window.resetTrackedForms($el)"
                x-on:cancel.prevent="window.requestTrackedModalClose($el)"
                class="form-drawer max-w-none">
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
