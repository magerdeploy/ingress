<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Async\Acme;

use AcmePhp\Core\AcmeClientInterface;
use AcmePhp\Core\Http\Base64SafeEncoder;
use AcmePhp\Core\Http\SecureHttpClientFactory;
use AcmePhp\Core\Http\ServerErrorHandler;
use AcmePhp\Core\Protocol\ExternalAccount;
use AcmePhp\Ssl\KeyPair;
use AcmePhp\Ssl\Parser\KeyParser;
use AcmePhp\Ssl\Signer\DataSigner;
use GuzzleHttp\ClientInterface;
use Psr\Container\ContainerInterface;

final class ClientFactory
{
    public static function create(ContainerInterface $c): AcmeClientInterface
    {
        $options = $c->get('acme.options');
        $factory = new SecureHttpClientFactory(
            $c->get(ClientInterface::class),
            new Base64SafeEncoder(),
            new KeyParser(),
            new DataSigner(),
            new ServerErrorHandler()
        );
        $httpClient = $factory->createSecureHttpClient($c->get(KeyPair::class));
        $acme = new Client($httpClient, $options['directory_url']);
        $externalAccount = null;
        if (null !== $options['external_account']['id'] && null !== $options['external_account']['key']) {
            $externalAccount = new ExternalAccount($options['external_account']['id'], $options['external_account']['key']);
        }

        $acme->registerAccount($options['email'], $externalAccount);

        return $acme;
    }
}
