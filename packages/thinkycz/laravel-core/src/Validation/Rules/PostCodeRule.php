<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Trans;

class PostCodeRule implements ValidationRule
{
    /**
     * ISO 3166 mapping.
     *
     * @var array<string, string>
     */
    /**
     * Enums.
     *
     * @var array<string, array<int, string>>
     */
    /**
     * Patterns.
     *
     * @var array<string, ?string>
     */
    /**
     * Country code.
     */
    protected string $countryCode;

    /**
     * PostCodeRule constructor.
     */
    public function __construct(string $countryCode, protected bool $validateEnum = true)
    {
        $this->countryCode = \mb_strtoupper($countryCode);
    }

    /**
     * @inheritDoc
     */
    public function validate(mixed $attribute, mixed $value, Closure $fail): void
    {
        $trans = Trans::inject();

        if (!\is_string($value)) {
            $fail($trans->assertString('validation.regex'));

            return;
        }

        if ($this->countryCode === '') {
            return;
        }

        if (\array_key_exists($this->countryCode, PostCodeData::ISO_3166)) {
            $iso2 = PostCodeData::ISO_3166[$this->countryCode];
        } elseif (\in_array($this->countryCode, PostCodeData::ISO_3166, true)) {
            $iso2 = $this->countryCode;
        } else {
            $fail($trans->assertString('validation.regex'));

            return;
        }

        if (Config::inject()->appEnvIs(['testing'])) {
            return;
        }

        $pattern = PostCodeData::PATTERNS[$iso2];

        if ($pattern === null) {
            return;
        }

        if (\preg_match($pattern, $value) !== 1) {
            $fail($trans->assertString('validation.regex'));

            return;
        }

        if ($this->validateEnum && \array_key_exists($iso2, PostCodeData::ENUMS) && !\in_array($value, PostCodeData::ENUMS[$iso2], true)) {
            $fail($trans->assertString('validation.regex'));
        }
    }
}
