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

namespace Itnelo\React\WebDriver\Routine\Condition;

use Itnelo\React\WebDriver\Timeout\Interceptor as TimeoutInterceptor;
use LogicException;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RuntimeException;
use Throwable;

/**
 * Runs periodic condition checks for the web driver, using the specified event loop instance
 *
 * @internal
 */
final class CheckRoutine
{
    /**
     * Event loop reference
     *
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * Cancels a driver promise if it isn't resolved for too long
     *
     * @var TimeoutInterceptor
     */
    private TimeoutInterceptor $timeoutInterceptor;

    /**
     * A timer, registered in the event loop, which executes routine logic
     *
     * @var TimerInterface|null
     */
    private ?TimerInterface $_evaluationTimer;

    /**
     * A flag that is set, when the routine already waits promise result from the next check iteration
     *
     * @var bool
     */
    private bool $_isEvaluating;

    /**
     * CheckRoutine constructor.
     *
     * @param LoopInterface      $loop               Event loop reference
     * @param TimeoutInterceptor $timeoutInterceptor Cancels a driver promise if it isn't resolved for too long
     */
    public function __construct(LoopInterface $loop, TimeoutInterceptor $timeoutInterceptor)
    {
        $this->loop               = $loop;
        $this->timeoutInterceptor = $timeoutInterceptor;

        $this->_evaluationTimer = null;
        $this->_isEvaluating    = false;
    }

    /**
     * Runs a routine logic in the loop timer and returns a promise, which will be resolved when the condition is met
     *
     * @param callable $conditionMetCallback A condition to be met, as a callback
     * @param float    $checkInterval        The interval for condition checks, in seconds
     *
     * @return PromiseInterface<null>
     *
     * @throws RuntimeException Whenever a routine is already is the running state
     */
    public function run(callable $conditionMetCallback, float $checkInterval = 0.5): PromiseInterface
    {
        if ($this->_evaluationTimer instanceof TimerInterface) {
            throw new RuntimeException('Routine is already running.');
        }

        return $this->runInternal($conditionMetCallback, $checkInterval);
    }

    /**
     * Returns a promise that will be resolved when the check routine successfully evaluates a given callback
     *
     * @param callable $conditionMetCallback A condition to be met, as a callback
     * @param float    $checkInterval        The interval for condition checks, in seconds
     *
     * @return PromiseInterface<null>
     */
    private function runInternal(callable $conditionMetCallback, float $checkInterval): PromiseInterface
    {
        $evaluationDeferred = new Deferred();

        $evaluationLogic = function () use ($evaluationDeferred, $conditionMetCallback) {
            // do not try to evaluate a condition if a previous result promise was not resolved/rejected.
            if (false !== $this->_isEvaluating) {
                return;
            }

            try {
                // receiving a new result promise from the condition callback.
                $resultPromise = $conditionMetCallback();

                if (!$resultPromise instanceof PromiseInterface) {
                    $invalidPromiseExceptionMessage = sprintf(
                        'Return value from the condition callable must be an instance of %s.',
                        PromiseInterface::class
                    );

                    throw new LogicException($invalidPromiseExceptionMessage);
                }

                // handling evaluation results.
                $this->_isEvaluating = true;
                $resultPromise->then(
                    function () use ($evaluationDeferred) {
                        // at some point, a promise has been successfully resolved.
                        $evaluationDeferred->resolve(null);
                    },
                    function (Throwable $rejectionReason) {
                        // signals that we can take another promise from the condition callback to continue our checks.
                        $this->_isEvaluating = false;
                    }
                );
            } catch (Throwable $exception) {
                $reason = new RuntimeException('Unable to evaluate a condition callback.', 0, $exception);

                $evaluationDeferred->reject($reason);
            }
        };

        // registering a periodic timer in the event loop.
        $this->_evaluationTimer = $this->loop->addPeriodicTimer($checkInterval, $evaluationLogic);

        $conditionMetPromise = $evaluationDeferred->promise();

        $conditionMetTimedPromise = $this->timeoutInterceptor->applyTimeout(
            $conditionMetPromise,
            'A condition is not met within the specified amount of time.'
        );

        return $conditionMetTimedPromise->then(
            function () {
                // cleaning up a related timer with condition-check logic.
                $this->loop->cancelTimer($this->_evaluationTimer);

                return null;
            },
            function (Throwable $rejectionReason) {
                $this->loop->cancelTimer($this->_evaluationTimer);

                throw $rejectionReason;
            }
        );
    }
}
