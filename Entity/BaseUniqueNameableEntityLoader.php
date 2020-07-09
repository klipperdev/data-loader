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

use Klipper\Component\DataLoader\Exception\InvalidArgumentException;
use Klipper\Component\Model\Traits\NameableInterface;
use Klipper\Component\Security\Model\Traits\OrganizationalInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
abstract class BaseUniqueNameableEntityLoader extends BaseUniqueEntityLoader
{
    protected function getSourceUniqueValue(array $item): string
    {
        return $item['name'];
    }

    protected function getUniqueValue(object $entity): string
    {
        if (!$entity instanceof NameableInterface) {
            throw new InvalidArgumentException(sprintf('The "%s" class must implemented "%s"', $this->domain->getClass(), NameableInterface::class));
        }

        return $entity->getName();
    }

    protected function getExistingEntities(array $items): array
    {
        if (!\in_array(OrganizationalInterface::class, class_implements($this->domain->getClass()), true)) {
            throw new InvalidArgumentException(sprintf('The "%s" class must implemented "%s"', $this->domain->getClass(), OrganizationalInterface::class));
        }

        /* @var object[] $list */
        return $this->domain->getRepository()->findBy(['organization' => null]);
    }
}
