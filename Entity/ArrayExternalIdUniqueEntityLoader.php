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
use Klipper\Component\Resource\Domain\DomainInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class ArrayExternalIdUniqueEntityLoader extends ArrayUniqueEntityLoader
{
    private string $externalService;

    public function __construct(
        DomainInterface $domain,
        string $externalService,
        ?QueryBuilder $existingEntitiesQueryBuilder = null,
        UniqueEntityConfiguration $config = null,
        Processor $processor = null,
        string $defaultLocale = 'en',
        PropertyAccessor $accessor = null
    ) {
        parent::__construct(
            $domain,
            sprintf('externalIds[%s]', $externalService),
            sprintf('[externalIds][%s]', $externalService),
            $existingEntitiesQueryBuilder,
            $config,
            $processor,
            $defaultLocale,
            $accessor
        );

        $this->externalService = $externalService;
    }

    protected function getExistingEntities(array $items): array
    {
        $qb = $this->createExistingEntitiesQueryBuilder();
        $alias = $qb->getRootAliases()[0];
        $qb->andWhere(sprintf(
            'JSON_GET(%s.externalIds, \'%s\') in (:uniqueValues)',
            $alias,
            $this->externalService
        ));
        $qb->setParameter('uniqueValues', $this->getSourceUniqueValues($items));

        return $qb->getQuery()->getResult();
    }
}
