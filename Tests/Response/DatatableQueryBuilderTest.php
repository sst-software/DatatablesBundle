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
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Prophecy\Prophecy\ObjectProphecy;
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
    /** @var EntityManagerInterface|ObjectProphecy */
    private $entityManager;

    /** @var ClassMetadataFactory|ObjectProphecy */
    private $classMetadataFactory;

    /** @var ObjectProphecy|QueryBuilder */
    private $queryBuilder;

    /** @var ClassMetadata|ObjectProphecy */
    private $classMetadata;

    /** @var ObjectProphecy|\ReflectionClass */
    private $reflectionClass;

    /** @var ColumnBuilder|ObjectProphecy */
    private $columnBuilder;

    /** @var ObjectProphecy|Options */
    private $options;

    /** @var Features|ObjectProphecy */
    private $features;

    /** @var Ajax|ObjectProphecy */
    private $ajax;

    /** @var DatatableInterface|ObjectProphecy */
    private $dataTable;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->classMetadataFactory = $this->prophesize(ClassMetadataFactory::class);
        $this->queryBuilder = $this->prophesize(QueryBuilder::class);
        $this->classMetadata = $this->prophesize(ClassMetadata::class);
        $this->reflectionClass = $this->prophesize(\ReflectionClass::class);
        $this->columnBuilder = $this->prophesize(ColumnBuilder::class);
        $this->options = $this->prophesize(Options::class);
        $this->features = $this->prophesize(Features::class);
        $this->ajax = $this->prophesize(Ajax::class);
        $this->dataTable = $this->prophesize(DatatableInterface::class);
    }

    public function testUsingAPrefixedAliasWhenShortNameIsAReservedWord()
    {
        $entityName = '\App\Entity\Order';
        $shortName = 'Order';
        $this->queryBuilder->from($entityName, '_order')->willReturn($this->queryBuilder)->shouldBeCalled();
        $this->getDataTableQueryBuilder($entityName, $shortName);
    }

    public function testUsingTheSortNameWhenShortNameIsNotAReservedWord()
    {
        $entityName = '\App\Entity\Account';
        $shortName = 'Account';
        $this->queryBuilder->from($entityName, 'account')->willReturn($this->queryBuilder)->shouldBeCalled();

        $this->getDataTableQueryBuilder($entityName, $shortName);
    }

    /**
     * 'count' is a DQL keyword but not a keyword of any database platform, so it was not
     * prefixed before the alias check was moved from the platform's SQL keyword list to
     * the DQL parser's own reserved words.
     */
    public function testUsingAPrefixedAliasWhenShortNameIsADqlOnlyKeyword()
    {
        $entityName = '\App\Entity\Count';
        $shortName = 'Count';
        $this->queryBuilder->from($entityName, '_count')->willReturn($this->queryBuilder)->shouldBeCalled();

        $this->getDataTableQueryBuilder($entityName, $shortName);
    }

    /**
     * The counterpart: 'user' is reserved on PostgreSQL, SQL Server, Oracle and DB2 but is
     * not a DQL keyword, so it is no longer prefixed on those platforms.
     */
    public function testUsingTheShortNameWhenShortNameIsOnlyAPlatformKeyword()
    {
        $entityName = '\App\Entity\User';
        $shortName = 'User';
        $this->queryBuilder->from($entityName, 'user')->willReturn($this->queryBuilder)->shouldBeCalled();

        $this->getDataTableQueryBuilder($entityName, $shortName);
    }

    /**
     * The alias check must not depend on the DBAL connection at all: resolving the
     * platform is what triggered a deprecation on every datatable request.
     */
    public function testTheAliasCheckDoesNotTouchTheConnection()
    {
        $entityName = '\App\Entity\Account';
        $this->queryBuilder->from($entityName, 'account')->willReturn($this->queryBuilder);

        $this->getDataTableQueryBuilder($entityName, 'Account');

        $this->entityManager->getConnection()->shouldNotHaveBeenCalled();
    }

    private function getDataTableQueryBuilder(string $entityName, string $shortName): DatatableQueryBuilder
    {
        $this->reflectionClass->getShortName()->willReturn($shortName);
        $this->classMetadata->getReflectionClass()->willReturn($this->reflectionClass->reveal());
        $this->classMetadata->getIdentifierFieldNames()->willReturn([]);
        $this->classMetadataFactory->getMetadataFor($entityName)->willReturn($this->classMetadata->reveal());
        $this->entityManager->getMetadataFactory()->willReturn($this->classMetadataFactory->reveal());
        $this->entityManager->createQueryBuilder()->willReturn($this->queryBuilder->reveal());
        $this->columnBuilder->getColumns()->willReturn([]);
        $this->columnBuilder->getColumnNames()->willReturn([]);
        $this->dataTable->getEntity()->willReturn($entityName);
        $this->dataTable->getEntityManager()->willReturn($this->entityManager->reveal());
        $this->dataTable->getColumnBuilder()->willReturn($this->columnBuilder->reveal());
        $this->dataTable->getOptions()->willReturn($this->options->reveal());
        $this->dataTable->getFeatures()->willReturn($this->features->reveal());
        $this->dataTable->getAjax()->willReturn($this->ajax->reveal());

        return new DatatableQueryBuilder([], $this->dataTable->reveal());
    }
}
