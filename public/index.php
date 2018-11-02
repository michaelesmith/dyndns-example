<?php

require __DIR__ . '/../vendor/autoload.php';

use Cache\Adapter\Filesystem\FilesystemCachePool;
use DigitalOceanV2\Adapter\GuzzleHttpAdapter;
use DigitalOceanV2\DigitalOceanV2;
use DynDNSKit\Authenticator\AuthenticatorException;
use DynDNSKit\Authenticator\HttpBasicAuthenticator;
use DynDNSKit\Authenticator\User\RegexUser;
use DynDNSKit\Handler\GenericHandler;
use DynDNSKit\Handler\HandlerException;
use DynDNSKit\Processor\CacheProcessor;
use DynDNSKit\Processor\DigitalOceanApiProcessor;
use DynDNSKit\Processor\JsonProcessor;
use DynDNSKit\Processor\ProcessorException;
use DynDNSKit\Server;
use DynDNSKit\Transformer\DynDNSTransformer;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// If we are behind a reverse proxy or load balancer, we need to tell Symfony so it can determine the client IP properly
//Request::setTrustedProxies(
//    // the IP address (or range) of your proxy
//    ['192.0.0.1', '10.0.0.0/8'],
//
//    // trust *all* "X-Forwarded-*" headers
//    Request::HEADER_X_FORWARDED_ALL
//
//    // or, if your proxy instead uses the "Forwarded" header
//    // Request::HEADER_FORWARDED
//
//    // or, if you're using AWS ELB
//    // Request::HEADER_X_FORWARDED_AWS_ELB
//);

$dotenv = new Dotenv();
$dotenv->load(file_exists($local = __DIR__ . '/../.env') ? $local : __DIR__ . '/../.env.dist');

$server = new Server([
    new GenericHandler(
        new DynDNSTransformer(),
        new HttpBasicAuthenticator([new RegexUser(getenv('USERNAME'), getenv('PASSWORD'), '.+')]),
        new JsonProcessor(__DIR__ . '/var/dns.json')
    ),
]);

// More advanced example with a cache handler and Digital Ocean integartion
//$server = new Server([
//    new GenericHandler(
//        new DynDNSTransformer(),
//        new HttpBasicAuthenticator([new RegexUser(getenv('USERNAME'), getenv('PASSWORD'), '.+')]),
//        new CacheProcessor(
//            new DigitalOceanApiProcessor(['example.com'], new DigitalOceanV2(new GuzzleHttpAdapter(getenv('TOKEN')))),
//            new FilesystemCachePool(new Filesystem(new Local(__DIR__ . '/../var/')))
//        )
//    ),
//]);

try {
    $server->execute(Request::createFromGlobals());

    echo 'Success' . PHP_EOL;
} catch (HandlerException $e) {
    if (getenv('APP_DEBUG')) {
        while ($e) {
            echo $e->getMessage() . PHP_EOL;
            $e = $e->getPrevious();
        }
    } else {
        if ($e->getPrevious() instanceof AuthenticatorException) {
            http_response_code(Response::HTTP_FORBIDDEN);
            echo 'Your user name or password is not valid' . PHP_EOL;
        } elseif ($e->getPrevious() instanceof ProcessorException) {
            http_response_code(Response::HTTP_BAD_REQUEST);
            echo 'Your request is not valid' . PHP_EOL;
        } else {
            http_response_code(Response::HTTP_INTERNAL_SERVER_ERROR);
            echo 'Something went wrong' . PHP_EOL;
        }
    }
}
