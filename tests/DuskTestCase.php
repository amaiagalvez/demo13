<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
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
