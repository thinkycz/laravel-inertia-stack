<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Support;

use BackedEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use ReflectionEnum;
use stdClass;

class Typer
{
    /**
     * Assert string.
     */
    public static function assertString(mixed $value): string
    {
        \assert(\is_string($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert nullable string.
     */
    public static function assertNullableString(mixed $value): string|null
    {
        \assert($value === null || \is_string($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert bool.
     */
    public static function assertBool(mixed $value): bool
    {
        \assert(\is_bool($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert nullable bool.
     */
    public static function assertNullableBool(mixed $value): bool|null
    {
        \assert($value === null || \is_bool($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert int.
     */
    public static function assertInt(mixed $value): int
    {
        \assert(\is_int($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert nullable int.
     */
    public static function assertNullableInt(mixed $value): int|null
    {
        \assert($value === null || \is_int($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert float.
     */
    public static function assertFloat(mixed $value): float
    {
        \assert(\is_float($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert nullable float.
     */
    public static function assertNullableFloat(mixed $value): float|null
    {
        \assert($value === null || \is_float($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert array.
     *
     * @return array<mixed>
     */
    public static function assertArray(mixed $value): array
    {
        \assert(\is_array($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert string-key array.
     *
     * @param array<array-key, mixed> $value
     *
     * @return array<string, mixed>
     */
    public static function assertStringKeyArray(array $value): array
    {
        $result = [];

        foreach (\array_keys($value) as $key) {
            \assert(\is_string($key), Panicker::message(__METHOD__, 'assertion failed', \compact('value', 'key')));

            $result[$key] = $value[$key];
        }

        return $result;
    }

    /**
     * Assert string array.
     *
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, string>
     */
    public static function assertStringArray(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            \assert(\is_string($item), Panicker::message(__METHOD__, 'assertion failed', \compact('value', 'key', 'item')));

            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * Assert nullable array.
     *
     * @return array<mixed>|null
     */
    public static function assertNullableArray(mixed $value): array|null
    {
        \assert($value === null || \is_array($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert file.
     */
    public static function assertFile(mixed $value): UploadedFile
    {
        \assert($value instanceof UploadedFile, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert nullable file.
     */
    public static function assertNullableFile(mixed $value): UploadedFile|null
    {
        \assert($value === null || $value instanceof UploadedFile, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert carbon.
     */
    public static function assertCarbon(mixed $value): Carbon
    {
        \assert($value instanceof Carbon, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert nullable carbon.
     */
    public static function assertNullableCarbon(mixed $value): Carbon|null
    {
        \assert($value === null || $value instanceof Carbon, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert object.
     */
    public static function assertObject(mixed $value): object
    {
        \assert(\is_object($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert nullable object.
     */
    public static function assertNullableObject(mixed $value): object|null
    {
        \assert($value === null || \is_object($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert scalar.
     *
     * @return scalar
     */
    public static function assertScalar(mixed $value): bool|float|int|string
    {
        \assert(\is_scalar($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert nullable scalar.
     *
     * @return scalar|null
     */
    public static function assertNullableScalar(mixed $value): bool|float|int|string|null
    {
        \assert($value === null || \is_scalar($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public static function assertEnum(mixed $value, string $enum): BackedEnum
    {
        if ($value instanceof $enum) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('value', 'enum'));
    }

    /**
     * Assert nullable enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    public static function assertNullableEnum(mixed $value, string $enum): BackedEnum|null
    {
        if ($value === null || $value instanceof $enum) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('value', 'enum'));
    }

    /**
     * Assert instance.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public static function assertInstance(mixed $value, string $class): object
    {
        if ($value instanceof $class) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('value', 'class'));
    }

    /**
     * Assert nullable instance.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public static function assertNullableInstance(mixed $value, string $class): object|null
    {
        if ($value === null || $value instanceof $class) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('value', 'class'));
    }

    /**
     * Assert a.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return class-string<T>
     */
    public static function assertA(mixed $value, string $class): string
    {
        if (\is_string($value) && \is_a($value, $class, true)) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('value', 'class'));
    }

    /**
     * Assert nullable a.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return class-string<T>|null
     */
    public static function assertNullableA(mixed $value, string $class): string|null
    {
        if ($value === null) {
            return $value;
        }

        if (\is_string($value) && \is_a($value, $class, true)) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('value', 'class'));
    }

    /**
     * Parse string.
     */
    public static function parseString(mixed $value): string
    {
        return self::parseNullableString($value) ?? '';
    }

    /**
     * Parse nullable string.
     */
    public static function parseNullableString(mixed $value): string|null
    {
        if ($value === null || \is_string($value)) {
            return $value;
        }

        $value = \filter_var($value);

        if ($value === false) {
            return null;
        }

        return $value;
    }

    /**
     * Must parse string.
     */
    public static function mustParseString(mixed $value): string
    {
        $value = self::mustParseNullableString($value);

        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Must parse nullable string.
     */
    public static function mustParseNullableString(mixed $value): string|null
    {
        if ($value === null || \is_string($value)) {
            return $value;
        }

        $value = \filter_var($value);

        \assert($value !== false, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Parse bool.
     */
    public static function parseBool(mixed $value): bool
    {
        return self::parseNullableBool($value) ?? false;
    }

    /**
     * Parse nullable bool.
     */
    public static function parseNullableBool(mixed $value): bool|null
    {
        if ($value === null || \is_bool($value)) {
            return $value;
        }

        return \filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE);
    }

    /**
     * Must parse bool.
     */
    public static function mustParseBool(mixed $value): bool
    {
        $value = self::mustParseNullableBool($value);

        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Must parse nullable bool.
     */
    public static function mustParseNullableBool(mixed $value): bool|null
    {
        if ($value === null || \is_bool($value)) {
            return $value;
        }

        $value = \filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE);

        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Parse int.
     */
    public static function parseInt(mixed $value): int
    {
        return self::parseNullableInt($value) ?? 0;
    }

    /**
     * Parse nullable int.
     */
    public static function parseNullableInt(mixed $value): int|null
    {
        if ($value === null || \is_int($value)) {
            return $value;
        }

        $value = \filter_var($value, \FILTER_VALIDATE_INT);

        if ($value === false) {
            return null;
        }

        return $value;
    }

    /**
     * Must parse int.
     */
    public static function mustParseInt(mixed $value): int
    {
        $value = self::mustParseNullableInt($value);

        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Must parse nullable int.
     */
    public static function mustParseNullableInt(mixed $value): int|null
    {
        if ($value === null || \is_int($value)) {
            return $value;
        }

        $value = \filter_var($value, \FILTER_VALIDATE_INT);

        \assert($value !== false, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Parse float.
     */
    public static function parseFloat(mixed $value): float
    {
        return self::parseNullableFloat($value) ?? 0.0;
    }

    /**
     * Parse nullable float.
     */
    public static function parseNullableFloat(mixed $value): float|null
    {
        if ($value === null || \is_float($value)) {
            return $value;
        }

        $value = \filter_var($value, \FILTER_VALIDATE_FLOAT);

        if ($value === false) {
            return null;
        }

        return $value;
    }

    /**
     * Must parse float.
     */
    public static function mustParseFloat(mixed $value): float
    {
        $value = self::mustParseNullableFloat($value);

        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Must parse nullable float.
     */
    public static function mustParseNullableFloat(mixed $value): float|null
    {
        if ($value === null || \is_float($value)) {
            return $value;
        }

        $value = \filter_var($value, \FILTER_VALIDATE_FLOAT);

        \assert($value !== false, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Parse array.
     *
     * @return array<mixed>
     */
    public static function parseArray(mixed $value): array
    {
        return self::parseNullableArray($value) ?? [];
    }

    /**
     * Parse nullable array.
     *
     * @return array<mixed>|null
     */
    public static function parseNullableArray(mixed $value): array|null
    {
        if ($value === null || \is_array($value)) {
            return $value;
        }

        if (\is_object($value)) {
            return \get_object_vars($value);
        }

        return null;
    }

    /**
     * Must parse array.
     *
     * @return array<mixed>
     */
    public static function mustParseArray(mixed $value): array
    {
        $value = self::mustParseNullableArray($value);

        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Must parse nullable array.
     *
     * @return array<mixed>|null
     */
    public static function mustParseNullableArray(mixed $value): array|null
    {
        if (\is_object($value)) {
            return \get_object_vars($value);
        }

        \assert($value === null || \is_array($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Parse file.
     */
    public static function parseFile(mixed $value): UploadedFile
    {
        return self::parseNullableFile($value) ?? UploadedFile::createFromBase(UploadedFile::fake()->create(self::assertString(\tempnam(\sys_get_temp_dir(), 'php'))));
    }

    /**
     * Parse nullable file.
     */
    public static function parseNullableFile(mixed $value): UploadedFile|null
    {
        if ($value === null || $value instanceof UploadedFile) {
            return $value;
        }

        return null;
    }

    /**
     * Must parse file.
     */
    public static function mustParseFile(mixed $value): UploadedFile
    {
        return self::assertFile($value);
    }

    /**
     * Must parse nullable file.
     */
    public static function mustParseNullableFile(mixed $value): UploadedFile|null
    {
        return self::assertNullableFile($value);
    }

    /**
     * Parse carbon.
     */
    public static function parseCarbon(mixed $value, string|null $format = null, string|null $tz = null): Carbon
    {
        return self::parseNullableCarbon($value, $format, $tz) ?? Carbon::now();
    }

    /**
     * Parse nullable carbon.
     */
    public static function parseNullableCarbon(mixed $value, string|null $format = null, string|null $tz = null): Carbon|null
    {
        if ($value === null || $value instanceof Carbon) {
            return $value;
        }

        $value = \filter_var($value);

        if ($value === false || $value === '') {
            return null;
        }

        if ($format === null) {
            return Carbon::parse($value, $tz)->setTimezone(Config::inject()->appTimezone());
        }

        $value = Carbon::createFromFormat($format, $value, $tz);

        return $value?->setTimezone(Config::inject()->appTimezone());
    }

    /**
     * Must parse carbon.
     */
    public static function mustParseCarbon(mixed $value, string|null $format = null, string|null $tz = null): Carbon
    {
        $value = self::mustParseNullableCarbon($value, $format, $tz);

        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Must parse nullable carbon.
     */
    public static function mustParseNullableCarbon(mixed $value, string|null $format = null, string|null $tz = null): Carbon|null
    {
        if ($value === null || $value instanceof Carbon) {
            return $value;
        }

        $value = \filter_var($value);

        \assert($value !== false && $value !== '', Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        if ($format === null) {
            return Carbon::parse($value, $tz)->setTimezone(Config::inject()->appTimezone());
        }

        $value = Carbon::createFromFormat($format, $value, $tz);

        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value->setTimezone(Config::inject()->appTimezone());
    }

    /**
     * Parse object.
     */
    public static function parseObject(mixed $value): object
    {
        return self::parseNullableObject($value) ?? new stdClass();
    }

    /**
     * Parse nullable object.
     */
    public static function parseNullableObject(mixed $value): object|null
    {
        if ($value === null || \is_object($value)) {
            return $value;
        }

        if (\is_array($value)) {
            return (object) $value;
        }

        return null;
    }

    /**
     * Must parse object.
     */
    public static function mustParseObject(mixed $value): object
    {
        $value = self::mustParseNullableObject($value);

        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Must parse nullable object.
     */
    public static function mustParseNullableObject(mixed $value): object|null
    {
        if (\is_array($value)) {
            return (object) $value;
        }

        \assert($value === null || \is_object($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Parse scalar.
     */
    public static function parseScalar(mixed $value): bool|float|int|string
    {
        return self::parseNullableScalar($value) ?? '';
    }

    /**
     * Parse nullable scalar.
     */
    public static function parseNullableScalar(mixed $value): bool|float|int|string|null
    {
        if ($value === null || \is_scalar($value)) {
            return $value;
        }

        return null;
    }

    /**
     * Must parse scalar.
     */
    public static function mustParseScalar(mixed $value): bool|float|int|string
    {
        $value = self::mustParseNullableScalar($value);

        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Must parse nullable scalar.
     */
    public static function mustParseNullableScalar(mixed $value): bool|float|int|string|null
    {
        \assert($value === null || \is_scalar($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Parse enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public static function parseEnum(mixed $value, string $enum): BackedEnum
    {
        return self::parseNullableEnum($value, $enum) ?? $enum::cases()[0];
    }

    /**
     * Parse nullable enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    public static function parseNullableEnum(mixed $value, string $enum): BackedEnum|null
    {
        if ((string) (new ReflectionEnum($enum))->getBackingType() === 'int') {
            $value = self::parseNullableInt($value);
        } else {
            $value = self::parseNullableString($value);
        }

        if ($value === null) {
            return $value;
        }

        return $enum::tryFrom($value);
    }

    /**
     * Must parse enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public static function mustParseEnum(mixed $value, string $enum): BackedEnum
    {
        $value = self::mustParseNullableEnum($value, $enum);

        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Must parse nullable enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    public static function mustParseNullableEnum(mixed $value, string $enum): BackedEnum|null
    {
        if ((string) (new ReflectionEnum($enum))->getBackingType() === 'int') {
            $value = self::mustParseNullableInt($value);
        } else {
            $value = self::mustParseNullableString($value);
        }

        if ($value === null) {
            return $value;
        }

        return $enum::from($value);
    }

    /**
     * Assert in.
     *
     * @template T
     *
     * @param T $value
     * @param array<mixed> $enum
     *
     * @return T
     */
    public static function assertIn(mixed $value, array $enum): mixed
    {
        if (\in_array($value, $enum, true)) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('value', 'enum'));
    }

    /**
     * Assert not null.
     *
     * @template T of string|int|float|bool|object|array
     *
     * @param T|null $value
     *
     * @return T
     */
    public static function assertNotNull(mixed $value): array|bool|float|int|object|string
    {
        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }

    /**
     * Assert null.
     */
    public static function assertNull(mixed $value): mixed
    {
        if ($value !== null) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('value'));
        }

        return $value;
    }

    /**
     * Parse int enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public static function parseIntEnum(mixed $value, string $enum): BackedEnum
    {
        return self::parseNullableIntEnum($value, $enum) ?? $enum::cases()[0];
    }

    /**
     * Parse nullable int enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    public static function parseNullableIntEnum(mixed $value, string $enum): BackedEnum|null
    {
        $value = self::parseNullableInt($value);

        if ($value === null) {
            return $value;
        }

        return $enum::tryFrom($value);
    }

    /**
     * Must parse int enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public static function mustParseIntEnum(mixed $value, string $enum): BackedEnum
    {
        $value = self::mustParseNullableIntEnum($value, $enum);

        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value', 'enum')));

        return $value;
    }

    /**
     * Must parse nullable int enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    public static function mustParseNullableIntEnum(mixed $value, string $enum): BackedEnum|null
    {
        $value = self::mustParseNullableInt($value);

        if ($value === null) {
            return $value;
        }

        return $enum::from($value);
    }

    /**
     * Parse string enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public static function parseStringEnum(mixed $value, string $enum): BackedEnum
    {
        return self::parseNullableStringEnum($value, $enum) ?? $enum::cases()[0];
    }

    /**
     * Parse nullable string enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    public static function parseNullableStringEnum(mixed $value, string $enum): BackedEnum|null
    {
        $value = self::parseNullableString($value);

        if ($value === null) {
            return $value;
        }

        return $enum::tryFrom($value);
    }

    /**
     * Must parse string enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public static function mustParseStringEnum(mixed $value, string $enum): BackedEnum
    {
        $value = self::mustParseNullableStringEnum($value, $enum);

        \assert($value !== null, Panicker::message(__METHOD__, 'assertion failed', \compact('value', 'enum')));

        return $value;
    }

    /**
     * Must parse nullable string enum.
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    public static function mustParseNullableStringEnum(mixed $value, string $enum): BackedEnum|null
    {
        $value = self::mustParseNullableString($value);

        if ($value === null) {
            return $value;
        }

        return $enum::from($value);
    }

    /**
     * Assert.
     *
     * @param array<mixed> $args
     */
    public static function assert(bool $value, string $message = 'assertion failed', array $args = []): void
    {
        if ($value !== true) {
            Panicker::panic(__METHOD__, $message, $args);
        }
    }

    /**
     * Assert true.
     *
     * @return true
     */
    public static function assertTrue(mixed $value): bool
    {
        if ($value !== true) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('value'));
        }

        return true;
    }

    /**
     * Assert false.
     *
     * @return false
     */
    public static function assertFalse(mixed $value): bool
    {
        if ($value !== false) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('value'));
        }

        return false;
    }

    /**
     * Assert not true.
     *
     * @template T
     *
     * @param T $value
     *
     * @return T
     */
    public static function assertNotTrue(mixed $value): mixed
    {
        if ($value === true) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('value'));
        }

        return $value;
    }

    /**
     * Assert not false.
     *
     * @template T
     *
     * @param T $value
     *
     * @return T
     */
    public static function assertNotFalse(mixed $value): mixed
    {
        if ($value === false) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('value'));
        }

        return $value;
    }

    /**
     * Assert not empty.
     *
     * @template T of array<mixed>
     *
     * @param T $value
     *
     * @return T
     */
    public static function assertNotEmpty(array $value): array
    {
        if (\count($value) === 0) {
            Panicker::panic(__METHOD__, 'assertion failed', \compact('value'));
        }

        return $value;
    }

    /**
     * Assert empty.
     *
     * @param array<mixed> $value
     *
     * @return array{}
     */
    public static function assertEmpty(array $value): array
    {
        if (\count($value) === 0) {
            return $value;
        }

        Panicker::panic(__METHOD__, 'assertion failed', \compact('value'));
    }

    /**
     * Assert not bool.
     *
     * @template T of string|int|float|object|array|null
     *
     * @param bool|T $value
     *
     * @return T
     */
    public static function assertNotBool(mixed $value): array|float|int|object|string|null
    {
        \assert(!\is_bool($value), Panicker::message(__METHOD__, 'assertion failed', \compact('value')));

        return $value;
    }
}
