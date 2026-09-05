<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Casts;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

/**
 * @implements CastsAttributes<BigDecimal, BigNumber|float|int|string>
 */
class BigDecimalCast implements CastsAttributes, ComparesCastableAttributes
{
    /**
     * Decimal scale.
     *
     * @var int<0, max>
     */
    protected int $scale;

    /**
     * Rounding mode for cast.
     */
    protected RoundingMode $roundingMode;

    /**
     * Create a new cast class instance.
     */
    public function __construct(int $scale = 0, string $roundingMode = RoundingMode::HalfUp->name)
    {
        if ($scale < 0) {
            throw new LogicException(static::class . ' requires a non-negative scale.');
        }

        $this->scale = $scale;

        try {
            $this->roundingMode = Typer::assertInstance(\constant(RoundingMode::class . "::{$roundingMode}"), RoundingMode::class);
        } catch (Throwable $th) {
            throw new LogicException(static::class . " has invalid rounding mode parameter on model: {$roundingMode}, please fix it.");
        }
    }

    /**
     * Specify the scale and rounding mode for the cast.
     *
     * @param int<0, max> $scale
     */
    public static function using(int $scale, RoundingMode $roundingMode = RoundingMode::HalfUp): string
    {
        return static::class . ':' . \implode(',', [(string) $scale, $roundingMode->name]);
    }

    /**
     * Convert the value from cents to currency with 2 decimals.
     *
     * @param Model $model
     * @param array<mixed> $attributes
     */
    public function get(mixed $model, string $key, mixed $value, array $attributes): BigDecimal|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof BigNumber && !\is_string($value) && !\is_int($value)) {
            throw new LogicException(static::class . ' try to get value from unsupported value type: ' . \gettype($value));
        }

        if (!$value instanceof BigNumber) {
            $value = BigDecimal::of($value);
        }

        return $value->toScale($this->scale, $this->roundingMode);
    }

    /**
     * Parse value and use specified scale.
     *
     * @param Model $model
     * @param array<mixed> $attributes
     */
    public function set(mixed $model, string $key, mixed $value, array $attributes): BigDecimal|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof BigNumber && !\is_string($value) && !\is_int($value)) {
            throw new LogicException(static::class . ' try to set value by unsupported value type: ' . \gettype($value));
        }

        if (!$value instanceof BigNumber) {
            $value = BigDecimal::of($value);
        }

        return $value->toScale($this->scale, $this->roundingMode);
    }

    /**
     * Determine if the given values are equal.
     */
    public function compare(
        Model $model,
        string $key,
        mixed $firstValue,
        mixed $secondValue,
    ): bool {
        if ($firstValue === null || $secondValue === null) {
            return $firstValue === $secondValue;
        }

        if (!$firstValue instanceof BigNumber) {
            if (!\is_string($firstValue) && !\is_int($firstValue) && !\is_float($firstValue)) {
                throw new LogicException(static::class . ' try to compare unsupported value type: ' . \gettype($firstValue));
            }

            $firstValue = BigDecimal::of(\is_float($firstValue) ? (string) $firstValue : $firstValue);
        }

        $firstValue = $firstValue->toScale($this->scale, $this->roundingMode);

        if (!$secondValue instanceof BigNumber) {
            if (!\is_string($secondValue) && !\is_int($secondValue) && !\is_float($secondValue)) {
                throw new LogicException(static::class . ' try to compare unsupported value type: ' . \gettype($secondValue));
            }

            $secondValue = BigDecimal::of(\is_float($secondValue) ? (string) $secondValue : $secondValue);
        }

        $secondValue = $secondValue->toScale($this->scale, $this->roundingMode);

        return $firstValue->isEqualTo($secondValue);
    }
}
