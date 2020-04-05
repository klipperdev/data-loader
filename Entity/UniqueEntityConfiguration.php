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

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
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
    /**
     * @var DomainInterface
     */
    protected $domain;

    /**
     * @var ClassMetadata|ClassMetadataInfo
     */
    protected $metadata;

    /**
     * Constructor.
     *
     * @param DomainInterface $domain The domain
     */
    public function __construct(DomainInterface $domain)
    {
        $this->domain = $domain;
        $this->metadata = $domain->getObjectManager()->getClassMetadata($domain->getClass());
    }

    /**
     * {@inheritdoc}
     */
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
            case Type::BOOLEAN:
                $children->booleanNode($fieldName)->end();

                break;
            case Type::FLOAT:
            case Type::DECIMAL:
                $children->floatNode($fieldName)->end();

                break;
            case Type::BIGINT:
            case Type::SMALLINT:
            case Type::INTEGER:
                $children->integerNode($fieldName)->end();

                break;
            case Type::TARRAY:
            case Type::SIMPLE_ARRAY:
            case Type::JSON_ARRAY:
                $children
                    ->arrayNode($fieldName)
                    ->prototype('scalar')->end()
                    ->end()
                ;

                break;
            case Type::DATETIME:
            case Type::DATETIMETZ:
            case Type::DATE:
            case Type::TIME:
            case Type::OBJECT:
            case Type::STRING:
            case Type::TEXT:
            case Type::BINARY:
            case Type::BLOB:
            case Type::GUID:
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
