<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Component\DataLoader\Entity;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ArrayUniqueNameableEntityLoader extends BaseUniqueNameableEntityLoader
{
    public function supports($resource): bool
    {
        return \is_array($resource) && !empty($resource);
    }

    protected function loadContent($resource): array
    {
        return (array) $resource;
    }
}
