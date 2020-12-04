<?php

/*
 * This file is part of the ReactPHP WebDriver <https://github.com/itnelo/reactphp-webdriver>.
 *
 * (c) 2020 Pavel Petrov <itnelo@gmail.com>.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license https://opensource.org/licenses/mit MIT
 */

declare(strict_types=1);

namespace Itnelo\React\WebDriver\Client;

use Itnelo\React\WebDriver\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RuntimeException;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as ConfigurationExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Throwable;
use function React\Promise\reject;

/**
 * W3C compliant WebDriver client for Selenium Grid server (hub) that performs asynchronously.
 *
 * This is a direct port of RemoteWebDriver logic from the "php-webdriver/webdriver" package, which utilizes
 * ReactPHP event loop and promise API for browser interaction w/o execution flow blocking.
 *
 * It isn't a complete bridge and gives only the most common and robust levers to make the job done. For more info see
 * {@link ClientInterface}.
 *
 * @see https://github.com/php-webdriver/php-webdriver/blob/1.8.3/lib/Remote/RemoteWebDriver.php
 */
class W3CClient implements ClientInterface
{
    /**
     * Sends commands to the Selenium Grid endpoint using W3C protocol over HTTP
     *
     * @var Browser
     *
     * @see https://www.w3.org/TR/webdriver
     */
    private Browser $httpClient;

    /**
     * Array of options for the hub client
     *
     * @var array
     */
    private array $_options;

    /**
     * W3CClient constructor.
     *
     * Usage example:
     *
     * ```
     * $loop = \React\EventLoop\Factory::create();
     *
     * $browser = new \React\Http\Browser($loop);
     * $browser = $browser->withRejectErrorResponse(false);
     *
     * $hubClient = new \Itnelo\React\WebDriver\Client\W3CClient(
     *     $browser,
     *     [
     *         'server' => [
     *             'host' => 'selenium-hub',
     *             'port' => 4444,
     *         ],
     *     ]
     * );
     * ```
     *
     * @param Browser $httpClient Sends commands to the Selenium Grid endpoint using W3C protocol over HTTP
     * @param array   $options    Array of options for the hub client
     *
     * @throws ConfigurationExceptionInterface Whenever an error has been occurred during client configuration
     */
    public function __construct(Browser $httpClient, array $options = [])
    {
        $optionsResolver = new OptionsResolver();

        $optionsResolver
            ->define('server')
            ->info('Options for establishing a socket connection to the remote server')
            ->default(
                function (OptionsResolver $serverOptionsResolver) {
                    $serverOptionsResolver
                        ->define('host')
                        ->allowedTypes('string')
                        ->default('127.0.0.1')
                    ;

                    $serverOptionsResolver
                        ->define('port')
                        ->allowedTypes('int')
                        ->default(4444)
                    ;
                }
            )
        ;

        $this->_options = $optionsResolver->resolve($options);

        $this->httpClient = $httpClient;
    }

    /**
     * {@inheritDoc}
     */
    public function getSessionIdentifiers(): PromiseInterface
    {
        // todo

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function createSession(): PromiseInterface
    {
        $sessionOpeningDeferred = new Deferred();

        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session',
            $this->_options['server']['host'],
            $this->_options['server']['port']
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        // todo: implement custom executable args / prefs support (omitted in the interface)
        $requestContents =
            '{"capabilities":{"firstMatch":[{"browserName":"chrome","goog:chromeOptions":{"prefs":{"intl.accept_languages":"RU-ru,ru,en-US,en"},"args":["--user-data-dir=\/opt\/google\/chrome\/profiles"]}}]},"desiredCapabilities":{"browserName":"chrome","platform":"ANY","goog:chromeOptions":{"prefs":{"intl.accept_languages":"RU-ru,ru,en-US,en"},"args":["--user-data-dir=\/opt\/google\/chrome\/profiles"]}}}';

        $responsePromise = $this->httpClient->post($requestUri, $requestHeaders, $requestContents);

        $responsePromise->then(
            function (ResponseInterface $response) use ($sessionOpeningDeferred) {
                try {
                    $responseBody = (string) $response->getBody();
                    preg_match('/sessionid[":\s]+([a-z\d]{32})/Ui', $responseBody, $matches);

                    if (!isset($matches[1])) {
                        // todo: locate an error message or set it as "undefined error"
                        throw new RuntimeException('Unable to locate a session identifier in the response.');
                    }

                    $sessionIdentifier = $matches[1];
                    $sessionOpeningDeferred->resolve($sessionIdentifier);
                } catch (Throwable $exception) {
                    $reason = new RuntimeException(
                        'Unable to open a selenium hub session (response deserialization).',
                        0,
                        $exception
                    );

                    $sessionOpeningDeferred->reject($reason);
                }
            },
            function (Throwable $rejectionReason) use ($sessionOpeningDeferred) {
                $reason = new RuntimeException('Unable to open a selenium hub session (request).', 0, $rejectionReason);

                $sessionOpeningDeferred->reject($reason);
            }
        );

        $sessionIdentifierPromise = $sessionOpeningDeferred->promise();

        return $sessionIdentifierPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function removeSession(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement removeSession() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function getTabIdentifiers(string $sessionIdentifier): PromiseInterface
    {
        $tabLookupDeferred = new Deferred();

        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session/%s/window/handles',
            $this->_options['server']['host'],
            $this->_options['server']['port'],
            $sessionIdentifier
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $responsePromise = $this->httpClient->get($requestUri, $requestHeaders);

        $responsePromise->then(
            function (ResponseInterface $response) use ($tabLookupDeferred) {
                try {
                    $responseBody     = (string) $response->getBody();
                    $bodyDeserialized = json_decode($responseBody, true);

                    if (!array_key_exists('value', $bodyDeserialized) || !is_array($bodyDeserialized['value'])) {
                        // todo: locate an error message or set it as "undefined error"
                        throw new RuntimeException('Unable to locate tab identifiers in the response.');
                    }

                    $tabIdentifiers = $bodyDeserialized['value'];
                    $tabLookupDeferred->resolve($tabIdentifiers);
                } catch (Throwable $exception) {
                    $reason = new RuntimeException(
                        'Unable to open a selenium hub session (response deserialization).',
                        0,
                        $exception
                    );

                    $tabLookupDeferred->reject($reason);
                }
            },
            function (Throwable $rejectionReason) use ($tabLookupDeferred) {
                $reason = new RuntimeException('Unable to open a selenium hub session (request).', 0, $rejectionReason);

                $tabLookupDeferred->reject($reason);
            }
        );

        $tabIdentifierListPromise = $tabLookupDeferred->promise();

        return $tabIdentifierListPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveTabIdentifier(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement getActiveTabIdentifier() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function setActiveTab(string $sessionIdentifier, string $tabIdentifier): PromiseInterface
    {
        // TODO: Implement setActiveTab() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function openUri(string $sessionIdentifier, string $uri): PromiseInterface
    {
        // TODO: Implement openUri() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentUri(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement getUri() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function getSource(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement getSource() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function getElementIdentifier(string $sessionIdentifier, string $xpathQuery): PromiseInterface
    {
        // TODO: Implement getElementIdentifier() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveElement(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement getActiveElement() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function getElementVisibility(string $sessionIdentifier, array $elementIdentifier): PromiseInterface
    {
        // TODO: Implement getElementVisibility() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function clickElement(string $sessionIdentifier, array $elementIdentifier): PromiseInterface
    {
        // TODO: Implement clickElement() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function keypressElement(
        string $sessionIdentifier,
        array $elementIdentifier,
        string $keySequence
    ): PromiseInterface {
        // TODO: Implement keypressElement() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function mouseMove(
        string $sessionIdentifier,
        int $offsetX,
        int $offsetY,
        int $moveDuration = 100,
        array $startingPoint = null
    ): PromiseInterface {
        // TODO: Implement mouseMove() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function mouseLeftClick(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement mouseLeftClick() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function getScreenshot(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement getScreenshot() method.

        return reject(new RuntimeException('Not implemented.'));
    }
}
