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
    private const FRAMEWORK_KEYS = ['validation.required', 'validation.string'];

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
        $root = dirname(__DIR__, 3);
        $sources = [];

        $sources = $this->applicationSources();
        $unusedKeys = [];

        foreach (array_keys($this->translations('en')) as $key) {
            if (
                ! in_array($key, self::FRAMEWORK_KEYS, true)
                && ! $this->translationIsReferenced($key, $sources)
            ) {
                $unusedKeys[] = $key;
            }
        }

        $this->assertSame([], $unusedKeys, 'Unused lang/en.json keys: '.implode(', ', $unusedKeys));
    }

    /**
     * @return list<string>
     */
    private function applicationSources(): array
    {
        $sources = [];
        $root = dirname(__DIR__, 3);

        foreach (['app', 'resources'] as $directory) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator("{$root}/{$directory}", FilesystemIterator::SKIP_DOTS),
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
     * @return array<string, string>
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
