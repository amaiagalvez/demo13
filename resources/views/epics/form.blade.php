<div class="mx-auto flex w-full max-w-xl flex-col gap-6">
    <div>
        <flux:heading size="xl" x-text="form.title"></flux:heading>
        <flux:subheading x-text="form.subtitle"></flux:subheading>
    </div>

    <form method="POST" x-bind:action="form.action" x-data="{ isSubmitting: false }"
        x-on:submit="if (isSubmitting) $event.preventDefault(); isSubmitting = true"
        x-on:reset="isSubmitting = false" class="flex flex-col gap-6">
        @csrf
        <input type="hidden" name="_method" x-bind:value="form.method">
        <input type="hidden" name="_epic_form" x-bind:value="form.context">
        <input type="hidden" name="_epic_id" x-bind:value="form.id">
        <flux:field>
            <flux:label>{{ __('Name') }} <span class="text-red-600" aria-hidden="true">*</span>
            </flux:label>
            <flux:input name="name" x-model="form.name" minlength="4" maxlength="255" required autofocus
                data-test="epic-name" />
            <flux:error name="name" />
        </flux:field>
        <flux:field>
            <flux:label for="epic-project-id">{{ __('Project') }}
                <span class="text-red-600" aria-hidden="true">*</span>
            </flux:label>
            <flux:select id="epic-project-id" name="project_id" x-model="form.project_id" required
                data-test="epic-project">
                <option value="">{{ __('Select a project') }}</option>
                @foreach ($availableProjects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }} ({{ $project->customer->name }})</option>
                @endforeach
            </flux:select>
            <flux:error name="project_id" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Start date') }}</flux:label>
            <flux:input type="date" name="start_date" x-model="form.start_date"
                x-bind:required="form.end_date !== ''" data-test="epic-start-date" />
            <flux:error name="start_date" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('End date') }}</flux:label>
            <flux:input type="date" name="end_date" x-model="form.end_date"
                x-bind:min="form.start_date" data-test="epic-end-date" />
            <flux:description>{{ __('The end date requires a start date and must be after it.') }}</flux:description>
            <flux:error name="end_date" />
        </flux:field>
        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button type="button" variant="ghost" data-test="epic-cancel">
                    {{ __('Cancel') }}
                </flux:button>
            </flux:modal.close>
            <flux:button variant="primary" type="submit" x-bind:disabled="isSubmitting"
                x-bind:aria-busy="isSubmitting" data-test="epic-submit">
                <span x-text="form.submitLabel"></span>
            </flux:button>
        </div>
    </form>

    <template x-if="form.method === 'PUT'">
        <section class="flex flex-col gap-4 border-t border-zinc-200 pt-6 dark:border-zinc-700"
            aria-labelledby="epic-comments-heading" data-test="epic-comments">
            <flux:heading size="lg" id="epic-comments-heading">{{ __('Comments') }}</flux:heading>

            <form method="POST" x-bind:action="form.commentAction" x-data="{ isSubmitting: false }"
                x-on:submit="if (isSubmitting) $event.preventDefault(); isSubmitting = true"
                class="flex flex-col gap-3">
                @csrf
                <input type="hidden" name="_comment_epic_id" x-bind:value="form.id">
                <flux:field>
                    <flux:label>{{ __('New comment') }}</flux:label>
                    <flux:textarea name="body" x-model="form.commentBody" rows="3" maxlength="5000"
                        required data-test="epic-comment-body" />
                    @error('body', 'comment')
                        <flux:text class="text-sm text-red-600" role="alert">{{ $message }}</flux:text>
                    @enderror
                </flux:field>
                <flux:text class="text-xs text-zinc-500">
                    {{ __('Unsaved epic changes are discarded when adding a comment.') }}
                </flux:text>
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" size="sm" icon="chat-bubble-left"
                        x-bind:disabled="isSubmitting" data-test="epic-comment-submit">
                        {{ __('Add comment') }}
                    </flux:button>
                </div>
            </form>

            <p x-show="form.comments.length === 0" class="text-sm text-zinc-500"
                data-test="epic-comments-empty">{{ __('No comments yet.') }}</p>

            <ul class="flex flex-col gap-3" x-show="form.comments.length > 0">
                <template x-for="comment in form.comments" :key="comment.id">
                    <li class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"
                        data-test="epic-comment">
                        <div class="flex items-center justify-between gap-2 text-xs text-zinc-500">
                            <span class="font-medium text-zinc-700 dark:text-zinc-300" x-text="comment.author"></span>
                            <time x-text="comment.writtenAt"></time>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm" x-text="comment.body"></p>
                    </li>
                </template>
            </ul>
        </section>
    </template>
</div>
