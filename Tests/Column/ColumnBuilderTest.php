<?php

/*
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Tests\Column;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\ManyToManyInverseSideMapping;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\ORM\Mapping\OneToManyAssociationMapping;
use Doctrine\ORM\Mapping\OneToOneInverseSideMapping;
use Doctrine\ORM\Mapping\OneToOneOwningSideMapping;
use Sg\DatatablesBundle\Datatable\Column\AbstractColumn;
use Sg\DatatablesBundle\Datatable\Column\ActionColumn;
use Sg\DatatablesBundle\Datatable\Column\Column;
use Sg\DatatablesBundle\Datatable\Column\ColumnBuilder;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * @internal
 * @coversNothing
 */
final class ColumnBuilderTest extends \PHPUnit\Framework\TestCase
{
    public function testRemoveKeepsColumnNamesInSyncWithColumns()
    {
        $columnBuilder = $this->getColumnBuilder();
        $columnBuilder
            ->add('id', Column::class, ['title' => 'Id'])
            ->add('title', Column::class, ['title' => 'Title'])
            ->add(null, ActionColumn::class, ['title' => 'Actions', 'actions' => []])
        ;

        static::assertSame(['id' => 0, 'title' => 1, '' => 2], $columnBuilder->getColumnNames());

        $columnBuilder->remove('title');

        static::assertCount(2, $columnBuilder->getColumns());
        static::assertSame(['id' => 0, '' => 1], $columnBuilder->getColumnNames());
    }

    /**
     * A column with 'dql' === null (ActionColumn, MultiselectColumn, a VirtualColumn added
     * without a dql) used to end up as a null array key in $columnNames, which PHP 8.5
     * deprecates for both array offsets and \array_key_exists().
     */
    public function testRemoveDoesNotUseNullAsAnArrayKey()
    {
        $columnBuilder = $this->getColumnBuilder();

        set_error_handler(static function ($severity, $message) {
            throw new \ErrorException($message, 0, $severity);
        }, \E_DEPRECATED | \E_USER_DEPRECATED);

        try {
            $columnBuilder
                ->add('title', Column::class, ['title' => 'Title'])
                ->add(null, ActionColumn::class, ['title' => 'Actions', 'actions' => []])
                // hits the reindex loop, which read the null dql of the ActionColumn
                ->remove('title')
                // hits the \array_key_exists() call, which was passed the null dql itself
                ->remove(null)
            ;
        } finally {
            restore_error_handler();
        }

        static::assertSame([], $columnBuilder->getColumnNames());
        static::assertSame([], $columnBuilder->getColumns());
    }

    /**
     * 1.8.0 replaced the ClassMetadataInfo::ONE_TO_MANY / MANY_TO_MANY integers with an
     * `instanceof ToManyAssociationMapping` check. These pin that every concrete ORM 3
     * mapping class still lands on the association type the column expects.
     *
     * @dataProvider provideAssociationMappings
     */
    public function testTheAssociationTypeIsDerivedFromTheOrmMapping(AssociationMapping $mapping, bool $expectedToMany)
    {
        $columnBuilder = $this->getColumnBuilder($mapping);
        $columnBuilder->add('comments.title', Column::class, ['title' => 'Comment title']);

        $columns = $columnBuilder->getColumns();

        static::assertSame(
            $expectedToMany ? [AbstractColumn::TO_MANY_ASSOCIATION] : [AbstractColumn::TO_ONE_ASSOCIATION],
            $columns[0]->getTypeOfAssociation()
        );
        static::assertSame($expectedToMany, $columns[0]->isToManyAssociation());
    }

    public static function provideAssociationMappings(): array
    {
        $mappingArray = [
            'fieldName' => 'comments',
            'sourceEntity' => 'AppBundle\Entity\Post',
            'targetEntity' => 'AppBundle\Entity\Comment',
        ];

        return [
            'oneToMany' => [OneToManyAssociationMapping::fromMappingArray($mappingArray), true],
            'manyToManyOwningSide' => [ManyToManyOwningSideMapping::fromMappingArray($mappingArray), true],
            'manyToManyInverseSide' => [ManyToManyInverseSideMapping::fromMappingArray($mappingArray), true],
            'manyToOne' => [ManyToOneAssociationMapping::fromMappingArray($mappingArray), false],
            'oneToOneOwningSide' => [OneToOneOwningSideMapping::fromMappingArray($mappingArray), false],
            'oneToOneInverseSide' => [OneToOneInverseSideMapping::fromMappingArray($mappingArray), false],
        ];
    }

    private function getColumnBuilder(?AssociationMapping $associationMapping = null): ColumnBuilder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $metadata = $this->createMock(ClassMetadata::class);
        /** @noinspection PhpUndefinedMethodInspection */
        $metadata->method('getName')->willReturn('AppBundle\Entity\Post');

        /** @noinspection PhpUndefinedMethodInspection */
        $twig = $this->createMock(Environment::class);
        /** @noinspection PhpUndefinedMethodInspection */
        $router = $this->createMock(RouterInterface::class);
        /** @noinspection PhpUndefinedMethodInspection */
        $em = $this->createMock(EntityManagerInterface::class);

        if (null !== $associationMapping) {
            /** @noinspection PhpUndefinedMethodInspection */
            $metadata->method('getAssociationMapping')->willReturn($associationMapping);
            /** @noinspection PhpUndefinedMethodInspection */
            $metadata->method('getAssociationTargetClass')->willReturn('AppBundle\Entity\Comment');

            /** @noinspection PhpUndefinedMethodInspection */
            $metadataFactory = $this->createMock(ClassMetadataFactory::class);
            /** @noinspection PhpUndefinedMethodInspection */
            $metadataFactory->method('getMetadataFor')->willReturn($metadata);
            /** @noinspection PhpUndefinedMethodInspection */
            $em->method('getMetadataFactory')->willReturn($metadataFactory);
        }

        return new ColumnBuilder($metadata, $twig, $router, 'post_datatable', $em);
    }
}
