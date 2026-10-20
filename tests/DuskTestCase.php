<?php

namespace Tests;

use Illuminate\Support\Env;
use Symfony\Component\Process\Process;
use Facebook\WebDriver\Edge\EdgeOptions;
use Laravel\Dusk\TestCase as BaseTestCase;
use Facebook\WebDriver\Chrome\ChromeOptions;
use PHPUnit\Framework\Attributes\BeforeClass;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;

/**
 * Extended DuskTestCase supporting multiple browsers, screen sizes, and configurations.
 *
 * Configuration via environment variables:
 * - DUSK_BROWSER: chrome (default), firefox, edge
 * - DUSK_SCREEN_SIZE: desktop (1920x1080), desktop-small (1366x768), tablet (768x1024), tablet-landscape (1024x768), mobile (375x667), mobile-large (390x844), mobile-xl (412x915), custom WIDTHxHEIGHT
 * - DUSK_HEADLESS: true (default), false
 * - DUSK_DRIVER_URL: http://localhost:9515 (Chrome), http://localhost:4444 (Selenium Grid)
 * - DUSK_BROWSER_BINARY: path to browser binary
 * - DUSK_DRIVER_PATH: path to driver binary
 * - DUSK_BASE_URL: base URL for tests
 * - DUSK_WINDOW_POSITION: x,y (for multi-monitor setups)
 */
abstract class DuskTestCase extends BaseTestCase
{
    /** @var string Current browser being tested */
    protected static string $browser = 'chrome';

    /** @var array{width: int, height: int} Current screen size */
    protected static array $screenSize = ['width' => 1920, 'height' => 1080];

    /** @var bool Whether to run headless */
    protected static bool $headless = true;

    /** @var string Browser binary path */
    protected static string $browserBinary = '';

    /** @var string Driver binary path */
    protected static string $driverPath = '';

    /** @var string Driver URL */
    protected static string $driverUrl = 'http://localhost:9515';

    /** @var array<int, string> Additional browser arguments */
    protected static array $additionalArgs = [];

    /**
     * Get the browser name from environment or default to chrome.
     */
    protected static function getBrowser(): string
    {
        return strtolower($_ENV['DUSK_BROWSER'] ?? Env::get('DUSK_BROWSER', 'chrome'));
    }

    /**
     * Get screen size configuration.
     */
    protected static function getScreenSize(): array
    {
        $size = $_ENV['DUSK_SCREEN_SIZE'] ?? Env::get('DUSK_SCREEN_SIZE', 'desktop');

        $presets = [
            'desktop' => ['width' => 1920, 'height' => 1080],
            'desktop-small' => ['width' => 1366, 'height' => 768],
            'desktop-xl' => ['width' => 2560, 'height' => 1440],
            'tablet' => ['width' => 768, 'height' => 1024],
            'tablet-landscape' => ['width' => 1024, 'height' => 768],
            'mobile' => ['width' => 375, 'height' => 667],
            'mobile-large' => ['width' => 390, 'height' => 844],
            'mobile-xl' => ['width' => 412, 'height' => 915],
            'mobile-fold' => ['width' => 280, 'height' => 653],
        ];

        if (isset($presets[$size])) {
            return $presets[$size];
        }

        // Custom format: WIDTHxHEIGHT
        if (preg_match('/^(\d+)x(\d+)$/', $size, $matches)) {
            return ['width' => (int) $matches[1], 'height' => (int) $matches[2]];
        }

        return $presets['desktop'];
    }

    /**
     * Get headless mode setting.
     */
    protected static function getHeadless(): bool
    {
        $headless = $_ENV['DUSK_HEADLESS'] ?? Env::get('DUSK_HEADLESS', 'true');

        return filter_var($headless, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get browser binary path.
     */
    protected static function getBrowserBinary(string $browser): string
    {
        $envVar = 'DUSK_'.strtoupper($browser).'_BINARY';

        return $_ENV[$envVar] ?? Env::get($envVar, match ($browser) {
            'chrome' => '/usr/bin/chromium',
            'firefox' => '/usr/bin/firefox-esr',
            'edge' => '/usr/bin/microsoft-edge',
            default => '',
        });
    }

    /**
     * Get driver path.
     */
    protected static function getDriverPath(string $browser): string
    {
        $envVar = 'DUSK_'.strtoupper($browser).'_DRIVER_PATH';

        return $_ENV[$envVar] ?? Env::get($envVar, match ($browser) {
            'chrome' => '/usr/bin/chromedriver',
            'firefox' => '/usr/local/bin/geckodriver',
            'edge' => '/usr/local/bin/msedgedriver',
            default => '',
        });
    }

    /**
     * Get driver URL.
     */
    protected static function getDriverUrl(string $browser): string
    {
        $url = $_ENV['DUSK_DRIVER_URL'] ?? Env::get('DUSK_DRIVER_URL');

        if ($url) {
            return $url;
        }

        // Default ports for different drivers
        return match ($browser) {
            'chrome' => 'http://localhost:9515',
            'firefox' => 'http://localhost:4444',
            'edge' => 'http://localhost:9515', // Edge uses ChromeDriver protocol
            default => 'http://localhost:9515',
        };
    }

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        self::$browser = self::getBrowser();
        self::$screenSize = self::getScreenSize();
        self::$headless = self::getHeadless();
        self::$browserBinary = self::getBrowserBinary(self::$browser);
        self::$driverPath = self::getDriverPath(self::$browser);
        self::$driverUrl = self::getDriverUrl(self::$browser);

        if (! static::runningInSail() && self::$driverPath !== '') {
            static::useChromedriver(self::$driverPath);
        }

        // Start the appropriate driver
        match (self::$browser) {
            'chrome', 'edge' => static::startChromeDriver(['--port=9515']),
            'firefox' => static::startGeckoDriver(['--port=4444']),
            default => static::startChromeDriver(['--port=9515']),
        };
    }

    /**
     * Start GeckoDriver for Firefox.
     */
    protected static function startGeckoDriver(array $arguments = []): void
    {
        $driverPath = self::$driverPath ?: (self::$driverPath = '/usr/local/bin/geckodriver');

        $process = new Process(
            array_merge([$driverPath], $arguments),
            null,
            null,
            null,
            null
        );

        $process->start();

        // Give it time to start
        sleep(2);

        register_shutdown_function(function () use ($process) {
            if ($process->isRunning()) {
                $process->stop(3, SIGTERM);
            }
        });
    }

    /**
     * Create the RemoteWebDriver instance based on configuration.
     */
    protected function driver(): RemoteWebDriver
    {
        if (! is_string(self::$browserBinary) || self::$browserBinary === '') {
            static::fail('Browser binary path must be configured for '.self::$browser);
        }

        if (! is_string(self::$driverUrl) || self::$driverUrl === '') {
            static::fail('Driver URL must be configured for '.self::$browser);
        }

        $capabilities = match (self::$browser) {
            'chrome' => $this->createChromeCapabilities(),
            'firefox' => $this->createFirefoxCapabilities(),
            'edge' => $this->createEdgeCapabilities(),
            default => throw new \RuntimeException('Unsupported browser: '.self::$browser),
        };

        return RemoteWebDriver::create(self::$driverUrl, $capabilities);
    }

    /**
     * Create Chrome capabilities with configured options.
     */
    protected function createChromeCapabilities(): DesiredCapabilities
    {
        $options = new ChromeOptions;
        $options->setBinary(self::$browserBinary);

        $args = collect([
            '--window-size='.self::$screenSize['width'].','.self::$screenSize['height'],
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--disable-dev-shm-usage',
            '--no-sandbox',
            '--disable-extensions',
            '--disable-infobars',
        ]);

        // Add mobile emulation for mobile screen sizes
        if ($this->isMobileSize()) {
            $options->setExperimentalOption('mobileEmulation', [
                'deviceMetrics' => [
                    'width' => self::$screenSize['width'],
                    'height' => self::$screenSize['height'],
                    'pixelRatio' => 2.0,
                    'touch' => true,
                ],
                'userAgent' => $this->getMobileUserAgent(),
            ]);
        } else {
            $args->merge([
                $this->shouldStartMaximized() ? '--start-maximized' : '',
            ])->filter();
        }

        if (self::$headless) {
            $args->merge([
                '--headless=new',
                '--disable-gpu',
            ]);
        }

        $options->addArguments($args->merge(self::$additionalArgs)->all());

        return DesiredCapabilities::chrome()->setCapability(
            ChromeOptions::CAPABILITY,
            $options
        );
    }

    /**
     * Create Firefox capabilities with configured options.
     */
    protected function createFirefoxCapabilities(): DesiredCapabilities
    {
        $options = new FirefoxOptions;

        // Set Firefox binary using the correct API
        if (method_exists($options, 'setBinary')) {
            $options->setBinary(self::$browserBinary);
        } else {
            // Fallback: set via capability
            $options->setCapability('moz:firefoxOptions', [
                'binary' => self::$browserBinary,
                'args' => [],
            ]);
        }

        $args = [
            '--width='.self::$screenSize['width'],
            '--height='.self::$screenSize['height'],
        ];

        if (self::$headless) {
            $args[] = '--headless';
        }

        $options->addArguments($args);

        return DesiredCapabilities::firefox()->setCapability(
            FirefoxOptions::CAPABILITY,
            $options
        );
    }

    /**
     * Create Edge capabilities with configured options.
     */
    protected function createEdgeCapabilities(): DesiredCapabilities
    {
        $options = new EdgeOptions;
        $options->setBinary(self::$browserBinary);

        $args = collect([
            '--window-size='.self::$screenSize['width'].','.self::$screenSize['height'],
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--disable-dev-shm-usage',
            '--no-sandbox',
            '--disable-extensions',
        ]);

        if (self::$headless) {
            $args->merge([
                '--headless=new',
                '--disable-gpu',
            ]);
        }

        $options->addArguments($args->merge(self::$additionalArgs)->all());

        return DesiredCapabilities::edge()->setCapability(
            EdgeOptions::CAPABILITY,
            $options
        );
    }

    /**
     * Check if current screen size is a mobile size.
     */
    protected function isMobileSize(): bool
    {
        $mobileSizes = ['mobile', 'mobile-large', 'mobile-xl', 'mobile-fold'];
        $sizeName = $_ENV['DUSK_SCREEN_SIZE'] ?? Env::get('DUSK_SCREEN_SIZE', 'desktop');

        return in_array($sizeName, $mobileSizes) ||
               (self::$screenSize['width'] < 768);
    }

    /**
     * Get a mobile user agent string.
     */
    protected function getMobileUserAgent(): string
    {
        $sizeName = $_ENV['DUSK_SCREEN_SIZE'] ?? Env::get('DUSK_SCREEN_SIZE', 'mobile');

        return match ($sizeName) {
            'mobile' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
            'mobile-large' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
            'mobile-xl' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
            'mobile-fold' => 'Mozilla/5.0 (Linux; Android 13; Pixel 7 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Mobile Safari/537.36',
            default => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
        };
    }

    /**
     * Get the base URL for tests.
     */
    protected function baseUrl(): string
    {
        $baseUrl = Env::get('DUSK_BASE_URL', config('app.url'));

        if (! is_string($baseUrl)) {
            static::fail('DUSK_BASE_URL must resolve to a string.');
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * Get current browser name (for test assertions/reporting).
     */
    public static function getCurrentBrowser(): string
    {
        return self::$browser;
    }

    /**
     * Get current screen size (for test assertions/reporting).
     */
    public static function getCurrentScreenSize(): array
    {
        return self::$screenSize;
    }

    /**
     * Check if running in headless mode.
     */
    public static function isHeadless(): bool
    {
        return self::$headless;
    }

    /**
     * Add custom browser arguments.
     */
    protected function addBrowserArgument(string $argument): void
    {
        self::$additionalArgs[] = $argument;
    }

    /**
     * Set custom screen size for a specific test.
     */
    protected function setScreenSize(int $width, int $height): void
    {
        self::$screenSize = ['width' => $width, 'height' => $height];
    }
}
