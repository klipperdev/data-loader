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

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Klipper\Component\DataLoader\DataLoaderInterface;
use Klipper\Component\DataLoader\Exception\InvalidArgumentException;
use Klipper\Component\DataLoader\Exception\RuntimeException;
use Klipper\Component\DataLoader\Util\DataLoaderTranslationUtil;
use Klipper\Component\DoctrineExtensions\Util\SqlFilterUtil;
use Klipper\Component\DoctrineExtensionsExtra\Model\BaseTranslation;
use Klipper\Component\DoctrineExtensionsExtra\Model\Traits\TranslatableInterface;
use Klipper\Component\Resource\Domain\DomainInterface;
use Klipper\Component\Resource\ResourceList;
use Klipper\Component\Resource\ResourceListInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
abstract class BaseUniqueEntityLoader implements DataLoaderInterface
{
    protected DomainInterface $domain;

    protected string $uniquePropertyPath;

    protected string $sourceUniquePropertyPath;

    protected ?QueryBuilder $existingEntitiesQueryBuilder;

    /**
     * @var ClassMetadata|ClassMetadataInfo
     */
    protected ClassMetadata $metadata;

    protected ConfigurationInterface $config;

    protected Processor $processor;

    protected string $defaultLocale;

    protected PropertyAccessor $accessor;

    protected bool $hasNewEntities = false;

    protected bool $hasUpdatedEntities = false;

    /**
     * @param DomainInterface                $domain                       The resource domain of entity
     * @param string                         $uniquePropertyPath           The property path of unique property
     * @param null|string                    $sourceUniquePropertyPath     The property path of source unique property, if null, the unique property path is used
     * @param null|QueryBuilder              $existingEntitiesQueryBuilder The custom query builder to retrieve the existing entities
     * @param null|UniqueEntityConfiguration $config                       The configuration
     * @param Processor                      $processor                    The processor
     * @param string                         $defaultLocale                The default locale
     * @param null|PropertyAccessor          $accessor                     The property accessor
     */
    public function __construct(
        DomainInterface $domain,
        string $uniquePropertyPath,
        ?string $sourceUniquePropertyPath = null,
        ?QueryBuilder $existingEntitiesQueryBuilder = null,
        UniqueEntityConfiguration $config = null,
        Processor $processor = null,
        string $defaultLocale = 'en',
        PropertyAccessor $accessor = null
    ) {
        $this->domain = $domain;
        $this->uniquePropertyPath = $uniquePropertyPath;
        $this->sourceUniquePropertyPath = $sourceUniquePropertyPath ?? '['.$uniquePropertyPath.']';
        $this->existingEntitiesQueryBuilder = $existingEntitiesQueryBuilder;
        $this->metadata = $domain->getObjectManager()->getClassMetadata($domain->getClass());
        $this->config = $config ?? new UniqueEntityConfiguration($domain, $this->uniquePropertyPath);
        $this->processor = $processor ?? new Processor();
        $this->defaultLocale = $defaultLocale;
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function load($resource): ResourceListInterface
    {
        $content = $this->loadContent($resource);
        $items = $this->processor->processConfiguration($this->config, [$content]);

        return $this->doLoad($items);
    }

    /**
     * Check if the new entities are loaded.
     */
    public function hasNewEntities(): bool
    {
        return $this->hasNewEntities;
    }

    /**
     * Check if the entities are updated.
     */
    public function hasUpdatedEntities(): bool
    {
        return $this->hasUpdatedEntities;
    }

    public function setDefaultLocale(string $defaultLocale): self
    {
        $this->defaultLocale = $defaultLocale;

        return $this;
    }

    /**
     * Create a new instance of entity.
     *
     * @param array $options The options
     */
    protected function newInstance(array $options): object
    {
        return $this->domain->newInstance($options);
    }

    /**
     * Action to load the config of entities in doctrine.
     *
     * @param array $items The items
     */
    protected function doLoad(array $items): ResourceListInterface
    {
        $filters = SqlFilterUtil::disableFilters($this->domain->getObjectManager(), [], true);
        $list = $this->getExistingEntities($items);

        /** @var object[] $entities */
        $entities = [];
        /** @var object[] $upsertEntities */
        $upsertEntities = [];
        $res = new ResourceList();

        foreach ($list as $entity) {
            $entities[$this->getUniqueValue($entity)] = $entity;
        }

        foreach ($items as $item) {
            $this->convertToEntity($upsertEntities, $entities, $item);
        }

        // upsert entities
        if (\count($upsertEntities) > 0) {
            $res = $this->domain->upserts($upsertEntities, true);
        }

        SqlFilterUtil::enableFilters($this->domain->getObjectManager(), $filters);

        return $res;
    }

    /**
     * Find and attach entity in the map entities.
     *
     * @param array|object[] $upsertEntities The map of upserted entities (by reference)
     * @param array|object[] $entities       The map of entities in database
     * @param array          $item           The item
     */
    protected function convertToEntity(array &$upsertEntities, array $entities, array $item): void
    {
        $itemUniqueValue = $this->getSourceUniqueValue($item);
        $newEntity = false;

        if (!isset($entities[$itemUniqueValue])) {
            $entity = $this->newInstance($item);
            $this->setUniqueValue($entity, $itemUniqueValue);

            if ($entity instanceof TranslatableInterface) {
                $entity->setAvailableLocales([$this->defaultLocale]);
            }

            $upsertEntities[$itemUniqueValue] = $entity;
            $this->hasNewEntities = true;
        } else {
            $entity = $entities[$itemUniqueValue];
        }

        $this->mapProperties($upsertEntities, $entities, $entity, $item, $newEntity);
    }

    /**
     * @param bool $newEntity
     *
     * @throws
     */
    protected function mapProperties(array &$upsertEntities, array $entities, object $entity, array $item, $newEntity = false): void
    {
        /** @var BaseTranslation[] $translations */
        $translations = [];
        $edited = false;
        $optionalFields = $item['@optionalFields'] ?? [];

        foreach ($this->metadata->getFieldNames() as $fieldName) {
            if (\array_key_exists($fieldName, $item)) {
                $type = $this->metadata->getTypeOfField($fieldName);
                $value = $this->accessor->getValue($entity, $fieldName);
                $itemValue = $item[$fieldName];

                // Skip optional field with existing entity field with a value
                if (\in_array($fieldName, $optionalFields, true) && !empty($value)) {
                    continue;
                }

                switch ($type) {
                    case Types::DATETIME_MUTABLE:
                    case Types::DATETIME_IMMUTABLE:
                    case Types::DATETIMETZ_MUTABLE:
                    case Types::DATETIMETZ_IMMUTABLE:
                    case Types::DATE_MUTABLE:
                    case Types::DATE_IMMUTABLE:
                    case Types::TIME_MUTABLE:
                    case Types::TIME_IMMUTABLE:
                        $itemValue = null !== $itemValue && !$itemValue instanceof \DateTimeInterface
                            ? new \DateTime($itemValue)
                            : $itemValue;

                        if ($itemValue instanceof \DateTime) {
                            $this->accessor->setValue($entity, $fieldName, $itemValue);
                        }

                        break;
                    default:
                        if (!empty($itemValue) && $value !== $itemValue) {
                            $this->accessor->setValue($entity, $fieldName, $itemValue);
                            $edited = true;

                            if (!$newEntity) {
                                $this->hasUpdatedEntities = true;
                            }
                        }

                        break;
                }
            }
        }

        if ($this->isTranslatable()) {
            /** @var TranslatableInterface $entity */
            $translations = DataLoaderTranslationUtil::getTranslationsMap($entity);
        }

        foreach ($this->metadata->getAssociationNames() as $associationName) {
            if (\array_key_exists($associationName, $item) && !empty($item[$associationName])) {
                $mapping = $this->metadata->getAssociationMapping($associationName);

                switch ($mapping['type']) {
                    case ClassMetadataInfo::ONE_TO_ONE:
                        $assoItem = $item[$associationName];
                        $assoClass = $mapping['targetEntity'];
                        $assoEntity = null;

                        if (isset($assoItem['reference']) && is_a($assoItem['reference'], $assoClass)) {
                            $assoEntity = $assoItem['reference'];
                        }

                        if (isset($assoItem['values'])) {
                            $assoEntity = $this->accessor->getValue($entity, $associationName)
                                ?? new $assoClass();
                            $assoOptionalFields = $assoItem['values']['@optionalFields'] ?? [];
                            unset($assoItem['values']['@optionalFields']);

                            foreach ($assoItem['values'] as $field => $value) {
                                $existingAssoValue = $this->accessor->getValue($assoEntity, $field);

                                if (\in_array($field, $assoOptionalFields, true) && !empty($existingAssoValue)) {
                                    continue;
                                }

                                $this->accessor->setValue($assoEntity, $field, $value);
                            }
                        } elseif (!empty($assoItem['criteria'])) {
                            $assoEntity = $this->domain->getObjectManager()->getRepository($assoClass)
                                ->findOneBy($assoItem['criteria'])
                            ;
                        }

                        if (null !== $assoEntity) {
                            $this->accessor->setValue($entity, $associationName, $assoEntity);
                        }

                        if (!$newEntity) {
                            $this->hasUpdatedEntities = true;
                        }

                        break;
                    case ClassMetadataInfo::MANY_TO_ONE:
                        $assoItem = $item[$associationName];

                        if (isset($assoItem['reference'])) {
                            $assoEntity = $assoItem['reference'];

                            if (!is_a($assoEntity, $mapping['targetEntity'])) {
                                throw new InvalidArgumentException(sprintf('The many-to-one association "%s" is not supported without an object target instance in reference', $associationName));
                            }

                            $this->accessor->setValue($entity, $associationName, $assoEntity);
                        }

                        break;
                    case ClassMetadataInfo::ONE_TO_MANY:
                        if ('translations' === $associationName && $this->isTranslatable()) {
                            $edited = DataLoaderTranslationUtil::injectTranslations(
                                $entity,
                                $mapping['targetEntity'],
                                $item[$associationName],
                                $translations
                            ) || $edited;
                        } else {
                            throw new InvalidArgumentException(sprintf('The one-to-many association "%s" is not supported', $associationName));
                        }

                        break;
                    case ClassMetadataInfo::MANY_TO_MANY:
                        foreach ($item[$associationName] as $child) {
                            if (!isset($child['criteria'][$this->uniquePropertyPath])) {
                                throw new InvalidArgumentException(sprintf('The "%s" criteria is required for the "%s" association', $this->uniquePropertyPath, $associationName));
                            }
                            if (!isset($entities[$child['criteria'][$this->uniquePropertyPath]]) && !isset($upsertEntities[$child['criteria'][$this->uniquePropertyPath]])) {
                                throw new RuntimeException(sprintf('The entity "%s" does not exist', $child['criteria'][$this->uniquePropertyPath]));
                            }

                            /** @var Collection $coll */
                            $coll = $this->accessor->getValue($entity, $associationName);
                            $childEntity = $entities[$child['criteria'][$this->uniquePropertyPath]]
                                ?? $upsertEntities[$child['criteria'][$this->uniquePropertyPath]];

                            if (!$coll->contains($childEntity)) {
                                $coll->add($childEntity);
                                $edited = true;

                                if (!$newEntity) {
                                    $this->hasUpdatedEntities = true;
                                }
                            }
                        }

                        break;
                    default:
                        break;
                }
            }
        }

        if ($edited) {
            $this->hasUpdatedEntities = true;
            $uniqueValue = $this->getUniqueValue($entity);

            if (!isset($upsertEntities[$uniqueValue])) {
                $upsertEntities[$uniqueValue] = $entity;
            }
        }
    }

    /**
     * Check if the entity is translatable.
     */
    protected function isTranslatable(): bool
    {
        return \in_array(TranslatableInterface::class, class_implements($this->metadata->getName()), true);
    }

    protected function getSourceUniqueValue(array $item): string
    {
        return $this->accessor->getValue($item, $this->sourceUniquePropertyPath);
    }

    protected function setUniqueValue(object $entity, $value): void
    {
        $this->accessor->setValue($entity, $this->uniquePropertyPath, $value);
    }

    protected function getUniqueValue(object $entity): string
    {
        return $this->accessor->getValue($entity, $this->uniquePropertyPath);
    }

    /**
     * @param $items array[]
     *
     * @return object[]
     */
    protected function getExistingEntities(array $items): array
    {
        $qb = $this->createExistingEntitiesQueryBuilder();
        $alias = $qb->getRootAliases()[0];
        $qb->andWhere($alias.'.'.$this->uniquePropertyPath.' in (:uniqueValues)');
        $qb->setParameter('uniqueValues', $this->getSourceUniqueValues($items));

        return $qb->getQuery()->getResult();
    }

    protected function createExistingEntitiesQueryBuilder(): QueryBuilder
    {
        if (null !== $this->existingEntitiesQueryBuilder) {
            return clone $this->existingEntitiesQueryBuilder;
        }

        return $this->domain->createQueryBuilder();
    }

    protected function getSourceUniqueValues(array $items): array
    {
        $values = [];

        foreach ($items as $item) {
            $values[] = $this->getSourceUniqueValue($item);
        }

        return $values;
    }

    /**
     * Load the resource content.
     *
     * @param mixed $resource The resource
     */
    abstract protected function loadContent($resource): array;
}
