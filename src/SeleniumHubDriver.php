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

namespace Itnelo\React\WebDriver;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use RuntimeException;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as ConfigurationExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Throwable;
use function React\Promise\reject;
use function React\Promise\Timer\timeout;

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
     * Array of options for the driver
     *
     * @var array
     */
    private array $_options;

    /**
     * SeleniumHubDriver constructor.
     *
     * @param LoopInterface   $loop      The event loop reference to manage promise timeouts and other async routines
     * @param ClientInterface $hubClient Base client implementation for sending commands to the remote hub server
     * @param array           $options   Array of options for the driver
     *
     * @throws ConfigurationExceptionInterface Whenever an error has been occurred during driver configuration
     */
    public function __construct(LoopInterface $loop, ClientInterface $hubClient, array $options = [])
    {
        $optionsResolver = new OptionsResolver();

        $optionsResolver
            ->define('command')
            ->info('Options to control behavior of the commands, which will be executed on the remote server')
            ->default(
                function (OptionsResolver $requestOptionsResolver) {
                    $requestOptionsResolver
                        ->define('timeout')
                        ->info(
                            'Maximum time to wait (in seconds) for command execution '
                            . '(do not correlate with HTTP timeouts)'
                        )
                        ->allowedTypes('int')
                        ->default(30)
                    ;
                }
            )
        ;

        $this->_options = $optionsResolver->resolve($options);

        $this->loop      = $loop;
        $this->hubClient = $hubClient;
    }

    /**
     * {@inheritDoc}
     */
    public function wait(float $time): PromiseInterface
    {
        // TODO: Implement wait() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function waitUntil(float $time, callable $conditionMetCallback): PromiseInterface
    {
        // TODO: Implement waitUntil() method.

        return reject(new RuntimeException('Not implemented.'));
    }

    /**
     * {@inheritDoc}
     */
    public function createSession(): PromiseInterface
    {
        $sessionIdentifierPromise = $this->hubClient->createSession();

        // applying command timeout.
        $commandTimeoutInSeconds = $this->_options['command']['timeout'];

        // global rejection handler for all internal side effects (timeout inclusive).
        // todo: move to the separate service
        $sessionIdentifierTimedPromise = timeout($sessionIdentifierPromise, $commandTimeoutInSeconds, $this->loop);

        $sessionIdentifierTimedPromise = $sessionIdentifierTimedPromise->otherwise(
            function (Throwable $rejectionReason) {
                throw new RuntimeException('Unable to finish a session create command.', 0, $rejectionReason);
            }
        );

        return $sessionIdentifierTimedPromise;
    }

    /**
     * {@inheritDoc}
     */
    public function getSessionIdentifiers(): PromiseInterface
    {
        // TODO: Implement getSessionIdentifiers() method.

        return reject(new RuntimeException('Not implemented.'));
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
        // TODO: Implement getTabIdentifiers() method.

        return reject(new RuntimeException('Not implemented.'));
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
        // TODO: Implement getCurrentUri() method.

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
