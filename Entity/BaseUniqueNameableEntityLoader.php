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
use Klipper\Component\DataLoader\Exception\InvalidArgumentException;
use Klipper\Component\Resource\Domain\DomainInterface;
use Klipper\Contracts\Model\NameableInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
abstract class BaseUniqueNameableEntityLoader extends BaseUniqueEntityLoader
{
    public function __construct(
        DomainInterface $domain,
        ?QueryBuilder $existingEntitiesQueryBuilder = null,
        UniqueEntityConfiguration $config = null,
        Processor $processor = null,
        string $defaultLocale = 'en',
        PropertyAccessor $accessor = null
    ) {
        if (!is_a($domain->getClass(), NameableInterface::class, true)) {
            throw new InvalidArgumentException(sprintf('The "%s" class must implemented "%s"', $this->domain->getClass(), NameableInterface::class));
        }

        parent::__construct($domain, 'name', null, $existingEntitiesQueryBuilder, $config, $processor, $defaultLocale, $accessor);
    }
}
