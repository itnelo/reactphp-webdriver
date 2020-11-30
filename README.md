
# ReactPHP WebDriver

This is a direct port of [RemoteWebDriver](https://github.com/php-webdriver/php-webdriver/blob/1.8.3/lib/Remote/RemoteWebDriver.php)
logic from the [php-webdriver/webdriver](https://github.com/php-webdriver/php-webdriver) package, which utilizes [ReactPHP](https://github.com/reactphp/reactphp)
event loop and promise API for browser interaction w/o execution flow blocking.

Usage example:

```php
use React\EventLoop\Factory as LoopFactory;
use React\Http\Browser;
use Itnelo\React\WebDriver\Client\W3CClient;

$loop = LoopFactory::create();
$browser = new Browser($loop);
    
$webdriver = new W3CClient(
    $browser,
    [
        'server' => [
            'host' => 'selenium-hub',
            'port' => 4444,
        ],
        'request' => [
            'timeout' => 30,
        ],
    ]
);
```

See a self-documented [ClientInterface.php](src/ClientInterface.php) for the API details. Not all methods are ported
(only the most necessary), so feel free to open an issue / make a pull request if you want more.

## See also

- [php-webdriver/webdriver](https://github.com/php-webdriver/php-webdriver) — the original, "blocking" implementation; 
to get information how to use some advanced methods. For example, [WebDriverKeys](https://github.com/php-webdriver/php-webdriver/blob/main/lib/WebDriverKeys.php#L10)
helper describes Unicode strings for sending special inputs to page elements (e.g. `Ctrl`, `Alt` and other keys).

## Changelog

All notable changes to this project will be documented in [CHANGELOG.md](CHANGELOG.md).