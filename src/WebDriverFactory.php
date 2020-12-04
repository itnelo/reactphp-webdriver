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

use Itnelo\React\WebDriver\Client\W3CClient;
use Itnelo\React\WebDriver\Timeout\Interceptor as TimeoutInterceptor;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Socket\Connector as SocketConnector;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as ConfigurationExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Builds web driver instances by the given configuration settings
 */
final class WebDriverFactory
{
    /**
     * Creates and returns a new web driver instance.
     *
     * Usage example:
     *
     * ```
     * $loop = \React\EventLoop\Factory::create();
     *
     * $webDriver = \Itnelo\React\WebDriver\WebDriverFactory::create(
     *     $loop,
     *     [
     *         'browser' => [
     *             'tcp' => [
     *                 'bindto' => '192.168.56.10:0',
     *             ],
     *             'tls' => [
     *                 'verify_peer' => false,
     *                 'verify_peer_name' => false,
     *             ],
     *         ],
     *         'hub' => [
     *             'host' => 'selenium-hub',
     *             'port' => 4444,
     *         ],
     *         'command' => [
     *             'timeout' => 30,
     *         ],
     *     ]
     * );
     * ```
     *
     * For all available "browser" options see \React\Socket\Connector class (will be instantiated for the underlying
     * browser) and socket context options: https://php.net/manual/en/context.socket.php.
     *
     * The "command.timeout" option here doesn't correlate with ReactPHP Browser's timeouts and will just cancel a
     * pending promise after the specified time (in seconds). Furthermore, the hub client can reject promise with a
     * runtime exception if an underlying browser has decided to stop waiting for the response by its own settings.
     *
     * @param LoopInterface $loop    The event loop reference to create underlying components
     * @param array         $options An array of configuration settings for the driver
     *
     * @return WebDriverInterface
     *
     * @throws ConfigurationExceptionInterface Whenever an error has been occurred during driver instantiation
     */
    public static function create(LoopInterface $loop, array $options): WebDriverInterface
    {
        $optionsResolver = new OptionsResolver();

        $optionsResolver
            ->define('browser')
            ->info('Options to customize a socket connector, which will be used by the internal http client')
            ->default(
                function (OptionsResolver $browserOptionsResolver) {
                    $browserOptionsResolver->setDefined(['tcp', 'tls', 'unix', 'dns', 'timeout', 'happy_eyeballs']);
                }
            )
        ;

        $optionsResolver
            ->define('hub')
            ->info('Options to create a hub client that will send commands to the Selenium Grid endpoint')
            ->default(
                function (OptionsResolver $hubOptionsResolver) {
                    $hubOptionsResolver->setDefined(['host', 'port']);
                }
            )
        ;

        $optionsResolver
            ->define('command')
            ->info('Options to control behavior of the commands, which will be executed on the remote server')
            ->default(
                function (OptionsResolver $commandOptionsResolver) {
                    $commandOptionsResolver
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

        $optionsResolved = $optionsResolver->resolve($options);

        $socketConnector = new SocketConnector($loop, $optionsResolved['browser']);
        $httpClient      = new Browser($loop, $socketConnector);

        // Selenium hub sends some valid responses with 5xx status codes, so we need to disable eager promise rejection
        // to properly parse error message and other details from the body.
        $httpClient = $httpClient->withRejectErrorResponse(false);

        $hubClient          = new W3CClient($httpClient, ['server' => $optionsResolved['hub']]);
        $timeoutInterceptor = new TimeoutInterceptor($loop, $optionsResolved['command']['timeout']);

        $webDriverOptions = [];
        $webDriver        = new SeleniumHubDriver($loop, $hubClient, $timeoutInterceptor, $webDriverOptions);

        return $webDriver;
    }
}
