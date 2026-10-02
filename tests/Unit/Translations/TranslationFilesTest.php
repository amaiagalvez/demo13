<?php

namespace Tests\Unit\Translations;

use PHPUnit\Framework\TestCase;

class TranslationFilesTest extends TestCase
{
    private const LOCALES = ['en', 'es', 'eu', 'fr'];

    public function test_all_locales_define_the_same_keys(): void
    {
        $english = array_keys($this->translations('en'));
        sort($english);

        foreach (self::LOCALES as $locale) {
            $keys = array_keys($this->translations($locale));
            sort($keys);

            $this->assertSame($english, $keys, "lang/{$locale}.json keys differ from lang/en.json.");
        }
    }

    public function test_translations_keep_the_placeholders_of_their_key(): void
    {
        foreach (self::LOCALES as $locale) {
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
