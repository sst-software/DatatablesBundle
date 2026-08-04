<?php

/*
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Tests\Response;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Mapping\ClassMetadata;
use PHPUnit\Framework\MockObject\MockObject;
use Sg\DatatablesBundle\Datatable\Ajax;
use Sg\DatatablesBundle\Datatable\Column\ColumnBuilder;
use Sg\DatatablesBundle\Datatable\DatatableInterface;
use Sg\DatatablesBundle\Datatable\Features;
use Sg\DatatablesBundle\Datatable\Options;
use Sg\DatatablesBundle\Response\DatatableQueryBuilder;

/**
 * @internal
 * @coversNothing
 */
final class DatatableQueryBuilderTest extends \PHPUnit\Framework\TestCase
{
    /** @var EntityManagerInterface|MockObject */
    private $entityManager;

    /** @var MockObject|QueryBuilder */
    private $queryBuilder;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        /** @noinspection PhpUndefinedMethodInspection */
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
    }

    public function testUsingAPrefixedAliasWhenShortNameIsAReservedWord()
    {
        $this->assertRootAlias('\App\Entity\Order', 'Order', '_order');
    }

    public function testUsingTheSortNameWhenShortNameIsNotAReservedWord()
    {
        $this->assertRootAlias('\App\Entity\Account', 'Account', 'account');
    }

    /**
     * 'count' is a DQL keyword but not a keyword of any database platform, so it was not
     * prefixed before the alias check was moved from the platform's SQL keyword list to
     * the DQL parser's own reserved words.
     */
    public function testUsingAPrefixedAliasWhenShortNameIsADqlOnlyKeyword()
    {
        $this->assertRootAlias('\App\Entity\Count', 'Count', '_count');
    }

    /**
     * The counterpart: 'user' is reserved on PostgreSQL, SQL Server, Oracle and DB2 but is
     * not a DQL keyword, so it is no longer prefixed on those platforms.
     */
    public function testUsingTheShortNameWhenShortNameIsOnlyAPlatformKeyword()
    {
        $this->assertRootAlias('\App\Entity\User', 'User', 'user');
    }

    /**
     * The alias check must not depend on the DBAL connection at all: resolving the
     * platform is what triggered a deprecation on every datatable request.
     */
    public function testTheAliasCheckDoesNotTouchTheConnection()
    {
        // @noinspection PhpUndefinedMethodInspection
        $this->entityManager->expects(static::never())->method('getConnection');

        $this->assertRootAlias('\App\Entity\Account', 'Account', 'account');
    }

    /**
     * The root alias is whatever getSafeName() makes of the lowercased entity short name,
     * and it reaches the query as the second argument of QueryBuilder::from().
     */
    private function assertRootAlias(string $entityName, string $shortName, string $expectedAlias): void
    {
        // @noinspection PhpUndefinedMethodInspection
        $this->queryBuilder->expects(static::once())
            ->method('from')
            ->with($entityName, $expectedAlias)
            ->willReturn($this->queryBuilder)
        ;

        $this->createDatatableQueryBuilder($entityName, $shortName);
    }

    private function createDatatableQueryBuilder(string $entityName, string $shortName): DatatableQueryBuilder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $reflectionClass = $this->createMock(\ReflectionClass::class);
        /** @noinspection PhpUndefinedMethodInspection */
        $reflectionClass->method('getShortName')->willReturn($shortName);

        /** @noinspection PhpUndefinedMethodInspection */
        $classMetadata = $this->createMock(ClassMetadata::class);
        /** @noinspection PhpUndefinedMethodInspection */
        $classMetadata->method('getReflectionClass')->willReturn($reflectionClass);
        /** @noinspection PhpUndefinedMethodInspection */
        $classMetadata->method('getIdentifierFieldNames')->willReturn([]);

        /** @noinspection PhpUndefinedMethodInspection */
        $classMetadataFactory = $this->createMock(ClassMetadataFactory::class);
        /** @noinspection PhpUndefinedMethodInspection */
        $classMetadataFactory->method('getMetadataFor')->with($entityName)->willReturn($classMetadata);

        // @noinspection PhpUndefinedMethodInspection
        $this->entityManager->method('getMetadataFactory')->willReturn($classMetadataFactory);
        // @noinspection PhpUndefinedMethodInspection
        $this->entityManager->method('createQueryBuilder')->willReturn($this->queryBuilder);

        /** @noinspection PhpUndefinedMethodInspection */
        $columnBuilder = $this->createMock(ColumnBuilder::class);
        /** @noinspection PhpUndefinedMethodInspection */
        $columnBuilder->method('getColumns')->willReturn([]);
        /** @noinspection PhpUndefinedMethodInspection */
        $columnBuilder->method('getColumnNames')->willReturn([]);

        /** @noinspection PhpUndefinedMethodInspection */
        $dataTable = $this->createMock(DatatableInterface::class);
        /** @noinspection PhpUndefinedMethodInspection */
        $dataTable->method('getEntity')->willReturn($entityName);
        /** @noinspection PhpUndefinedMethodInspection */
        $dataTable->method('getEntityManager')->willReturn($this->entityManager);
        /** @noinspection PhpUndefinedMethodInspection */
        $dataTable->method('getColumnBuilder')->willReturn($columnBuilder);
        /** @noinspection PhpUndefinedMethodInspection */
        $dataTable->method('getOptions')->willReturn($this->createMock(Options::class));
        /** @noinspection PhpUndefinedMethodInspection */
        $dataTable->method('getFeatures')->willReturn($this->createMock(Features::class));
        /** @noinspection PhpUndefinedMethodInspection */
        $dataTable->method('getAjax')->willReturn($this->createMock(Ajax::class));

        return new DatatableQueryBuilder([], $dataTable);
    }
}
