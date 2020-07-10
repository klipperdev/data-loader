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
class DataLoaderConfigUtil
{
    public static function findOptionalFields(array $item): array
    {
        $value = [];

        foreach ($item as $field => $val) {
            $newField = ltrim($field, '?');

            if (0 === strpos($field, '?')) {
                $value['@optionalFields'][] = $newField;
            }

            if (\is_array($val) && isset($val['values']) && \is_array($val['values'])) {
                $val['values'] = static::findOptionalFields($val['values']);
            }

            $value[$newField] = $val;
        }

        return $value;
    }
}
