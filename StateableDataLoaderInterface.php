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

/**
 * Stateable data loader interface.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
interface StateableDataLoaderInterface extends DataLoaderInterface
{
    /**
     * Check if the new values are loaded.
     */
    public function hasNewValues(): bool;

    /**
     * Check if the values are updated.
     */
    public function hasUpdatedValues(): bool;

    /**
     * Check if new values are loaded or if values are updated.
     */
    public function isEdited(): bool;
}
