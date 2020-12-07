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

namespace Itnelo\React\WebDriver\Timeout;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Promise\Timer\TimeoutException;
use RuntimeException;
use Throwable;
use function React\Promise\Timer\timeout;

/**
 * Cancels a driver promise if it isn't resolved within the specified amount of time
 */
class Interceptor
{
    /**
     * The event loop reference to track time
     *
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * Time (in seconds) to wait until promise will be rejected
     *
     * @var float
     */
    private float $timeout;

    /**
     * Interceptor constructor.
     *
     * @param LoopInterface $loop    The event loop reference to track time
     * @param float         $timeout Time (in seconds) to wait until promise will be rejected
     */
    public function __construct(LoopInterface $loop, float $timeout)
    {
        $this->loop    = $loop;
        $this->timeout = $timeout;
    }

    /**
     * Applies a timeout logic for the given promise
     *
     * @param PromiseInterface $promise          Promise to be timed out
     * @param string           $rejectionMessage Message for rejection reason
     *
     * @return PromiseInterface<mixed>
     */
    public function applyTimeout(
        PromiseInterface $promise,
        string $rejectionMessage = 'Unable to complete a command.'
    ): PromiseInterface {
        $timedPromise = timeout($promise, $this->timeout, $this->loop);

        $timedPromise = $timedPromise->then(
            null,
            function (Throwable $rejectionReason) use ($rejectionMessage) {
                if (!$rejectionReason instanceof TimeoutException) {
                    throw $rejectionReason;
                }

                $promiseTimerExceptionMessage = $rejectionReason->getMessage();
                $timeoutRejectionMessage      = sprintf('%s %s.', $rejectionMessage, $promiseTimerExceptionMessage);

                throw new RuntimeException($timeoutRejectionMessage);
            }
        );

        return $timedPromise;
    }
}
