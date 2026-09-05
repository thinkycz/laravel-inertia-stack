<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Exceptions;

use Exception;
use Illuminate\Validation\ValidationException as IlluminateValidationException;
use Illuminate\Validation\Validator;
use Throwable;

class ValidationException extends IlluminateValidationException
{
    /**
     * @inheritDoc
     *
     * @param array<mixed> $headers
     * @param array<mixed> $data
     */
    public function __construct(Validator $validator, int $statusCode = 422, string $message = '', Throwable|null $previous = null, public array $headers = [], int $code = 0, public array $data = [])
    {
        parent::__construct($validator);
        $this->response = null;
        $this->status = $statusCode;
        $this->errorBag = 'default';

        Exception::__construct($message, $code, $previous);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
