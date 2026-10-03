<?php

namespace Tests;

use Illuminate\Support\Env;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Application;
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
        $baseUrl = Env::get('DUSK_BASE_URL', config('app.url'));

        if (! is_string($baseUrl)) {
            static::fail('DUSK_BASE_URL must resolve to a string.');
        }

        return rtrim($baseUrl, '/');
    }

    protected function getEnvironmentSetUp(Application $app): void
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
            $chromeDriverPath = getenv('DUSK_CHROMEDRIVER_PATH');

            if (is_string($chromeDriverPath) && $chromeDriverPath !== '') {
                static::useChromedriver($chromeDriverPath);
            }

            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $chromeBinary = $_ENV['DUSK_CHROME_BINARY'] ?? Env::get('DUSK_CHROME_BINARY', '/usr/bin/chromium');
        $driverUrl = $_ENV['DUSK_DRIVER_URL'] ?? Env::get('DUSK_DRIVER_URL') ?? 'http://localhost:9515';

        if (! is_string($chromeBinary)) {
            static::fail('DUSK_CHROME_BINARY must resolve to a string.');
        }

        if (! is_string($driverUrl)) {
            static::fail('DUSK_DRIVER_URL must resolve to a string.');
        }

        $options = (new ChromeOptions)
            ->setBinary($chromeBinary)
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
            $driverUrl,
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options
            )
        );
    }
}
