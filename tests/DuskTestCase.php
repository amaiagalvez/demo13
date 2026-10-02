<?php

namespace Tests;

use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use Facebook\WebDriver\Chrome\ChromeOptions;
use PHPUnit\Framework\Attributes\BeforeClass;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;

abstract class DuskTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::assertSame('laravel_test', config('database.connections.mysql.database'));
    }

    protected function baseUrl(): string
    {
        return rtrim(env('DUSK_BASE_URL', config('app.url')), '/');
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', 'laravel_test');
    }

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            if (is_executable('/usr/bin/chromedriver')) {
                static::useChromedriver('/usr/bin/chromedriver');
            }

            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)
            ->setBinary($_ENV['DUSK_CHROME_BINARY'] ?? env('DUSK_CHROME_BINARY', '/usr/bin/chromium'))
            ->addArguments(collect([
                $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
                '--disable-search-engine-choice-screen',
                '--disable-smooth-scrolling',
                '--disable-dev-shm-usage',
                '--no-sandbox',
            ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
                return $items->merge([
                    '--disable-gpu',
                    '--headless=new',
                ]);
            })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options
            )
        );
    }
}
