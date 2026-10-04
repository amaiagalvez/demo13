<?php

namespace Tests\Unit\Translations;

use Tests\TestCase;

/**
 * Customers, projects and epics share one generic message per lifecycle action, so a single
 * translated string covers every resource and each locale maintains one wording instead of three.
 *
 * The wording itself is deliberately not asserted here: TranslationFilesTest already guarantees
 * key parity, placeholder preservation and usage, and the exact Basque, Spanish or French phrasing
 * may be improved at any time without touching the application code.
 */
class RecordMessageTranslationTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const SHARED_MESSAGES = [
        'Record created successfully.',
        'Record updated successfully.',
        'Record moved to trash.',
        'Record restored successfully.',
        'Record restored successfully. No new record was created with the repeated name.',
        'Record permanently deleted.',
        'Cannot be deleted while it has related records.',
        'Cannot be permanently deleted while it has related records.',
        'Cannot be restored because another record outside the trash uses this name.',
        'Record Name already in trash',
        'A deleted record already uses the name :name.',
        'Restore the deleted record instead',
        'Delete record?',
        'Permanently delete record?',
        'Restore record?',
        'Search record',
        'Loading records...',
    ];

    public function test_every_shared_message_is_translated_in_every_supported_locale(): void
    {
        foreach ($this->translations() as $locale => $translations) {
            foreach (self::SHARED_MESSAGES as $key) {
                app()->setLocale($locale);

                $this->assertSame(
                    $translations[$key] ?? $key,
                    __($key),
                    "lang/{$locale}.json is missing \"{$key}\".",
                );

                if ($locale !== 'en') {
                    $this->assertNotSame($key, __($key), "lang/{$locale}.json \"{$key}\" is not translated.");
                }

                app()->setLocale('en');
            }
        }
    }

    public function test_shared_messages_are_not_copied_english_outside_english(): void
    {
        $english = array_intersect_key($this->translations()['en'], array_flip(self::SHARED_MESSAGES));

        foreach ($this->translations() as $locale => $translations) {
            if ($locale === 'en') {
                continue;
            }

            $shared = array_intersect_key($translations, $english);
            $copied = array_intersect($english, $shared);

            $this->assertSame(
                [],
                array_keys($copied),
                "lang/{$locale}.json repeats the English wording of: ".implode(', ', array_keys($copied)),
            );
        }
    }

    public function test_shared_messages_keep_the_placeholders_of_their_key(): void
    {
        foreach ($this->translations() as $locale => $translations) {
            foreach (self::SHARED_MESSAGES as $key) {
                preg_match_all('/:[a-z_]+/', $key, $placeholders);

                foreach ($placeholders[0] as $placeholder) {
                    $this->assertStringContainsString(
                        $placeholder,
                        $translations[$key] ?? '',
                        "lang/{$locale}.json \"{$key}\" is missing {$placeholder}.",
                    );
                }
            }
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function translations(): array
    {
        $translations = [];

        foreach (glob(dirname(__DIR__, 3).'/lang/*.json') ?: [] as $file) {
            $locale = pathinfo($file, PATHINFO_FILENAME);

            /** @var array<string, string> $messages */
            $messages = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

            $translations[$locale] = $messages;
        }

        $this->assertNotSame([], $translations, 'No locale files were found in lang/.');

        return $translations;
    }
}
