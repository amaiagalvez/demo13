<?php

namespace Tests\Unit\Translations;

use SplFileInfo;
use FilesystemIterator;
use RecursiveIteratorIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;

class TranslationFilesTest extends TestCase
{
    /**
     * Laravel resolves these keys through validation rules without application string literals.
     *
     * @var list<string>
     */
    private const FRAMEWORK_KEYS = [
        'validation.required',
        'validation.string',
        'validation.unique',
        'validation.attributes',
    ];

    /**
     * App-specific keys that are not in the basics13 package but are used by the application.
     *
     * @var list<string>
     */
    private const APP_SPECIFIC_KEYS = [
        'Deactivate',
        'Deactivate record?',
        'Reactivate',
        'Reactivate record?',
        'Record deactivated successfully.',
        'Record reactivated successfully.',
    ];

    /**
     * Customers, projects and epics share the outcome wording of their lifecycle actions, so a
     * resource name in one of these keys would mean translating the very same sentence again.
     */
    private const OUTCOME_PATTERN = '/\b(created|updated|restored|deleted|moved to trash)\b/';

    /**
     * @var list<string>
     */
    private const RESOURCES = ['Customer', 'Project', 'Epic'];

    public function test_all_locales_define_the_same_keys(): void
    {
        $locales = $this->locales();
        $english = array_keys($this->translations('en'));
        sort($english);

        foreach ($locales as $locale) {
            $keys = array_keys($this->translations($locale));
            sort($keys);

            $this->assertSame($english, $keys, "lang/{$locale}.json keys differ from lang/en.json.");
        }
    }

    public function test_translations_keep_the_placeholders_of_their_key(): void
    {
        foreach ($this->locales() as $locale) {
            foreach ($this->translations($locale) as $key => $value) {
                preg_match_all('/:[a-z_]+/', $key, $keyPlaceholders);

                if ($keyPlaceholders[0] === []) {
                    continue;
                }

                $this->assertIsString($value, "lang/{$locale}.json \"{$key}\" must be a string.");

                foreach ($keyPlaceholders[0] as $placeholder) {
                    $this->assertStringContainsString(
                        $placeholder,
                        $value,
                        "lang/{$locale}.json \"{$key}\" is missing {$placeholder}.",
                    );
                }
            }
        }
    }

    public function test_every_english_json_key_is_used_by_application_sources(): void
    {
        $sources = [];

        $sources = $this->translationSources();
        $unusedKeys = [];

        foreach (array_keys($this->translations('en')) as $key) {
            if (
                ! in_array($key, self::FRAMEWORK_KEYS, true)
                && ! in_array($key, self::APP_SPECIFIC_KEYS, true)
                && ! $this->translationIsReferenced($key, $sources)
            ) {
                $unusedKeys[] = $key;
            }
        }

        $this->assertSame([], $unusedKeys, 'Unused lang/en.json keys: '.implode(', ', $unusedKeys));
    }

    public function test_outcome_messages_do_not_name_the_resource_they_describe(): void
    {
        $resourceSpecificKeys = [];

        foreach (array_keys($this->translations('en')) as $key) {
            if (preg_match(self::OUTCOME_PATTERN, $key) !== 1) {
                continue;
            }

            foreach (self::RESOURCES as $resource) {
                if (str_starts_with($key, $resource.' ')) {
                    $resourceSpecificKeys[] = $key;
                }
            }
        }

        $this->assertSame(
            [],
            $resourceSpecificKeys,
            'Outcome messages must stay generic so every resource can reuse them: '.implode(', ', $resourceSpecificKeys),
        );
    }

    /**
     * @return list<string>
     */
    private function translationSources(): array
    {
        $sources = [];
        $root = dirname(__DIR__, 3);
        $directories = [
            'app',
            'resources',
            'vendor/laravel/framework/src',
            'vendor/laravel/fortify/src',
            'vendor/laravel/passkeys/src',
            'vendor/livewire/livewire/src',
            'vendor/livewire/flux/src',
            'vendor/opcodesio/log-viewer/src',
        ];

        foreach ($directories as $directory) {
            $path = "{$root}/{$directory}";

            if (! is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                if (! in_array($file->getExtension(), ['php', 'js', 'css'], true)) {
                    continue;
                }

                $source = file_get_contents($file->getPathname());

                if (is_string($source)) {
                    $sources[] = $source;
                }
            }
        }

        return $sources;
    }

    /**
     * @param  list<string>  $sources
     */
    private function translationIsReferenced(string $key, array $sources): bool
    {
        foreach ($sources as $source) {
            if (str_contains($source, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        $files = glob(dirname(__DIR__, 3).'/lang/*.json');

        if ($files === false || $files === []) {
            self::fail('No locale files were found in lang/.');
        }

        $locales = array_map(
            static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME),
            $files,
        );
        sort($locales);

        return $locales;
    }

    /**
     * @return array<string, mixed>
     */
    private function translations(string $locale): array
    {
        $json = file_get_contents(dirname(__DIR__, 3)."/lang/{$locale}.json");

        if ($json === false) {
            self::fail("lang/{$locale}.json could not be read.");
        }

        $translations = json_decode(
            $json,
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (! is_array($translations)) {
            self::fail("lang/{$locale}.json must decode to an array.");
        }

        /** @var array<string, string> $translations */
        return $translations;
    }
}
