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

use React\Promise\PromiseInterface;

/**
 * Manipulates a remote browser instance asynchronously, using Selenium Grid (hub) API.
 *
 * As an extension to the base client, it can perform both action requests and some client-side (offline) tasks,
 * between these actions, such as conditional waits.
 */
interface WebDriverInterface extends ClientInterface
{
    /**
     * Returns a promise that will be resolved when the driver completes idling for the specified amount of time.
     *
     * Usage example:
     *
     * ```
     * $navigationPromise = $webdriver->openUri('https://github.com/itnelo');
     *
     * $elementIdentifierPromise = $navigationPromise->then(
     *     function () use ($webdriver) {
     *         // try-catch
     *         $timeHasComePromise = $webdriver->wait(5.0);
     *
     *         return $timeHasComePromise->then(
     *             function () use ($webdriver) {
     *                 return $webdriver->getElementIdentifier('sessionIdentifier', 'xpathQuery');
     *             }
     *         );
     *     }
     *     // handle rejection reason (e.g. a connection timeout due to unexpected rate limiting)
     * );
     *
     * $elementClickPromise = $elementIdentifierPromise->then(
     *     function (string $elementIdentifier) use ($webdriver) {
     *         // try-catch
     *         return $webdriver->clickElement('sessionIdentifier', $elementIdentifier);
     *     }
     *     // handle rejection reason (e.g. invalid xpath or element not found error)
     * );
     * ```
     *
     * Note: each wait call is a separate action and starts its own timer; that timer will not wait for any other
     * timers to fire (i.e. this method is not suited for "shooting" an array of concurrent requests with delays, to
     * bypass rate limits, but to wait page loading and javascript code). Basically, it is just a syntactic sugar for
     * promise timer boilerplate, so you should use the event reactor directly, to limit driver calls for some
     * sensitive operations.
     *
     * @param float $time Time in seconds to wait (e.g. 0.351; max precision can be 3)
     *
     * @return PromiseInterface<null>
     */
    public function wait(float $time): PromiseInterface;

    /**
     * Returns a promise that will be resolved when a given condition is met within specified amount of time and
     * rejected, otherwise.
     *
     * A condition callback must return an instance of PromiseInterface. Whenever that promise becomes rejected, driver
     * will try to get a new promise from the callback, until it reaches a given timeout for retry attempts.
     *
     * Usage example:
     *
     * ```
     * $becomeVisiblePromise = $webdriver->waitUntil(
     *     15.5,
     *     function () use ($webdriver) {
     *         $visibilityStatePromise = $webdriver->getElementVisibility(...);
     *
     *         return $visibilityStatePromise->then(
     *             function (bool $isVisible) {
     *                 if (!$isVisible) {
     *                     throw new RuntimeException("Not visible yet! Let's retry!");
     *                 }
     *
     *                 return true;
     *             }
     *         );
     *     }
     * );
     *
     * $becomeVisiblePromise->then(
     *     function () use ($webdriver) {
     *         // try-catch
     *         $webdriver->clickElement(...);    // sending a click command only if we are sure the target is visible.
     *     }
     *     // handle case when the element is not visible on the page
     * );
     * ```
     *
     * @param float    $time                 Time (in seconds) to wait for successfully resolved promise from the
     *                                       condition callback
     * @param callable $conditionMetCallback A condition to be met, as a callback
     *
     * @return PromiseInterface<null>
     */
    public function waitUntil(float $time, callable $conditionMetCallback): PromiseInterface;
}