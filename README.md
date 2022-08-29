# Flysystem adapter for the Microsoft OneDrive API

[![Package version](https://img.shields.io/packagist/v/leapt/flysystem-onedrive.svg?style=flat-square)](https://packagist.org/packages/leapt/flysystem-onedrive)
[![Build Status](https://img.shields.io/github/workflow/status/leapt/flysystem-onedrive/Continuous%20Integration/1.x?style=flat-square)](https://github.com/leapt/flysystem-onedrive/actions?query=workflow%3A%22Continuous+Integration%22)
[![PHP Version](https://img.shields.io/packagist/php-v/leapt/flysystem-onedrive.svg?branch=1.x&style=flat-square)](https://travis-ci.org/leapt/flysystem-onedrive?branch=1.x)
[![License](https://img.shields.io/badge/license-MIT-red.svg?style=flat-square)](LICENSE)
[![Code coverage](https://img.shields.io/codecov/c/github/leapt/flysystem-onedrive?style=flat-square)](https://codecov.io/gh/leapt/flysystem-onedrive/branch/1.x)

This package contains a [Flysystem](https://flysystem.thephpleague.com/) adapter for OneDrive. Under the hood, the [Microsoft Graph SDK](https://github.com/microsoftgraph/msgraph-sdk-php) is used.

## Installation

This package requires PHP 8.1+ and Flysystem v3.

You can install the package using composer:

```bash
composer require leapt/flysystem-onedrive
```

## Usage

The first thing you need to do is get an authorization token for the Microsoft Graph API. 
For that you need to create an app on the [Microsoft Azure Portal](https://docs.microsoft.com/en-us/onedrive/developer/rest-api/getting-started/app-registration?view=odsp-graph-online).

```php
use League\Flysystem\Filesystem;
use Leapt\FlysystemOneDrive\OneDriveAdapter;
use Microsoft\Graph\Graph;

$graph = new Graph();
$graph->setAccessToken('EwBIA8l6BAAU7p9QDpi...');

$adapter = new OneDriveAdapter($graph);
$filesystem = new Filesystem($adapter);

// Or to use the approot endpoint:
$adapter = new OneDriveAdapter($graph, 'special/approot');
```

### Retrieve a bearer token

If you are looking for a way to retrieve a bearer token, here are two examples:

* first example using [Symfony HTTP Client](https://symfony.com/doc/current/http_client.html)
* second example using [Guzzle](https://github.com/guzzle/guzzle), which is already included as a dependency from Microsoft Graph SDK

#### Using Symfony HTTP Client

```php
$tenantId = 'your tenant id';
$clientId = 'your client id';
$clientSecret = 'your client secret';
$scope = 'https://graph.microsoft.com/.default';
$oauthUrl = sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $tenantId);

$client = \Symfony\Component\HttpClient\HttpClient::create();
$response = $client->request('GET', $oauthUrl, ['body' => [
    'client_id'     => $clientId,
    'scope'         => $scope,
    'grant_type'    => 'client_credentials',
    'client_secret' => $clientSecret,
]]);
$bearerToken = $response->toArray()['access_token'];
```

#### Using Guzzle

```php
$tenantId = 'your tenant id';
$clientId = 'your client id';
$clientSecret = 'your client secret';
$scope = 'https://graph.microsoft.com/.default';
$oauthUrl = sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $tenantId);

$client = new \GuzzleHttp\Client();
$response = $client->request('POST', $oauthUrl, ['form_params' => [
    'client_id'     => $clientId,
    'scope'         => $scope,
    'grant_type'    => 'client_credentials',
    'client_secret' => $clientSecret,
]]);
$bearerToken = json_decode((string) $response->getBody(), true)['access_token'];
```

## Changelog

Please see [CHANGELOG](CHANGELOG-1.x.md) for more information what has changed recently.

## Contributing

Feel free to contribute, like sending [pull requests](https://github.com/leapt/flysystem-onedrive/pulls) to add features/tests
or [creating issues](https://github.com/leapt/flysystem-onedrive/issues) :)

Note there are a few helpers to maintain code quality, that you can run using these commands:

```bash
composer cs:dry # Code style check
composer phpstan # Static analysis
vendor/bin/phpunit # Run tests
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

History
-------

This bundle is a maintained fork of the packages [nicolasbeauvais](https://github.com/nicolasbeauvais/flysystem-onedrive) 
and [hevelius](https://github.com/hevelius/flysystem-onedrive).
