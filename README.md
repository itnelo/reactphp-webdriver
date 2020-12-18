
# ReactPHP WebDriver

This is a direct port of [RemoteWebDriver](https://github.com/php-webdriver/php-webdriver/blob/1.8.3/lib/Remote/RemoteWebDriver.php)
logic from the [php-webdriver/webdriver](https://github.com/php-webdriver/php-webdriver) package, which utilizes [ReactPHP](https://github.com/reactphp/reactphp)
event loop and promise API for browser interaction w/o execution flow blocking.

**Selenium WebDriver** is a software that is used to manipulate browsers from the code (primarily, for testing and web scraping).
You can find more here: [https://selenium.dev](https://selenium.dev).

This PHP client sends async HTTP requests to the [Grid](https://www.selenium.dev/documentation/en/grid). It is a central
endpoint for commands, a bridge between your code and browser instances. See
[SeleniumHQ/docker-selenium](https://github.com/SeleniumHQ/docker-selenium) to get your own remote browser (or a cluster).

Enjoy!

## Requirements

- **PHP 7.4** or higher.
- ReactPHP v1 (http **^1**, stream **^1**).
- Symfony conflicts: 5.1 (or newer) environments are preferred; the package uses (and will use) some components from
there, and their code / version constraints may need a review, to include a wider range of supported environments
(otherwise, you need to adjust your platform).

## Installation

With [composer](https://getcomposer.org/download):

```
$ composer require itnelo/reactphp-webdriver:^0.2
```

## How to use

Call a factory method to get your instance (recommended):

```php
use React\EventLoop\Factory as LoopFactory;
use Itnelo\React\WebDriver\WebDriverFactory;

$loop = LoopFactory::create();

$webDriver = WebDriverFactory::create(
    $loop,
    [
        'browser' => [
            'tcp' => [
                'bindto' => '192.168.56.10:0',
            ],
            'tls' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ],
        'hub' => [
            'host' => 'selenium-hub',
            'port' => 4444,
        ],
        'command' => [
            'timeout' => 30,
        ],
    ]
);
```

Manual configuration (if you want to configure each component as a separate service, e.g. compiling a DI container
and want to reuse existing service definitions):

```php
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Connector as SocketConnector;
use React\Http\Browser;
use Itnelo\React\WebDriver\Client\W3CClient;
use Itnelo\React\WebDriver\Timeout\Interceptor as TimeoutInterceptor;
use Itnelo\React\WebDriver\SeleniumHubDriver;

$loop = LoopFactory::create();

$socketConnector = new SocketConnector(
    $loop,
    [
        'tcp' => [
            'bindto' => '192.168.56.10:0',
        ],
        'tls' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ],
);
$browser = new Browser($loop, $socketConnector);
$browser = $browser->withRejectErrorResponse(false);

$hubClient = new W3CClient(
    $browser,
    [
        'server' => [
            'host' => 'selenium-hub',
            'port' => 4444,
        ],
    ]
);

$timeoutInterceptor = new TimeoutInterceptor($loop, 30);

$webDriver = new SeleniumHubDriver(
    $loop,
    $hubClient,
    $timeoutInterceptor
);
```

See a self-documented [WebDriverInterface.php](src/WebDriverInterface.php) (and [ClientInterface.php](src/ClientInterface.php))
for the API details. Not all methods and arguments are ported (only the most necessary), so feel free to open
an issue / make a pull request if you want more.

## See also

- [php-webdriver/webdriver](https://github.com/php-webdriver/php-webdriver) — the original, "blocking" implementation;
to get information how to use some advanced methods. For example, [WebDriverKeys](https://github.com/php-webdriver/php-webdriver/blob/main/lib/WebDriverKeys.php#L10)
helper describes Unicode strings for sending special inputs to page elements (e.g. `Ctrl`, `Alt` and other keys).

## Changelog

All notable changes to this project will be documented in [CHANGELOG.md](CHANGELOG.md).