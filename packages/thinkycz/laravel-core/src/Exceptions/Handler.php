<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as IlluminateHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Trans;
use Throwable;

class Handler extends IlluminateHandler
{
    /**
     * @inheritDoc
     */
    protected $dontFlash = ['current_password', 'current_password_confirmation', 'password', 'password_confirmation', 'new_password', 'new_password_confirmation'];

    /**
     * Convert throwable to HTTP exception.
     */
    public function httpException(int $status, Throwable $exception): HttpExceptionInterface
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception;
        }

        $status = match (true) {
            $exception instanceof AuthorizationException => $exception->status() ?? 403,
            $exception instanceof TokenMismatchException => 419,
            $exception instanceof SuspiciousOperationException => 404,
            default => $status,
        };

        $code = $exception->getCode();

        return new SymfonyHttpException($status, $exception->getMessage(), $exception, [], \is_int($code) ? $code : 0);
    }

    /**
     * @inheritDoc
     */
    protected function prepareException(Throwable $e): Throwable
    {
        return $e;
    }

    /**
     * @inheritDoc
     */
    protected function unauthenticated(mixed $request, AuthenticationException $exception): JsonResponse|RedirectResponse|Response
    {
        $httpException = $this->httpException(401, $exception);

        if ($this->shouldReturnJson($request, $exception)) {
            return $this->jsonResponse($request, $exception, $httpException);
        }

        if ($this->debug()) {
            return $this->symfony($request, $exception, $httpException);
        }

        return $this->thinkycz($request, $exception, $httpException);
    }

    /**
     * @inheritDoc
     */
    protected function invalidJson(mixed $request, ValidationException $exception): JsonResponse
    {
        $httpException = $this->httpException($exception->status, $exception);

        return $this->jsonResponse($request, $exception, $httpException, [
            'errors' => $exception->errors(),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function invalid(mixed $request, ValidationException $exception): JsonResponse|RedirectResponse|Response
    {
        return parent::invalid($request, $exception);
    }

    /**
     * @inheritDoc
     */
    protected function prepareResponse(mixed $request, Throwable $e): RedirectResponse|Response
    {
        $httpException = $this->httpException(500, $e);

        if ($this->debug()) {
            return $this->symfony($request, $e, $httpException);
        }

        return $this->thinkycz($request, $e, $httpException);
    }

    /**
     * @inheritDoc
     */
    protected function prepareJsonResponse(mixed $request, Throwable $e): JsonResponse
    {
        $httpException = $this->httpException(500, $e);

        return $this->jsonResponse($request, $e, $httpException);
    }

    /**
     * Encode json response.
     *
     * @param array<mixed> $data
     */
    protected function jsonResponse(Request $request, Throwable $exception, HttpExceptionInterface $httpException, array $data = []): JsonResponse
    {
        $json = [];

        $status = $httpException->getStatusCode();

        if ($this->debug()) {
            if (!$exception instanceof ValidationException) {
                $json = $this->convertExceptionToArray($exception);

                unset($json['message']);
            }

            $json['internal'] = $exception->getMessage() === '' ? SymfonyResponse::$statusTexts[$status] ?? (string) $status : $exception->getMessage();
        }

        $json['code'] = $httpException->getCode();

        return new JsonResponse(\array_replace($json, $data), $httpException->getStatusCode(), $httpException->getHeaders());
    }

    /**
     * @inheritDoc
     */
    protected function shouldReturnJson(mixed $request, Throwable $e): bool
    {
        return parent::shouldReturnJson($request, $e) || $request->getRequestFormat() === 'json';
    }

    /**
     * Symfony response.
     */
    protected function symfony(Request $request, Throwable $exception, HttpExceptionInterface $httpException): RedirectResponse|Response
    {
        $response = new SymfonyResponse($this->renderExceptionContent($exception), $httpException->getStatusCode(), $httpException->getHeaders());

        return $this->toIlluminateResponse($response, $exception)->prepare($request);
    }

    /**
     * Send view response.
     */
    protected function thinkycz(Request $request, Throwable $exception, HttpExceptionInterface $httpException): RedirectResponse|Response
    {
        $data = [
            'title' => Trans::inject()->assertString("thinkycz::statuses.{$httpException->getStatusCode()}"),
            'status' => $httpException->getStatusCode(),
            'code' => $httpException->getCode(),
        ];

        $response = Resolver::resolveResponseFactory()->view('thinkycz::error', $data, $httpException->getStatusCode(), $httpException->getHeaders());

        return $this->toIlluminateResponse($response, $exception)->prepare($request);
    }

    /**
     * Debug mode enabled.
     */
    protected function debug(): bool
    {
        return Config::inject()->appDebug();
    }
}
