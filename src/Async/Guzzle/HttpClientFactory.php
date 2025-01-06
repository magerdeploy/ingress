<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Async\Guzzle;

use Amp\Http\Client\GuzzleAdapter\GuzzleHandlerAdapter;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\HandlerStack;

final class HttpClientFactory
{
    public static function create(): GuzzleHttpClient
    {
        return new GuzzleHttpClient([
            'handler' => HandlerStack::create(new GuzzleHandlerAdapter()),
        ]);
    }
}
