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

use Doctrine\Deprecations\Deprecation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Mapping\ClassMetadata;
use PHPUnit\Framework\MockObject\MockObject;
use Sg\DatatablesBundle\Datatable\Ajax;
use Sg\DatatablesBundle\Datatable\Column\Column;
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

    public function testOrderingWithAnAscendingDirection()
    {
        $this->assertOrderDirection('asc', \SortDirection::Ascending);
    }

    public function testOrderingWithADescendingDirection()
    {
        $this->assertOrderDirection('desc', \SortDirection::Descending);
    }

    /**
     * DataTables itself only ever sends lowercase directions, but the value is read straight
     * from the request, so a hand-written or proxied request can capitalise it.
     */
    public function testOrderingWithAnUppercaseDirection()
    {
        $this->assertOrderDirection('DESC', \SortDirection::Descending);
    }

    public function testOrderingWithAMixedCaseDirection()
    {
        $this->assertOrderDirection('Asc', \SortDirection::Ascending);
    }

    /**
     * Until 2.0.1 the direction reached QueryBuilder::addOrderBy() unchecked, which on
     * doctrine/orm < 3.7 concatenates it into the DQL ORDER BY clause. An unrecognised
     * value must never reach the query; it falls back to ascending instead.
     *
     * Since 2.1.0 an unrecognised value would instead make addOrderBy() throw an
     * InvalidArgumentException, so the fallback still has to happen here.
     */
    public function testOrderingWithAnUnknownDirectionFallsBackToAscending()
    {
        $this->assertOrderDirection('title DESC, account.id', \SortDirection::Ascending);
    }

    /**
     * The tests above record the argument against a mocked QueryBuilder, which never runs
     * ORM's own handling of it. The deprecation this release removes is therefore checked
     * against a real Doctrine\ORM\QueryBuilder, with doctrine/deprecations tracking on:
     * QueryBuilder::getSortDirection() triggers it for every $order that is not a
     * SortDirection, so a single triggered deprecation fails this test.
     *
     * @see https://github.com/doctrine/orm/issues/11313
     */
    public function testOrderingDoesNotTriggerTheOrmSortDirectionDeprecation()
    {
        $this->queryBuilder = new QueryBuilder($this->entityManager);

        Deprecation::enableTrackingDeprecations();
        // the deprecation is deduplicated per call site, so a run of the suite that already
        // triggered it elsewhere would otherwise hide it here
        Deprecation::withoutDeduplication();

        try {
            $qb = $this->createOrderedDatatableQueryBuilder('desc')->getBuiltQb();

            static::assertSame([], Deprecation::getTriggeredDeprecations());
            static::assertSame(
                ['account.title DESC'],
                array_map('strval', $qb->getDQLPart('orderBy'))
            );
        } finally {
            Deprecation::disable();
        }
    }

    /**
     * Order a single orderable column and assert which direction reaches the query.
     */
    private function assertOrderDirection(string $requestDirection, \SortDirection $expectedDirection): void
    {
        // getBuiltQb() works on a clone of the query builder, and a cloned mock records its
        // own invocations, so the ordering is captured from the stub instead of expects().
        $ordering = [];
        $queryBuilder = $this->queryBuilder;

        // @noinspection PhpUndefinedMethodInspection
        $this->queryBuilder->method('from')->willReturn($queryBuilder);
        // @noinspection PhpUndefinedMethodInspection
        $this->queryBuilder->method('addOrderBy')->willReturnCallback(
            static function ($sort, $order = null) use (&$ordering, $queryBuilder) {
                $ordering[] = [$sort, $order];

                return $queryBuilder;
            }
        );

        $this->createOrderedDatatableQueryBuilder($requestDirection)->getBuiltQb();

        static::assertSame([['account.title', $expectedDirection]], $ordering);
    }

    /**
     * Build a datatable over a single orderable column, ordered by the given raw request
     * direction.
     */
    private function createOrderedDatatableQueryBuilder(string $requestDirection): DatatableQueryBuilder
    {
        $column = new Column();
        // resolve the options, so the column gets the default orderable/searchable values
        $column->initOptions(true);
        $column->setDql('title');

        $requestParams = [
            'columns' => [['orderable' => 'true', 'search' => ['value' => '']]],
            'order' => [['column' => 0, 'dir' => $requestDirection]],
            'start' => 0,
            // paging off: this test is about the ORDER BY clause only
            'length' => DatatableQueryBuilder::DISABLE_PAGINATION,
        ];

        return $this->createDatatableQueryBuilder(
            '\\App\\Entity\\Account',
            'Account',
            $requestParams,
            [$column],
            ['title' => 0]
        );
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

    private function createDatatableQueryBuilder(
        string $entityName,
        string $shortName,
        array $requestParams = [],
        array $columns = [],
        array $columnNames = []
    ): DatatableQueryBuilder {
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
        $columnBuilder->method('getColumns')->willReturn($columns);
        /** @noinspection PhpUndefinedMethodInspection */
        $columnBuilder->method('getColumnNames')->willReturn($columnNames);

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

        return new DatatableQueryBuilder($requestParams, $dataTable);
    }
}
