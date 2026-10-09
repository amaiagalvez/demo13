<?php

namespace Tests\Unit\Translations;

use Tests\TestCase;

/**
 * The package basics13 provides generic outcome messages in its messages.php files.
 * This test verifies that those package translations exist and have the expected structure.
 */
class RecordMessageTranslationTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const SHARED_KEYS = [
        'created',
        'updated',
        'moved_to_trash',
        'permanently_deleted',
        'restored',
        'restored_no_new_record',
        'archived',
        'activated',
        'not_in_trash',
        'cannot_restore_name_taken',
        'deleted_name_conflict',
        'created_at',
        'updated_at',
        'deleted_at',
        'archived_label',
        'trash_label',
        'no_archived_records',
        'trash_is_empty',
        'you_can_restore_from_trash',
        'you_can_activate_from_archived',
        'record_returns_to_active',
        'record_returns_to_archived',
    ];

    public function test_every_shared_message_is_translated_in_every_supported_locale(): void
    {
        foreach ($this->packageTranslations() as $locale => $translations) {
            foreach (self::SHARED_KEYS as $key) {
                app()->setLocale($locale);

                $this->assertArrayHasKey(
                    $key,
                    $translations,
                    "basics13 messages.php for {$locale} is missing \"{$key}\".",
                );

                if ($locale !== 'en') {
                    $this->assertNotSame($key, $translations[$key], "basics13 messages.php for {$locale} \"{$key}\" is not translated.");
                }

                app()->setLocale('en');
            }
        }
    }

    public function test_shared_messages_are_not_copied_english_outside_english(): void
    {
        $english = array_intersect_key($this->packageTranslations()['en'], array_flip(self::SHARED_KEYS));

        foreach ($this->packageTranslations() as $locale => $translations) {
            if ($locale === 'en') {
                continue;
            }

            $shared = array_intersect_key($translations, $english);
            $copied = array_intersect($english, $shared);

            $this->assertSame(
                [],
                array_keys($copied),
                "basics13 messages.php for {$locale} repeats the English wording of: ".implode(', ', array_keys($copied)),
            );
        }
    }

    public function test_shared_messages_keep_the_placeholders_of_their_key(): void
    {
        foreach ($this->packageTranslations() as $locale => $translations) {
            foreach (self::SHARED_KEYS as $key) {
                preg_match_all('/:[a-z_]+/', $key, $placeholders);

                foreach ($placeholders[0] as $placeholder) {
                    $this->assertStringContainsString(
                        $placeholder,
                        $translations[$key] ?? '',
                        "basics13 messages.php for {$locale} \"{$key}\" is missing {$placeholder}.",
                    );
                }
            }
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function packageTranslations(): array
    {
        $translations = [];

        foreach (['en', 'es', 'fr', 'eu'] as $locale) {
            $file = base_path("../../packages/basics13/resources/lang/{$locale}/messages.php");

            if (! file_exists($file)) {
                continue;
            }

            /** @var array<string, string> $messages */
            $messages = require $file;

            $translations[$locale] = $messages;
        }

        $this->assertNotSame([], $translations, 'No locale files were found in basics13/resources/lang/.');

        return $translations;
    }
}
