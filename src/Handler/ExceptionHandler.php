<?php

namespace Spark\Handler;

use Exception;
use InvalidArgumentException;
use Negotiation\NegotiatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Relay\ResolverInterface;
use Spark\Exception\HttpException;
use Whoops\Run as Whoops;

class ExceptionHandler
{
    /**
     * @var NegotiatorInterface
     */
    private $negotiator;

    /**
     * @var ExceptionHandlerPreferences
     */
    private $preferences;

    /**
     * @var ResolverInterface
     */
    private $resolver;

    /**
     * @var Whoops
     */
    private $whoops;

    /**
     * @param ExceptionHandlerPreferences $preferences
     * @param NegotiatorInterface $negotiator
     * @param ResolverInterface $resolver
     * @param Whoops $whoops
     */
    public function __construct(
        ExceptionHandlerPreferences $preferences,
        NegotiatorInterface $negotiator,
        ResolverInterface $resolver,
        Whoops $whoops
    ) {
        $this->preferences = $preferences;
        $this->negotiator = $negotiator;
        $this->resolver = $resolver;
        $this->whoops = $whoops;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     *
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next
    ) {
        try {
            return $next($request, $response);
        } catch (Exception $e) {
            $type = $this->type($request);

            $response = $response->withHeader('Content-Type', $type);

            try {
                $response = $response->withStatus($e->getCode());
            } catch (InvalidArgumentException $_) {
                // Exception did not contain a valid code
                $response = $response->withStatus(500);
            }

            if ($e instanceof HttpException) {
                $response = $e->withResponse($response);
            }

            $handler = $this->handler($type);
            $this->whoops->pushHandler($handler);

            $body = $this->whoops->handleException($e);
            $response->getBody()->write($body);

            $this->whoops->popHandler();

            return $response;
        }
    }

    /**
     * Determine the preferred content type for the current request
     *
     * @param ServerRequestInterface $request
     *
     * @return string
     */
    private function type(ServerRequestInterface $request)
    {
        $accept = $request->getHeaderLine('Accept');
        $priorities = $this->preferences->toArray();
        $preferred = $this->negotiator->getBest($accept, array_keys($priorities));

        if ($preferred) {
            return $preferred->getValue();
        }

        return key($priorities);
    }

    /**
     * Retrieve the handler to use for the given type
     *
     * @param string $type
     *
     * @return \Whoops\Handler\HandlerInterface
     */
    private function handler($type)
    {
        return call_user_func($this->resolver, $this->preferences[$type]);
    }
}
