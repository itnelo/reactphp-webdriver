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
use React\Http\Browser;
use React\Promise\PromiseInterface;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as OptionsResolverExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
     * Array of options for the client
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
     * $browser = new \React\Http\Browser($loop);
     *
     * $webdriver = new \Itnelo\React\WebDriver\Client\W3CClient(
     *     $browser,
     *     [
     *         'server' => [
     *             'host' => 'selenium-hub',
     *             'port' => 4444,
     *         ],
     *         'request' => [
     *             'timeout' => 30,
     *         ],
     *     ]
     * );
     * ```
     *
     * The "request.timeout" option here doesn't correlate with ReactPHP Browser's timeouts and will just cancel a
     * pending promise after the specified time (in seconds); an HTTP request itself, which is handled by the ReactPHP
     * Browser, may (or may not) be completed. Furthermore, the client can reject promise with a runtime exception if
     * underlying browser has decided to stop waiting for the response by its own timeout settings.
     *
     * @param Browser $httpClient Sends commands to the Selenium Grid endpoint using W3C protocol over HTTP
     * @param array   $options    Array of options for the client
     *
     * @throws OptionsResolverExceptionInterface Whenever an error has been occurred during client configuration
     */
    public function __construct(Browser $httpClient, array $options = [])
    {
        $this->httpClient = $httpClient;

        $optionsResolver = new OptionsResolver();

        $optionsResolver
            ->define('server')
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

        $optionsResolver
            ->define('request')
            ->default(
                function (OptionsResolver $requestOptionsResolver) {
                    $requestOptionsResolver
                        ->define('timeout')
                        ->allowedTypes('int')
                        ->default(30)
                    ;
                }
            )
        ;

        $this->_options = $optionsResolver->resolve($options);
    }

    /**
     * {@inheritDoc}
     */
    public function getSessionIdentifiers(): PromiseInterface
    {
        // TODO: Implement getSessionIdentifiers() method.
    }

    /**
     * {@inheritDoc}
     */
    public function createSession(): PromiseInterface
    {
        // TODO: Implement createSession() method.
    }

    /**
     * {@inheritDoc}
     */
    public function removeSession(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement removeSession() method.
    }

    /**
     * {@inheritDoc}
     */
    public function getTabIdentifiers(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement getTabIdentifiers() method.
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveTabIdentifier(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement getActiveTabIdentifier() method.
    }

    /**
     * {@inheritDoc}
     */
    public function setActiveTab(string $sessionIdentifier, string $tabIdentifier): PromiseInterface
    {
        // TODO: Implement setActiveTab() method.
    }

    /**
     * {@inheritDoc}
     */
    public function openUri(string $sessionIdentifier, string $uri): PromiseInterface
    {
        // TODO: Implement openUri() method.
    }

    /**
     * {@inheritDoc}
     */
    public function getUri(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement getUri() method.
    }

    /**
     * {@inheritDoc}
     */
    public function getSource(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement getSource() method.
    }

    /**
     * {@inheritDoc}
     */
    public function getElementIdentifier(string $sessionIdentifier, string $xpathQuery): PromiseInterface
    {
        // TODO: Implement getElementIdentifier() method.
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveElement(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement getActiveElement() method.
    }

    /**
     * {@inheritDoc}
     */
    public function getElementVisibility(string $sessionIdentifier, array $elementIdentifier): PromiseInterface
    {
        // TODO: Implement getElementVisibility() method.
    }

    /**
     * {@inheritDoc}
     */
    public function clickElement(string $sessionIdentifier, array $elementIdentifier): PromiseInterface
    {
        // TODO: Implement clickElement() method.
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
    }

    /**
     * {@inheritDoc}
     */
    public function mouseLeftClick(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement mouseLeftClick() method.
    }

    /**
     * {@inheritDoc}
     */
    public function getScreenshot(string $sessionIdentifier): PromiseInterface
    {
        // TODO: Implement getScreenshot() method.
    }
}
