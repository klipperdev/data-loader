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
use Klipper\Component\DataLoader\Util\DataLoaderConfigUtil;
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

    protected string $uniquePropertyPath;

    /**
     * @var ClassMetadata|ClassMetadataInfo
     */
    protected ClassMetadata $metadata;

    public function __construct(DomainInterface $domain, string $uniquePropertyPath)
    {
        $this->domain = $domain;
        $this->uniquePropertyPath = $uniquePropertyPath;
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
            ->beforeNormalization()
            ->ifArray()
            ->then(static function ($v) {
                return DataLoaderConfigUtil::findOptionalFields($v);
            })
            ->end()
            ->children()
            ->arrayNode('@optionalFields')
            ->scalarPrototype()->end()
            ->end()
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
            case Types::JSON:
                $children
                    ->arrayNode($fieldName)
                    ->prototype('variable')->end()
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
                    $v = [$this->uniquePropertyPath => $v];
                }

                return ['criteria' => $v];
            })
            ->end()
            ->children()
            ->arrayNode('criteria')
            ->useAttributeAsKey('field')
            ->normalizeKeys(false)
            ->variablePrototype()->end()
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
                return \is_string($v) || !isset($v['criteria']) || !isset($v['values']);
            })
            ->then(static function ($v) {
                if (\is_string($v)) {
                    $v = [$this->uniquePropertyPath => $v];
                } elseif (isset($v['values']) && \is_array($v['values'])) {
                    return ['values' => $v['values']];
                }

                return ['criteria' => $v];
            })
            ->end()
            ->children()
            ->arrayNode('criteria')
            ->useAttributeAsKey('field')
            ->normalizeKeys(false)
            ->variablePrototype()->end()
            ->end()
            ->arrayNode('values')
            ->useAttributeAsKey('field')
            ->normalizeKeys(false)
            ->variablePrototype()->end()
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
