<?php

/*
 * This file is part of the Klipper package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Component\DataLoader;

use Klipper\Component\Resource\ResourceListInterface;

/**
 * Data loader interface.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
interface DataLoaderInterface
{
    /**
     * Loads a resource.
     *
     * @param mixed $resource The resource
     */
    public function load($resource): ResourceListInterface;

    /**
     * Returns whether this class supports the given resource.
     *
     * @param mixed $resource A resource
     *
     * @return bool True if this class supports the given resource, false otherwise
     */
    public function supports($resource): bool;
}
