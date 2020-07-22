<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Component\DataLoader\Util;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class DataUtil
{
    /**
     * @param null|mixed $default The default value
     *
     * @return null|mixed
     */
    public static function getValue(array $data, string $property, $default = null)
    {
        return \array_key_exists($property, $data) ? $data[$property] : $default;
    }

    public static function getString(array $data, string $property, ?string $default = null): ?string
    {
        $value = static::getValue($data, $property);

        return null !== $value ? (string) $value : $default;
    }

    public static function getBool(array $data, string $property, ?bool $default = null): ?bool
    {
        $value = static::getValue($data, $property);

        return null !== $value ? (bool) $value : $default;
    }

    public static function getInt(array $data, string $property, ?int $default = null): ?int
    {
        $value = static::getValue($data, $property);

        return null !== $value ? (int) $value : $default;
    }

    public static function getFloat(array $data, string $property, ?float $default = null): ?float
    {
        $value = static::getValue($data, $property);

        return null !== $value ? (float) $value : $default;
    }

    public static function getPercent(array $data, string $property, ?float $default = null): ?float
    {
        $value = static::getFloat($data, $property);

        return null !== $value ? (float) ($value / 100) : $default;
    }

    public static function getDateTime(array $data, string $property, string $timezone = 'UTC', ?\DateTimeInterface $default = null): ?\DateTimeInterface
    {
        $value = static::getValue($data, $property);

        return null !== $value
            ? new \DateTime($value, new \DateTimeZone($timezone))
            : $default;
    }
}
