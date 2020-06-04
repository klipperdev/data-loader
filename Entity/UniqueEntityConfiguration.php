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

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Klipper\Component\DoctrineExtensionsExtra\Model\Traits\TranslatableInterface;
use Klipper\Component\Resource\Domain\DomainInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class UniqueEntityConfiguration implements ConfigurationInterface
{
    protected DomainInterface $domain;

    /**
     * @var ClassMetadata|ClassMetadataInfo
     */
    protected ClassMetadata $metadata;

    public function __construct(DomainInterface $domain)
    {
        $this->domain = $domain;
        $this->metadata = $domain->getObjectManager()->getClassMetadata($domain->getClass());
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('entities');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $children = $rootNode
            ->requiresAtLeastOneElement()
            ->prototype('array')
            ->children()
        ;

        foreach ($this->metadata->getFieldNames() as $fieldName) {
            $this->addField($children, $fieldName);
        }

        foreach ($this->metadata->getAssociationNames() as $associationName) {
            $this->addAssociation($children, $associationName);
        }

        $children
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * Add the field.
     *
     * @param NodeBuilder $children  The children node
     * @param string      $fieldName The field name
     */
    protected function addField(NodeBuilder $children, string $fieldName): void
    {
        $type = $this->metadata->getTypeOfField($fieldName);

        switch ($type) {
            case Types::BOOLEAN:
                $children->booleanNode($fieldName)->end();

                break;
            case Types::FLOAT:
            case Types::DECIMAL:
                $children->floatNode($fieldName)->end();

                break;
            case Types::BIGINT:
            case Types::SMALLINT:
            case Types::INTEGER:
                $children->integerNode($fieldName)->end();

                break;
            case Types::ARRAY:
            case Types::SIMPLE_ARRAY:
            case Types::JSON_ARRAY:
                $children
                    ->arrayNode($fieldName)
                    ->prototype('scalar')->end()
                    ->end()
                ;

                break;
            case Types::DATETIME_MUTABLE:
            case Types::DATETIME_IMMUTABLE:
            case Types::DATETIMETZ_MUTABLE:
            case Types::DATETIMETZ_IMMUTABLE:
            case Types::DATE_MUTABLE:
            case Types::DATE_IMMUTABLE:
            case Types::TIME_MUTABLE:
            case Types::TIME_IMMUTABLE:
            case Types::OBJECT:
            case Types::STRING:
            case Types::TEXT:
            case Types::BINARY:
            case Types::BLOB:
            case Types::GUID:
            default:
                $children->scalarNode($fieldName)->end();

                break;
        }
    }

    /**
     * Add the association.
     *
     * @param NodeBuilder $children        The children node
     * @param string      $associationName The $association name
     *
     * @throws
     */
    protected function addAssociation(NodeBuilder $children, string $associationName): void
    {
        $mapping = $this->metadata->getAssociationMapping($associationName);

        switch ($mapping['type']) {
            case ClassMetadataInfo::ONE_TO_ONE:
                $this->addAssociationCriteria($children, $associationName);

                break;
            case ClassMetadataInfo::MANY_TO_ONE:
                $this->addAssociationCriteria($children, $associationName);

                break;
            case ClassMetadataInfo::ONE_TO_MANY:
                if ('translations' === $associationName && $this->isTranslatable()) {
                    $children->arrayNode($associationName)
                        ->useAttributeAsKey('locale')
                        ->normalizeKeys(false)
                        ->prototype('array')
                        ->useAttributeAsKey('field')
                        ->normalizeKeys(false)
                        ->prototype('scalar')->end()
                        ->end()
                        ->end()
                    ;
                } else {
                    $this->addAssociationCriteriaList($children, $associationName);
                }

                break;
            case ClassMetadataInfo::MANY_TO_MANY:
                $this->addAssociationCriteriaList($children, $associationName);

                break;
            default:
                break;
        }
    }

    /**
     * Add the association criteria for list.
     *
     * @param NodeBuilder $children        The children node
     * @param string      $associationName The association name
     */
    private function addAssociationCriteriaList(NodeBuilder $children, $associationName): void
    {
        $children->arrayNode($associationName)
            ->prototype('array')
            ->beforeNormalization()
            ->ifTrue(static function ($v) {
                return \is_string($v) || !isset($v['criteria']);
            })
            ->then(static function ($v) {
                if (\is_string($v)) {
                    $v = ['name' => $v];
                }

                return ['criteria' => $v];
            })
            ->end()
            ->children()
            ->arrayNode('criteria')
            ->useAttributeAsKey('field')
            ->normalizeKeys(false)
            ->prototype('scalar')->end()
            ->end()
            ->end()
            ->end()
        ;
    }

    /**
     * Add the association criteria.
     *
     * @param NodeBuilder $children        The children node
     * @param string      $associationName The association name
     */
    private function addAssociationCriteria(NodeBuilder $children, $associationName): void
    {
        $children->arrayNode($associationName)
            ->beforeNormalization()
            ->ifTrue(static function ($v) {
                return \is_string($v) || !isset($v['criteria']);
            })
            ->then(static function ($v) {
                if (\is_string($v)) {
                    $v = ['name' => $v];
                }

                return ['criteria' => $v];
            })
            ->end()
            ->children()
            ->arrayNode('criteria')
            ->useAttributeAsKey('field')
            ->normalizeKeys(false)
            ->prototype('scalar')->end()
            ->end()
            ->end()
            ->end()
        ;
    }

    /**
     * Check if the entity is translatable.
     */
    private function isTranslatable(): bool
    {
        return \in_array(TranslatableInterface::class, class_implements($this->metadata->getName()), true);
    }
}
