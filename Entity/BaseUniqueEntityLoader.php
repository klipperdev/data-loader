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
use Doctrine\Persistence\Mapping\ClassMetadata;
use Klipper\Component\DataLoader\DataLoaderInterface;
use Klipper\Component\DataLoader\Exception\ConsoleResourceException;
use Klipper\Component\DataLoader\Exception\InvalidArgumentException;
use Klipper\Component\DataLoader\Exception\RuntimeException;
use Klipper\Component\DoctrineExtensionsExtra\Model\BaseTranslation;
use Klipper\Component\DoctrineExtensionsExtra\Model\Traits\TranslatableInterface;
use Klipper\Component\Resource\Domain\DomainInterface;
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
     * @param DomainInterface                $domain        The resource domain of entity
     * @param null|UniqueEntityConfiguration $config        The amenity configuration
     * @param Processor                      $processor     The processor
     * @param string                         $defaultLocale The default locale
     * @param null|PropertyAccessor          $accessor      The property accessor
     */
    public function __construct(
        DomainInterface $domain,
        UniqueEntityConfiguration $config = null,
        Processor $processor = null,
        string $defaultLocale = 'en',
        PropertyAccessor $accessor = null
    ) {
        $this->domain = $domain;
        $this->metadata = $domain->getObjectManager()->getClassMetadata($domain->getClass());
        $this->config = $config ?? new UniqueEntityConfiguration($domain);
        $this->processor = $processor ?? new Processor();
        $this->defaultLocale = $defaultLocale;
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function load($resource): void
    {
        if (!$this->supports($resource)) {
            throw new InvalidArgumentException('The resource is not supported by this data loader');
        }

        $content = $this->loadContent($resource);
        $items = $this->processor->processConfiguration($this->config, [$content]);

        $this->doLoad($items);
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
     * Load the resource content.
     *
     * @param mixed $resource The resource
     */
    abstract protected function loadContent($resource): array;

    /**
     * Action to load the config of entities in doctrine.
     *
     * @param array $items The items
     */
    protected function doLoad(array $items): void
    {
        $list = $this->getExistingEntities($items);
        /** @var object[] $entities */
        $entities = [];
        /** @var object[] $upsertEntities */
        $upsertEntities = [];

        foreach ($list as $entity) {
            $entities[$this->getUniqueValue($entity)] = $entity;
        }

        foreach ($items as $item) {
            $this->convertToEntity($upsertEntities, $entities, $item);
        }

        // upsert entities
        if (\count($upsertEntities) > 0) {
            $res = $this->domain->upserts($upsertEntities, true);

            if ($res->hasErrors()) {
                throw new ConsoleResourceException($res, 'name');
            }
        }
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
            $entity->setName($itemUniqueValue);

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

        foreach ($this->metadata->getFieldNames() as $fieldName) {
            if (\array_key_exists($fieldName, $item)) {
                $type = $this->metadata->getTypeOfField($fieldName);
                $value = $this->accessor->getValue($entity, $fieldName);
                $itemValue = $item[$fieldName];

                switch ($type) {
                    case Types::DATETIME_MUTABLE:
                    case Types::DATETIME_IMMUTABLE:
                    case Types::DATETIMETZ_MUTABLE:
                    case Types::DATETIMETZ_IMMUTABLE:
                    case Types::DATE_MUTABLE:
                    case Types::DATE_IMMUTABLE:
                    case Types::TIME_MUTABLE:
                    case Types::TIME_IMMUTABLE:
                        $itemValue = null !== $itemValue ? new \DateTime($itemValue) : $itemValue;

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
            foreach ($entity->getTranslations() as $translation) {
                /* @var BaseTranslation $translation */
                $translations[$translation->getLocale().':'.$translation->getField()] = $translation;
            }
        }

        foreach ($this->metadata->getAssociationNames() as $associationName) {
            if (\array_key_exists($associationName, $item) && !empty($item[$associationName])) {
                $mapping = $this->metadata->getAssociationMapping($associationName);

                switch ($mapping['type']) {
                    case ClassMetadataInfo::ONE_TO_ONE:
                        throw new InvalidArgumentException(sprintf('The one-to-one association "%s" is not supported', $associationName));

                        break;
                    case ClassMetadataInfo::MANY_TO_ONE:
                        throw new InvalidArgumentException(sprintf('The many-to-one association "%s" is not supported', $associationName));

                        break;
                    case ClassMetadataInfo::ONE_TO_MANY:
                        if ('translations' === $associationName && $this->isTranslatable()) {
                            $transClass = $mapping['targetEntity'];

                            foreach ($item[$associationName] as $transLocale => $transItem) {
                                foreach ($transItem as $transField => $transValue) {
                                    $id = $transLocale.':'.$transField;

                                    if (!isset($translations[$id])) {
                                        $edited = true;
                                        /** @var BaseTranslation $transEntity */
                                        $transEntity = new $transClass($transLocale, $transField, $transValue);
                                        $transEntity->setObject($entity);
                                        $entity->getTranslations()->add($transEntity);
                                        $availables = $entity->getAvailableLocales();
                                        $availables[] = $transLocale;
                                        $availables = array_unique($availables);
                                        sort($availables);
                                        $entity->setAvailableLocales($availables);
                                    } elseif ($translations[$id]->getContent() !== $transValue) {
                                        $edited = true;
                                        $translations[$id]->setContent($transValue);
                                    }
                                }
                            }
                        } else {
                            throw new InvalidArgumentException(sprintf('The one-to-many association "%s" is not supported', $associationName));
                        }

                        break;
                    case ClassMetadataInfo::MANY_TO_MANY:
                        foreach ($item[$associationName] as $child) {
                            if (!isset($child['criteria']['name'])) {
                                throw new InvalidArgumentException(sprintf('The "name" criteria is required for the "%s" association', $associationName));
                            }
                            if (!isset($entities[$child['criteria']['name']]) && !isset($upsertEntities[$child['criteria']['name']])) {
                                throw new RuntimeException(sprintf('The entity "%s" does not exist', $child['criteria']['name']));
                            }

                            /** @var Collection $coll */
                            $coll = $this->accessor->getValue($entity, $associationName);
                            $childEntity = $entities[$child['criteria']['name']]
                                ?? $upsertEntities[$child['criteria']['name']];

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

    abstract protected function getSourceUniqueValue(array $item): string;

    abstract protected function getUniqueValue(object $entity): string;

    /**
     * @param $items array[]
     *
     * @return object[]
     */
    abstract protected function getExistingEntities(array $items): array;
}
