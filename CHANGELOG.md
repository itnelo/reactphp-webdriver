# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Changed

- No changes yet.

## [0.1.0] (core design) - 2020-12-02
### Added

- `WebDriverInterface` and low-level `ClientInterface` to communicate with
[Selenium Grid](https://www.selenium.dev/documentation/en/grid) server asynchronously, using the centralized
[event loop](https://github.com/reactphp/event-loop) and [promise API](https://github.com/reactphp/promise).
- `SeleniumHubDriver` and `Client\W3Client` stubs for W3C compliant webdriver implementation.
- `WebDriverFactory` as a shortcut for driver instantiation.
- `Timeout\Interceptor` to prevent unresolved (hanging) driver promises, whenever it fails
(using [reactphp/promise-timer](https://github.com/reactphp/promise-timer)).
- `ClientInterface::createSession()` method implementation (opening Selenium hub session to interact with remote
browser instance).

This early development version doesn't yet contain full implementation for the introduced `WebDriverInterface`, only
core design solutions and library interfaces are defined.

[Unreleased]: https://github.com/itnelo/reactphp-webdriver/compare/0.1.0...0.x
[0.2.0]: https://github.com/itnelo/reactphp-webdriver/compare/0.1.0..0.2.0
[0.1.0]: https://github.com/itnelo/reactphp-webdriver/releases/tag/0.1.0