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

namespace Itnelo\React\WebDriver;

use Itnelo\React\WebDriver\Routine\Condition\CheckRoutine as ConditionCheckRoutine;
use Itnelo\React\WebDriver\Timeout\Interceptor as TimeoutInterceptor;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Stream\WritableResourceStream;
use RuntimeException;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as ConfigurationExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Throwable;
use function React\Promise\reject;
use function React\Promise\Timer\resolve;

/**
 * Sends action requests to the Selenium Grid server (hub) and controls their async execution
 */
class SeleniumHubDriver implements WebDriverInterface
{
    /**
     * The event loop reference to manage promise timeouts and other async routines
     *
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * Base client implementation for sending commands to the Selenium Grid server (hub)
     *
     * @var ClientInterface
     */
    private ClientInterface $hubClient;

    /**
     * Cancels a driver promise if it isn't resolved within the specified amount of time
     *
     * @var TimeoutInterceptor
     */
    private TimeoutInterceptor $timeoutInterceptor;

    /**
     * Array of options for the driver
     *
     * @var array
     */
    private array $_options;

    /**
     * SeleniumHubDriver constructor.
     *
     * @param LoopInterface      $loop               The event loop reference to manage promise timeouts
     * @param ClientInterface    $hubClient          Base client implementation for sending commands to the server
     * @param TimeoutInterceptor $timeoutInterceptor Cancels a driver promise if it isn't resolved for too long
     * @param array              $options            Array of options for the driver
     *
     * @throws ConfigurationExceptionInterface Whenever an error has been occurred during driver configuration
     */
    public function __construct(
        LoopInterface $loop,
        ClientInterface $hubClient,
        TimeoutInterceptor $timeoutInterceptor,
        array $options = []
    ) {
        $optionsResolver = new OptionsResolver();

        $this->_options = $optionsResolver->resolve($options);

        $this->loop               = $loop;
        $this->hubClient          = $hubClient;
        $this->timeoutInterceptor = $timeoutInterceptor;
    }

    /**
     * {@inheritDoc}
     */
    public function createSession(): PromiseInterface
    {
        $sessionIdentifierPromise = $this->hubClient->createSession();

        return $this->timeoutInterceptor->applyTimeout(
            $sessionIdentifierPromise,
            'Unable to complete a session create command.'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getSessionIdentifiers(): PromiseInterface
    {
        // todo: implementation

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function removeSession(string $sessionIdentifier): PromiseInterface
    {
        // todo: implementation

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function getTabIdentifiers(string $sessionIdentifier): PromiseInterface
    {
        $sessionIdentifierPromise = $this->hubClient->getTabIdentifiers($sessionIdentifier);

        return $this->timeoutInterceptor->applyTimeout(
            $sessionIdentifierPromise,
            'Unable to complete a tab lookup command.'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveTabIdentifier(string $sessionIdentifier): PromiseInterface
    {
        $tabIdentifierPromise = $this->hubClient->getActiveTabIdentifier($sessionIdentifier);

        return $this->timeoutInterceptor->applyTimeout(
            $tabIdentifierPromise,
            'Unable to complete a get active tab command.'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function setActiveTab(string $sessionIdentifier, string $tabIdentifier): PromiseInterface
    {
        $switchConfirmationPromise = $this->hubClient->setActiveTab($sessionIdentifier, $tabIdentifier);

        return $this->timeoutInterceptor->applyTimeout(
            $switchConfirmationPromise,
            'Unable to complete a set active tab command.'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function openUri(string $sessionIdentifier, string $uri): PromiseInterface
    {
        $navigationPromise = $this->hubClient->openUri($sessionIdentifier, $uri);

        return $this->timeoutInterceptor->applyTimeout($navigationPromise, 'Unable to complete an open URI command.');
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentUri(string $sessionIdentifier): PromiseInterface
    {
        $currentUriPromise = $this->hubClient->getCurrentUri($sessionIdentifier);

        return $this->timeoutInterceptor->applyTimeout(
            $currentUriPromise,
            'Unable to complete a get current uri command.'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getSource(string $sessionIdentifier): PromiseInterface
    {
        $sourceCodePromise = $this->hubClient->getSource($sessionIdentifier);

        return $this->timeoutInterceptor->applyTimeout($sourceCodePromise, 'Unable to complete a get source command.');
    }

    /**
     * {@inheritDoc}
     */
    public function getElementIdentifier(string $sessionIdentifier, string $xpathQuery): PromiseInterface
    {
        $elementIdentifierPromise = $this->hubClient->getElementIdentifier($sessionIdentifier, $xpathQuery);

        return $this->timeoutInterceptor->applyTimeout(
            $elementIdentifierPromise,
            'Unable to complete a get element identifier command.'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getActiveElementIdentifier(string $sessionIdentifier): PromiseInterface
    {
        $elementIdentifierPromise = $this->hubClient->getActiveElementIdentifier($sessionIdentifier);

        return $this->timeoutInterceptor->applyTimeout(
            $elementIdentifierPromise,
            'Unable to complete a get active element identifier command.'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getElementVisibility(string $sessionIdentifier, array $elementIdentifier): PromiseInterface
    {
        $visibilityStatusPromise = $this->hubClient->getElementVisibility($sessionIdentifier, $elementIdentifier);

        return $this->timeoutInterceptor->applyTimeout(
            $visibilityStatusPromise,
            'Unable to complete a get element visibility command.'
        );
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
        $keypressConfirmationPromise = $this->hubClient->keypressElement(
            $sessionIdentifier,
            $elementIdentifier,
            $keySequence
        );

        return $this->timeoutInterceptor->applyTimeout(
            $keypressConfirmationPromise,
            'Unable to complete a keypress element command.'
        );
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
        $moveConfirmationPromise = $this->hubClient->mouseMove(
            $sessionIdentifier,
            $offsetX,
            $offsetY,
            $moveDuration,
            $startingPoint
        );

        return $this->timeoutInterceptor->applyTimeout(
            $moveConfirmationPromise,
            'Unable to complete a mouse move command.'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function mouseLeftClick(string $sessionIdentifier): PromiseInterface
    {
        $clickConfirmationPromise = $this->hubClient->mouseLeftClick($sessionIdentifier);

        return $this->timeoutInterceptor->applyTimeout(
            $clickConfirmationPromise,
            'Unable to complete a mouse click command.'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function wait(float $time = 30.0): PromiseInterface
    {
        $idlePromise = resolve($time, $this->loop);

        return $idlePromise;
    }

    /**
     * {@inheritDoc}
     */
    public function waitUntil(
        callable $conditionMetCallback,
        float $time = 30.0,
        float $checkInterval = 0.5
    ): PromiseInterface {
        $timeNormalized          = max(0.5, $time);
        $checkIntervalNormalized = max(0.1, $checkInterval);

        // todo: probably, should be redesigned, to reduce amount of "new" calls
        $timeoutInterceptor = new TimeoutInterceptor($this->loop, $timeNormalized);
        $checkRoutine       = new ConditionCheckRoutine($this->loop, $timeoutInterceptor);

        $conditionMetPromise = $checkRoutine->run($conditionMetCallback, $checkIntervalNormalized);

        return $conditionMetPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function getScreenshot(string $sessionIdentifier): PromiseInterface
    {
        $screenshotPromise = $this->hubClient->getScreenshot($sessionIdentifier);

        return $this->timeoutInterceptor->applyTimeout(
            $screenshotPromise,
            'Unable to complete a get screenshot command.'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function saveScreenshot(string $sessionIdentifier, string $filePath): PromiseInterface
    {
        $savingDeferred = new Deferred();

        $screenshotPromise = $this->hubClient->getScreenshot($sessionIdentifier);

        $screenshotPromise
            ->then(
                function (string $imageContents) use ($savingDeferred, $filePath) {
                    $fileResource = fopen($filePath, 'w');
                    $writeStream  = new WritableResourceStream($fileResource, $this->loop);

                    $writeStream->end($imageContents);

                    $writeStream->on(
                        'drain',
                        function () use ($savingDeferred, $writeStream) {
                            // explicitly removing all listeners, because we don't have a contract with guarantees that
                            // this handler will be triggered once, see https://github.com/reactphp/stream#drain-event.
                            $writeStream->removeAllListeners('drain');

                            $savingDeferred->resolve(null);
                        }
                    );

                    $writeStream->on(
                        'error',
                        function (Throwable $exception) use ($savingDeferred) {
                            $reason = new RuntimeException('Unable to save a screenshot (stream).', 0, $exception);

                            $savingDeferred->reject($reason);
                        }
                    );
                }
            )
            ->then(
                null,
                function (Throwable $rejectionReason) use ($savingDeferred) {
                    $reason = new RuntimeException('Unable to save a screenshot.', 0, $rejectionReason);

                    $savingDeferred->reject($reason);
                }
            )
        ;

        $saveConfirmationPromise = $savingDeferred->promise();

        return $this->timeoutInterceptor->applyTimeout(
            $saveConfirmationPromise,
            'Unable to complete a save screenshot command.'
        );
    }
}
