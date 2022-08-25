## This package is based on the repositories no more maintained by nicolasbeauvais & hevelius.

# Flysystem adapter for the Microsoft OneDrive API

[![Package version](https://img.shields.io/packagist/v/leapt/flysystem-onedrive.svg?style=flat-square)](https://packagist.org/packages/leapt/flysystem-onedrive)
[![Build Status](https://img.shields.io/github/workflow/status/leapt/flysystem-onedrive/Continuous%20Integration/1.x?style=flat-square)](https://github.com/leapt/flysystem-onedrive/actions?query=workflow%3A%22Continuous+Integration%22)
[![PHP Version](https://img.shields.io/packagist/php-v/leapt/flysystem-onedrive.svg?branch=1.x&style=flat-square)](https://travis-ci.org/leapt/flysystem-onedrive?branch=1.x)
[![License](https://img.shields.io/badge/license-MIT-red.svg?style=flat-square)](LICENSE)
[![Code coverage](https://img.shields.io/codecov/c/github/leapt/flysystem-onedrive?style=flat-square)](https://codecov.io/gh/leapt/flysystem-onedrive/branch/1.x)

This package contains a [Flysystem](https://flysystem.thephpleague.com/) adapter for OneDrive. Under the hood, the [Microsoft Graph SDK](https://github.com/microsoftgraph/msgraph-sdk-php) is used.

## Installation

You can install the package via composer:

```bash
composer require leapt/flysystem-onedrive
```

## Usage

The first thing you need to do is get an authorization token for the Microsoft Graph API. For that you need to create an app on the [Microsoft Azure Portal](https://portal.azure.com/).

``` php
use League\Flysystem\Filesystem;
use Leapt\FlysystemOneDrive\OneDriveAdapter;
use Microsoft\Graph\Graph;

$graph = new Graph();
$graph->setAccessToken('EwBIA8l6BAAU7p9QDpi...');

$adapter = new OneDriveAdapter($graph, 'root');
$filesystem = new Filesystem($adapter);

// Or to use the approot endpoint:
$adapter = new OneDriveAdapter($graph, 'special/approot');
```


## Changelog

Please see [CHANGELOG](CHANGELOG-1.x.md) for more information what has changed recently.

## Testing

```bash
vendor/bin/phpunit
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
