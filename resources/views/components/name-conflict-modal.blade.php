@props([
    'name',
    'title',
    'message',
    'createAction',
    'restoreAction',
    'createFields',
    'createLabel',
    'restoreLabel',
    'createTest',
    'restoreTest',
])

<flux:modal :name="$name" class="max-w-md">
    <div class="flex flex-col gap-6">
        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            <flux:text class="mt-2">{{ $message }}</flux:text>
        </div>

        <div class="flex flex-col gap-3">
            <form method="POST" action="{{ $createAction }}">
                @csrf
                @foreach ($createFields as $field => $value)
                    <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                @endforeach
                <input type="hidden" name="reuse_deleted_name" value="1">
                <flux:button type="submit" variant="primary" class="w-full" :data-test="$createTest">
                    {{ $createLabel }}
                </flux:button>
            </form>
            <form method="POST" action="{{ $restoreAction }}">
                @csrf
                <input type="hidden" name="_method" value="PATCH">
                <input type="hidden" name="resolve_name_conflict" value="1">
                <flux:button type="submit" variant="ghost" class="w-full" :data-test="$restoreTest">
                    {{ $restoreLabel }}
                </flux:button>
            </form>
        </div>
    </div>
</flux:modal>
