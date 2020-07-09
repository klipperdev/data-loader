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

use Doctrine\ORM\QueryBuilder;
use Klipper\Component\Security\Model\Traits\OrganizationalInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
abstract class BaseUniqueSystemNameableEntityLoader extends BaseUniqueNameableEntityLoader
{
    protected function createExistingEntitiesQueryBuilder(): QueryBuilder
    {
        $qb = parent::createExistingEntitiesQueryBuilder();

        if (!is_a($this->domain->getClass(), OrganizationalInterface::class, true)) {
            $qb->andWhere($qb->getRootAliases()[0].'.organization is null');
        }

        return $qb;
    }
}
