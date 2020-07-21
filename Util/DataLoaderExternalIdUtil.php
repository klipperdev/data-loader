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

use Klipper\Component\DoctrineExtensions\Util\SqlFilterUtil;
use Klipper\Component\Model\Traits\ExternalableInterface;
use Klipper\Component\Resource\Domain\DomainInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class DataLoaderExternalIdUtil
{
    /**
     * @return ExternalableInterface[] The map of crm object ids and entity instances
     */
    public static function findBy(
        DomainInterface $domain,
        array $sourceData,
        string $externalIdName,
        array $sourceAssociationFieldNames
    ): array {
        $filters = SqlFilterUtil::disableFilters($domain->getObjectManager(), [], true);
        $entityIds = [];
        $values = [];

        foreach ($sourceData as $item) {
            foreach ($sourceAssociationFieldNames as $field) {
                if (isset($item[$field])) {
                    $entityIds[] = $item[$field];
                }
            }
        }

        /** @var ExternalableInterface[] $res */
        $res = $domain->createQueryBuilder('u')
            ->where('JSON_GET(u.externalIds, \''.$externalIdName.'\') in (:ids)')
            ->setParameter('ids', array_unique($entityIds))
            ->getQuery()
            ->getResult()
        ;
        SqlFilterUtil::enableFilters($domain->getObjectManager(), $filters);

        foreach ($res as $entity) {
            $values[$entity->getExternalId($externalIdName)] = $entity;
        }

        return $values;
    }
}
