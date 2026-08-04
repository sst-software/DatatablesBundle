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
use Doctrine\Persistence\Mapping\ClassMetadata;
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

    private function getColumnBuilder(): ColumnBuilder
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

        return new ColumnBuilder($metadata, $twig, $router, 'post_datatable', $em);
    }
}
