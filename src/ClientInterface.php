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
 * The async client for Selenium Grid server (hub)
 *
 * @see https://github.com/SeleniumHQ/docker-selenium
 */
interface ClientInterface
{
    /**
     * Returns a promise that resolves to collection of session identifiers (ex. '0dd27f03d50e795f8499d1c99f7c01dd'),
     * which are available for command execution.
     *
     * Each session represent an opened browser, with some user profile and tabs (window handles).
     *
     * Resulting collection represents a Traversable<string> or string[].
     *
     * @return PromiseInterface<iterable>
     */
    public function getSessionIdentifiers(): PromiseInterface;

    /**
     * Returns a promise that resolves to a string, representing identifier of session that has been started by the
     * remote WebDriver service.
     *
     * Usage example:
     *
     * ```
     * $sessionIdentifierPromise = $webDriver->createSession();
     *
     * $sessionIdentifierPromise->then(
     *     function (string $sessionIdentifier) {
     *         // do some work
     *     },
     *     function (Throwable $rejectionReason) {
     *         // handle exception
     *     }
     * );
     * ```
     *
     * todo: argument to send desired capabilities, i.e. browser arguments and preferences (omitted)
     * todo: must also return a collection of confirmed browser capabilities (omitted)
     *
     * @return PromiseInterface<string>
     */
    public function createSession(): PromiseInterface;

    /**
     * Returns a promise that will be resolved when the remote WebDriver service confirms closed session.
     *
     * Such session can't be used (or resumed) after this promise is successfully resolved.
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     *
     * @return PromiseInterface<null>
     */
    public function removeSession(string $sessionIdentifier): PromiseInterface;

    /**
     * Returns a promise that resolves to collection of tab identifiers (i.e. "window handles") that currently are
     * opened in the browser.
     *
     * Resulting collection represents a string[].
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     *
     * @return PromiseInterface<array>
     */
    public function getTabIdentifiers(string $sessionIdentifier): PromiseInterface;

    /**
     * Returns a promise that resolves to a string, representing an identifier of browser tab that currently is active
     * (focused) and available for interaction (ex. 'CDwindow-A4F079B43D7E1D267F6D5F11FE8CC374')
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     *
     * @return PromiseInterface<string>
     */
    public function getActiveTabIdentifier(string $sessionIdentifier): PromiseInterface;

    /**
     * Returns a promise that will be resolved when the remote WebDriver service confirms browser tab switching using a
     * given identifier (window handle).
     *
     * Note: you can set to active state only existing tabs (see {@link getTabIdentifiers()}).
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     * @param string $tabIdentifier     Window handle for "tab" switching operation
     *
     * @return PromiseInterface<null>
     */
    public function setActiveTab(string $sessionIdentifier, string $tabIdentifier): PromiseInterface;

    /**
     * Returns a promise that will be resolved when the remote WebDriver service confirms URI navigation within
     * currently active (focused) tab ({@link getActiveTabIdentifier()})
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     * @param string $uri               Website URL or any other resource identifier, to open in the active (focused)
     *                                  browser tab
     *
     * @return PromiseInterface<null>
     */
    public function openUri(string $sessionIdentifier, string $uri): PromiseInterface;

    /**
     * Returns a promise that resolves to a string, representing an identifier of web resource, which is opened in the
     * currently active browser tab (ex. 'https://github.com/itnelo')
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     *
     * @return PromiseInterface<string>
     */
    public function getCurrentUri(string $sessionIdentifier): PromiseInterface;

    /**
     * Returns a promise that resolves to a string, representing source code of web resource, which is opened in the
     * currently active browser tab (view-source action)
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     *
     * @return PromiseInterface<string>
     */
    public function getSource(string $sessionIdentifier): PromiseInterface;

    /**
     * Returns a promise that resolves to an array, representing identifier (an internal handle) of the first element
     * on page, found by the given XPath query (currently active browser tab is used).
     *
     * Resulting value example:
     * ```
     * [
     *     'element-6066-11e4-a52e-4f735466cecf' => '1aab1034-814b-4a0b-95cb-94c7c58bbf7a'
     * ]
     * ```
     *
     * Use this handle to perform element-specific actions (e.g. {@link clickElement()} and {@link keypressElement()}).
     *
     * Note: this method behavior equals to "findElement" from the original (blocking) implementation, so if element is
     * not present in the DOM (or a given XPath query is invalid), promise will be rejected with the corresponding
     * RuntimeException as a reason.
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     * @param string $xpathQuery        XPath query to find an element in the DOM tree (e.g. '//input[@type="text"]')
     *
     * @return PromiseInterface<array>
     */
    public function getElementIdentifier(string $sessionIdentifier, string $xpathQuery): PromiseInterface;

    /**
     * Returns a promise that resolves to an array, representing identifier (an internal handle) of the currently
     * active (focused) element on page.
     *
     * Resulting value has the same representation as {@link getElementIdentifier()} resval.
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     *
     * @return PromiseInterface<array>
     */
    public function getActiveElementIdentifier(string $sessionIdentifier): PromiseInterface;

    /**
     * Returns a promise that resolves to a boolean, representing element visibility status on the currently active
     * browser tab.
     *
     * Similar to isDisplayed() method from the original (blocking) HttpCommandExecutor.
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     * @param array  $elementIdentifier An internal WebDriver handle that refers to the element on the page
     *
     * @return PromiseInterface<bool>
     */
    public function getElementVisibility(string $sessionIdentifier, array $elementIdentifier): PromiseInterface;

    /**
     * Returns a promise that will be resolved when the remote WebDriver service confirms element click operation
     * against active browser tab.
     *
     * Note: if target element is a link, it may trigger an implicit tab(s) creation. In such case you need to take
     * into consideration potentially new list of window handles for the current session (see {@link
     * getTabIdentifiers()}). The remote webdriver service WILL NOT automatically perform tab switch operation if
     * a command implicitly triggers new tab.
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     * @param array  $elementIdentifier An internal WebDriver handle that refers to the element on the page
     *
     * @return PromiseInterface<null>
     */
    public function clickElement(string $sessionIdentifier, array $elementIdentifier): PromiseInterface;

    /**
     * Returns a promise that will be resolved when the remote WebDriver service finishes key sequence apply operation
     * to the specified page element on the active browser tab.
     *
     * Note: you can't send a key sequence without targeting a concrete element on the page. The original (blocking)
     * implementation sends descriptor of currently active element as an implicit target for this case.
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     * @param array  $elementIdentifier An internal WebDriver handle that refers to the element on the page
     * @param string $keySequence       Text to type or a sequence of special symbols (e.g. "\xEE\x80\x89" for CTRL)
     *
     * @return PromiseInterface<null>
     *
     * @see https://github.com/php-webdriver/php-webdriver/blob/1.8.3/lib/WebDriverKeys.php#L10
     */
    public function keypressElement(
        string $sessionIdentifier,
        array $elementIdentifier,
        string $keySequence
    ): PromiseInterface;

    /**
     * Returns a promise that will be resolved when the remote WebDriver service finishes mouse moving operation
     * against a specified point on the page.
     *
     * Note 1: if you don't specify {startingPoint}, an active (focused) element will be used implicitly (see
     * {@link getActiveElement()}. If no active element (ex. page just loaded) - {0, 0} will be used. An internal
     * pointer of type "mouse" will PRESERVE its coordinates between page transition on the same tab (see a problem
     * description below).
     *
     * Note 2: if {offsetX} or {offsetY} is too high, you may get an "out of bounds" error, which means target
     * point is not in the browser window. The workaround is to always set a proper starting point (e.g. some element
     * at the top-left corner of the page - if you planning to make some detection-breaking moves) and knowing your
     * boundaries.
     *
     * @param string     $sessionIdentifier Session identifier for Selenium Grid server (hub)
     * @param int        $offsetX           A vertical offset that defines mouse move direction (from top to bottom)
     * @param int        $offsetY           A horizontal offset (from left to right)
     * @param int        $moveDuration      How fast WebDriver will move an internal pointer (original, blocking
     *                                      implementation has a 100 ms time frame dy default)
     * @param array|null $startingPoint     An element identifier (internal WebDriver handle) as a starting point, from
     *                                      which mouse will be moved by the given {offsetX} and {offsetY}
     *
     * @return PromiseInterface<null>
     */
    public function mouseMove(
        string $sessionIdentifier,
        int $offsetX,
        int $offsetY,
        int $moveDuration = 100,
        array $startingPoint = null
    ): PromiseInterface;

    /**
     * Returns a promise that will be resolved when the remote WebDriver service confirms both "pointerDown" and
     * "pointerUp" actions for left button of the virtual mouse (button 0), at the current position of internal pointer
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     *
     * @return PromiseInterface<null>
     */
    public function mouseLeftClick(string $sessionIdentifier): PromiseInterface;

    /**
     * Returns a promise that resolves to a string, raw content of the image, generated by the remote WebDriver service
     * and representing visual state of currently active (focused) browser tab.
     *
     * The resulting image is not automatically saved anywhere and user-side must perform persisting logic (PNG
     * format), if needed.
     *
     * @param string $sessionIdentifier Session identifier for Selenium Grid server (hub)
     *
     * @return PromiseInterface<string>
     */
    public function getScreenshot(string $sessionIdentifier): PromiseInterface;
}
