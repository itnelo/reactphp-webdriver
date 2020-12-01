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
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\Connector as SocketConnector;
use RuntimeException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Throwable;
use function React\Promise\Timer\timeout;

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
     * An event loop instance to manage an underlying browser and command timeouts
     *
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * Sends commands to the Selenium Grid endpoint using W3C protocol over HTTP
     *
     * @var Browser
     *
     * @see https://www.w3.org/TR/webdriver
     */
    private Browser $_httpClient;

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
     *
     * $webdriver = new \Itnelo\React\WebDriver\Client\W3CClient(
     *     $loop,
     *     [
     *         'server' => [
     *             'host' => 'selenium-hub',
     *             'port' => 4444,
     *         ],
     *         'command' => [
     *             'timeout' => 30,
     *         ],
     *         'browser' => [
     *             'tcp' => [
     *                 'bindto' => '192.168.56.10:0',
     *             ],
     *             'tls' => [
     *                 'verify_peer' => false,
     *                 'verify_peer_name' => false,
     *             ],
     *         ],
     *     ]
     * );
     * ```
     *
     * For all available "browser" options see \React\Socket\Connector class (will be instantiated for the underlying
     * browser) and socket context options: https://www.php.net/manual/en/context.socket.php.
     *
     * The "command.timeout" option here doesn't correlate with ReactPHP Browser's timeouts and will just cancel a
     * pending promise after the specified time (in seconds); an HTTP request itself, which is handled by the internal
     * ReactPHP Browser, may (or may not) be completed. Furthermore, the client can reject promise with a runtime
     * exception if an underlying browser has decided to stop waiting for the response by its own settings.
     *
     * @param LoopInterface $loop    An event loop instance to manage an underlying browser and command timeouts
     * @param array         $options Array of options for the client
     */
    public function __construct(LoopInterface $loop, array $options = [])
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

        $optionsResolver
            ->define('command')
            ->info('Options to control behavior of the commands, which will be executed on the remote server')
            ->default(
                function (OptionsResolver $requestOptionsResolver) {
                    $requestOptionsResolver
                        ->define('timeout')
                        ->info(
                            'Maximum time to wait (in seconds) for command execution '
                            . '(do not correlate with browser timeouts)'
                        )
                        ->allowedTypes('int')
                        ->default(30)
                    ;
                }
            )
        ;

        $optionsResolver
            ->define('browser')
            ->info('Options to customize a socket connector, which will be used by the internal http client')
            ->default(
                function (OptionsResolver $browserOptionsResolver) {
                    $browserOptionsResolver->setDefined(['tcp', 'tls', 'unix', 'dns', 'timeout', 'happy_eyeballs']);
                }
            )
        ;

        $this->_options = $optionsResolver->resolve($options);

        $socketConnector   = new SocketConnector($loop, $this->_options['browser']);
        $this->_httpClient = new Browser($loop, $socketConnector);

        $this->loop = $loop;
    }

    /**
     * {@inheritDoc}
     */
    public function getSessionIdentifiers(): PromiseInterface
    {
        // todo
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

        $responsePromise = $this->_httpClient->post($requestUri, $requestHeaders, $requestContents);

        $responsePromise->then(
            function (ResponseInterface $response) use ($sessionOpeningDeferred) {
                try {
                    $responseBody = (string) $response->getBody();
                    preg_match('/sessionid[":\s]+([a-z\d]{32})/Ui', $responseBody, $matches);

                    if (!isset($matches[1])) {
                        // todo: locate an error message or set it as "undefined error".
                        throw new RuntimeException('Unable to locate session identifier in the response.');
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

        // applying command timeout.
        $commandTimeoutInSeconds = $this->_options['command']['timeout'];

        // global rejection handler for all internal side effects (timeout inclusive).
        // todo: move to WebDriver wrapper
        $sessionIdentifierTimedPromise = timeout($sessionIdentifierPromise, $commandTimeoutInSeconds, $this->loop);

        $sessionIdentifierTimedPromise = $sessionIdentifierTimedPromise->otherwise(
            function (Throwable $rejectionReason) use (&$responsePromise) {
                if (method_exists($responsePromise, 'cancel')) {
                    $responsePromise->cancel();
                }
                $responsePromise = null;

                throw new RuntimeException('Unable to finish a session create command.', 0, $rejectionReason);
            }
        );

        return $sessionIdentifierTimedPromise;
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
