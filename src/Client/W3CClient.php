<?php

/*
 * This file is part of the ReactPHP WebDriver <https://github.com/itnelo/reactphp-webdriver>.
 *
 * (c) 2020-2021 Pavel Petrov <itnelo@gmail.com>.
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
     * A pattern to recognize session identifier in the WebDriver response
     *
     * @var string
     */
    private const PATTERN_SESSION_IDENTIFIER = '/[":\s]+([a-z\d]{32})/Ui';

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
        $requestUri = sprintf(
            'http://%s:%d/wd/hub/sessions',
            $this->_options['server']['host'],
            $this->_options['server']['port']
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $responsePromise = $this->httpClient->get($requestUri, $requestHeaders);

        $identifierListPromise = $responsePromise
            ->then(
                function (ResponseInterface $response) {
                    $dataArray = $this->deserializeResponse($response);
                    $payload   = $dataArray['message'] ?? '';

                    preg_match_all(self::PATTERN_SESSION_IDENTIFIER, $payload, $matches);

                    if (!isset($matches[1]) || 1 > count($matches[1])) {
                        throw new RuntimeException('Unable to locate a list of session identifiers in the response.');
                    }

                    $identifierList = $matches[1];

                    return $identifierList;
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException('Unable to get a list of session identifiers.', 0, $rejectionReason);
                }
            )
        ;

        return $identifierListPromise;
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
                    preg_match(self::PATTERN_SESSION_IDENTIFIER, $responseBody, $matches);

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
        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session/%s',
            $this->_options['server']['host'],
            $this->_options['server']['port'],
            $sessionIdentifier
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $responsePromise = $this->httpClient->delete($requestUri, $requestHeaders);

        $quitConfirmationPromise = $responsePromise
            ->then(fn (ResponseInterface $response) => $this->onCommandConfirmation($response))
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException('Unable to close a session.', 0, $rejectionReason);
                }
            )
        ;

        return $quitConfirmationPromise;
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
                    $tabIdentifiers = $this->deserializeResponse($response);

                    $tabLookupDeferred->resolve($tabIdentifiers);
                } catch (Throwable $exception) {
                    $reason = new RuntimeException(
                        'Unable to get tab identifiers (response deserialization).',
                        0,
                        $exception
                    );

                    $tabLookupDeferred->reject($reason);
                }
            },
            function (Throwable $rejectionReason) use ($tabLookupDeferred) {
                $reason = new RuntimeException('Unable to get tab identifiers (request).', 0, $rejectionReason);

                $tabLookupDeferred->reject($reason);
            }
        );

        $identifierListPromise = $tabLookupDeferred->promise();

        return $identifierListPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveTabIdentifier(string $sessionIdentifier): PromiseInterface
    {
        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session/%s/window',
            $this->_options['server']['host'],
            $this->_options['server']['port'],
            $sessionIdentifier
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $responsePromise = $this->httpClient->get($requestUri, $requestHeaders);

        $tabIdentifierPromise = $responsePromise
            ->then(
                function (ResponseInterface $response) {
                    $tabIdentifier = $this->deserializeResponse($response);

                    return $tabIdentifier;
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException('Unable to get an identifier for the active tab.', 0, $rejectionReason);
                }
            )
        ;

        return $tabIdentifierPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function setActiveTab(string $sessionIdentifier, string $tabIdentifier): PromiseInterface
    {
        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session/%s/window',
            $this->_options['server']['host'],
            $this->_options['server']['port'],
            $sessionIdentifier
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $requestContents = json_encode(['handle' => $tabIdentifier]);

        $responsePromise = $this->httpClient->post($requestUri, $requestHeaders, $requestContents);

        $switchConfirmationPromise = $responsePromise
            ->then(
                function (ResponseInterface $response) {
                    $this->onCommandConfirmation($response, 'Tab switch command is not confirmed.');

                    return null;
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException('Unable to focus a tab.', 0, $rejectionReason);
                }
            )
        ;

        return $switchConfirmationPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function openUri(string $sessionIdentifier, string $uri): PromiseInterface
    {
        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session/%s/url',
            $this->_options['server']['host'],
            $this->_options['server']['port'],
            $sessionIdentifier
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $requestContents = json_encode(['url' => $uri]);

        $responsePromise = $this->httpClient->post($requestUri, $requestHeaders, $requestContents);

        $navigationPromise = $responsePromise
            ->then(
                function (ResponseInterface $response) {
                    $this->onCommandConfirmation($response, 'URI navigation is not confirmed.');

                    return null;
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException('Unable to open an URI.', 0, $rejectionReason);
                }
            )
        ;

        return $navigationPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentUri(string $sessionIdentifier): PromiseInterface
    {
        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session/%s/url',
            $this->_options['server']['host'],
            $this->_options['server']['port'],
            $sessionIdentifier
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $responsePromise = $this->httpClient->get($requestUri, $requestHeaders);

        $currentUriPromise = $responsePromise
            ->then(
                function (ResponseInterface $response) {
                    $uriCurrent = $this->deserializeResponse($response);

                    return $uriCurrent;
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException('Unable to get a current URI.', 0, $rejectionReason);
                }
            )
        ;

        return $currentUriPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function getSource(string $sessionIdentifier): PromiseInterface
    {
        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session/%s/source',
            $this->_options['server']['host'],
            $this->_options['server']['port'],
            $sessionIdentifier
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $responsePromise = $this->httpClient->get($requestUri, $requestHeaders);

        $sourceCodePromise = $responsePromise
            ->then(
                function (ResponseInterface $response) {
                    $sourceCode = $this->deserializeResponse($response);

                    return $sourceCode;
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException('Unable to get source code of the web resource.', 0, $rejectionReason);
                }
            )
        ;

        return $sourceCodePromise;
    }

    /**
     * {@inheritDoc}
     */
    public function getElementIdentifier(string $sessionIdentifier, string $xpathQuery): PromiseInterface
    {
        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session/%s/element',
            $this->_options['server']['host'],
            $this->_options['server']['port'],
            $sessionIdentifier
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $requestContents = json_encode(['using' => 'xpath', 'value' => $xpathQuery]);

        $responsePromise = $this->httpClient->post($requestUri, $requestHeaders, $requestContents);

        $elementIdentifierPromise = $responsePromise
            ->then(
                function (ResponseInterface $response) {
                    $elementIdentifier = $this->extractElementIdentifier($response);

                    return $elementIdentifier;
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException('Unable to get an element identifier.', 0, $rejectionReason);
                }
            )
        ;

        return $elementIdentifierPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveElementIdentifier(string $sessionIdentifier): PromiseInterface
    {
        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session/%s/element/active',
            $this->_options['server']['host'],
            $this->_options['server']['port'],
            $sessionIdentifier
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $responsePromise = $this->httpClient->get($requestUri, $requestHeaders);

        $elementIdentifierPromise = $responsePromise
            ->then(
                function (ResponseInterface $response) {
                    $elementIdentifier = $this->extractElementIdentifier($response);

                    return $elementIdentifier;
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException(
                        'Unable to get an identifier of the active element.',
                        0,
                        $rejectionReason
                    );
                }
            )
        ;

        return $elementIdentifierPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function getElementVisibility(string $sessionIdentifier, array $elementIdentifier): PromiseInterface
    {
        // todo: safer checks (or hide internals behind a transfer object/contract)
        $elementHandle = array_key_first($elementIdentifier);
        if (!is_string($elementHandle)) {
            throw new RuntimeException('Unexpected format for the element identifier.');
        }

        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session/%s/element/%s/displayed',
            $this->_options['server']['host'],
            $this->_options['server']['port'],
            $sessionIdentifier,
            $elementIdentifier[$elementHandle]
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $responsePromise = $this->httpClient->get($requestUri, $requestHeaders);

        $visibilityStatusPromise = $responsePromise
            ->then(
                function (ResponseInterface $response) {
                    $visibilityStatus = $this->deserializeResponse($response);

                    return $visibilityStatus;
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException('Unable to get visibility status for the element.', 0, $rejectionReason);
                }
            )
        ;

        return $visibilityStatusPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function clickElement(string $sessionIdentifier, array $elementIdentifier): PromiseInterface
    {
        // todo: implementation

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
        // todo: safer checks (or hide internals behind a transfer object/contract)
        $elementHandle = array_key_first($elementIdentifier);
        if (!is_string($elementHandle)) {
            throw new RuntimeException('Unexpected format for the element identifier.');
        }

        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session/%s/element/%s/value',
            $this->_options['server']['host'],
            $this->_options['server']['port'],
            $sessionIdentifier,
            $elementIdentifier[$elementHandle]
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $requestContents = json_encode(['text' => $keySequence]);

        $responsePromise = $this->httpClient->post($requestUri, $requestHeaders, $requestContents);

        $keypressConfirmationPromise = $responsePromise
            ->then(
                function (ResponseInterface $response) {
                    $this->onCommandConfirmation($response, 'Keypress command is not confirmed.');

                    return null;
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException('Unable to apply keyboard keys to the element.', 0, $rejectionReason);
                }
            )
        ;

        return $keypressConfirmationPromise;
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
        if (is_array($startingPoint)) {
            $actionOrigin = $startingPoint;
        } else {
            $actionOrigin = 'pointer';
        }

        $mouseActions = [
            [
                'type'     => 'pointerMove',
                'origin'   => $actionOrigin,
                'x'        => $offsetX,
                'y'        => $offsetY,
                'duration' => $moveDuration,
            ],
        ];

        $responsePromise = $this->requestMouseActions($sessionIdentifier, $mouseActions);

        $moveConfirmationPromise = $responsePromise
            ->then(
                function (ResponseInterface $response) {
                    $this->onCommandConfirmation($response, 'Mouse move command is not confirmed.');

                    return null;
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException('Unable to confirm mouse move action.', 0, $rejectionReason);
                }
            )
        ;

        return $moveConfirmationPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function mouseLeftClick(string $sessionIdentifier): PromiseInterface
    {
        $mouseActions = [
            [
                'type'   => 'pointerDown',
                'button' => 0,
            ],
            [
                'type'   => 'pointerUp',
                'button' => 0,
            ],
        ];

        $responsePromise = $this->requestMouseActions($sessionIdentifier, $mouseActions);

        $clickConfirmationPromise = $responsePromise
            ->then(
                function (ResponseInterface $response) {
                    $this->onCommandConfirmation($response, 'Mouse click command is not confirmed.');

                    return null;
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException('Unable to confirm mouse click action.', 0, $rejectionReason);
                }
            )
        ;

        return $clickConfirmationPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function getScreenshot(string $sessionIdentifier): PromiseInterface
    {
        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session/%s/screenshot',
            $this->_options['server']['host'],
            $this->_options['server']['port'],
            $sessionIdentifier
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $responsePromise = $this->httpClient->get($requestUri, $requestHeaders);

        $imageContentsPromise = $responsePromise
            ->then(
                function (ResponseInterface $response) {
                    $imageContentsEncoded = $this->deserializeResponse($response);
                    $imageContents        = base64_decode($imageContentsEncoded);

                    return $imageContents;
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) {
                    throw new RuntimeException('Unable to get a screenshot.', 0, $rejectionReason);
                }
            )
        ;

        return $imageContentsPromise;
    }

    /**
     * Initializes a request to execute a sequence of mouse-specific actions in the remote browser
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     * @param array  $mouseActions      A data structure that describes a sequence of actions to be performed by the
     *                                  internal pointer with type "mouse"
     *
     * @return PromiseInterface<ResponseInterface>
     */
    private function requestMouseActions(string $sessionIdentifier, array $mouseActions): PromiseInterface
    {
        $requestUri = sprintf(
            'http://%s:%d/wd/hub/session/%s/actions',
            $this->_options['server']['host'],
            $this->_options['server']['port'],
            $sessionIdentifier
        );

        $requestHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        $requestContents = json_encode(
            [
                'actions' => [
                    [
                        'type'       => 'pointer',
                        'id'         => 'mouse',
                        'parameters' => [
                            'pointerType' => 'mouse',
                        ],
                        'actions'    => $mouseActions,
                    ],
                ],
            ]
        );

        $responsePromise = $this->httpClient->post($requestUri, $requestHeaders, $requestContents);

        return $responsePromise;
    }

    /**
     * Returns an element identifier, which has to be extracted from the response message (a surgical approach)
     *
     * @param ResponseInterface $response PSR-7 response message from the Selenium hub with action results
     *
     * @return array
     */
    private function extractElementIdentifier(ResponseInterface $response): array
    {
        $responseBody = (string) $response->getBody();

        preg_match(
            '/(element(?:-[a-z\d]{4}){4}[a-z\d]{8})[":\s]+([a-z\d]{8}(?:-[a-z\d]{4}){4}[a-z\d]{8})/Ui',
            $responseBody,
            $matches
        );

        if (!isset($matches[1], $matches[2])) {
            // todo: locate an error message or set it as "undefined error"
            throw new RuntimeException('Unable to locate element identifier parts in the response.');
        }

        $elementIdentifier = [$matches[1] => $matches[2]];

        return $elementIdentifier;
    }

    /**
     * Ensures that a related action is properly executed (confirmed) by the remote server, triggers an error otherwise.
     *
     * It is used when no specific context is required to confirm successful command execution (some methods will use
     * more advanced confirmation checks instead of this "default").
     *
     * @param ResponseInterface $response     PSR-7 response message from the Selenium hub with action results
     * @param string            $errorMessage Will be used if an error is registered during confirmation check
     *
     * @return void
     *
     * @throws RuntimeException Whenever an error has been occurred during confirmation check for a remote action
     */
    private function onCommandConfirmation(ResponseInterface $response, string $errorMessage): void
    {
        $responseValueNode = $this->deserializeResponse($response);

        if (null === $responseValueNode) {
            return;
        }

        $driverMessage            = $responseValueNode['message'] ?? 'undefined driver error';
        $confirmationErrorMessage = sprintf('%s %s.', $errorMessage, $driverMessage);

        throw new RuntimeException($confirmationErrorMessage);
    }

    /**
     * Returns a "value" node contents, which will be extracted from the PSR-7 response message
     *
     * @param ResponseInterface $response PSR-7 response message from the Selenium hub with action results
     *
     * @return mixed
     */
    private function deserializeResponse(ResponseInterface $response)
    {
        $responseBody     = (string) $response->getBody();
        $bodyDeserialized = json_decode($responseBody, true);

        if (!array_key_exists('value', $bodyDeserialized)) {
            // todo: locate an error message or set it as "undefined error"
            throw new RuntimeException('Unable to locate "value" node (response deserialization).');
        }

        return $bodyDeserialized['value'];
    }
}
