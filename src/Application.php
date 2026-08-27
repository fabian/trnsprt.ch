<?php

namespace Trnsprt;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\QueryParameterValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class Application implements HttpKernelInterface
{
    private array $agencyColors;
    private ClientInterface $client;
    private bool $debug;
    private UrlGenerator $urlGenerator;
    private UrlMatcher $urlMatcher;
    private RequestContext $requestContext;
    private Controller $controller;
    private ArgumentResolverInterface $argumentResolver;
    private Environment $twig;

    public function __construct(bool $debug = false, ?ClientInterface $client = null)
    {
        $this->agencyColors = require __DIR__.'/agency-colors.php';
        $this->client = $client ?? new Client(['timeout' => 10, 'connect_timeout' => 5]);
        $this->debug = $debug;

        $routes = new AttributeRouteLoader()->load(Controller::class);

        $this->requestContext = new RequestContext();
        $this->urlGenerator = new UrlGenerator($routes, $this->requestContext);
        $this->urlMatcher = new UrlMatcher($routes, $this->requestContext);

        $this->controller = new Controller(app: $this);
        $this->argumentResolver = new ArgumentResolver(argumentValueResolvers: [
            new QueryParameterValueResolver(),
            ...ArgumentResolver::getDefaultArgumentValueResolvers(),
        ]);

        $this->twig = new Environment(new FilesystemLoader(__DIR__.'/../views'), [
            'cache' => __DIR__.'/../cache/twig',
            'auto_reload' => true,
            'debug' => $this->debug,
        ]);
        $this->twig->addFunction(new TwigFunction(
            'path',
            fn (string $name, array $parameters = []): string => $this->urlGenerator->generate($name, $parameters)
        ));

        if ($this->debug) {
            $this->twig->addExtension(new DebugExtension());
        }
    }

    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        try {
            $this->requestContext->fromRequest($request);

            $request->attributes->add($this->urlMatcher->match($request->getPathInfo()));

            $controller = $this->controller->{$request->attributes->get('_method')}(...);
            $arguments = $this->argumentResolver->getArguments($request, $controller);

            return $controller(...$arguments);
        } catch (ResourceNotFoundException|MethodNotAllowedException|NotFoundHttpException) {
            return $this->render('404.html.twig', [], Response::HTTP_NOT_FOUND);
        } catch (RequestExceptionInterface $exception) {
            if ($this->debug || !$catch) {
                throw $exception;
            }

            return $this->render('error.html.twig', [
                'exception' => $exception,
            ], Response::HTTP_BAD_REQUEST);
        } catch (ServerException $exception) {
            if ($this->debug || !$catch) {
                throw $exception;
            }

            return $this->render('error_api.html.twig', [
                'response' => $exception->getResponse(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Throwable $exception) {
            if ($this->debug || !$catch) {
                throw $exception;
            }

            return $this->render('error.html.twig', [
                'exception' => $exception,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAgencyColor(mixed $operator, mixed $category, mixed $number): ?array
    {
        if (isset($this->agencyColors[$operator][$category][$number]['bg'])) {
            return $this->agencyColors[$operator][$category][$number];
        }

        return $this->agencyColors[$operator][$category]['ALL_LINES'] ?? null;
    }

    public function fetch(string $endpoint, array $query): object
    {
        $url = "https://transport.opendata.ch/v1/$endpoint?".http_build_query($query);

        return json_decode((string) $this->client->request('GET', $url)->getBody(), flags: JSON_THROW_ON_ERROR);
    }

    public function render(string $template, array $context, int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render($template, $context), $status);
    }
}
