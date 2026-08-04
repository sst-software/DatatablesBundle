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

use Sg\DatatablesBundle\Datatable\Column\AbstractColumn;
use Sg\DatatablesBundle\Datatable\Column\Column;

/**
 * @internal
 * @coversNothing
 */
final class AbstractColumnTest extends \PHPUnit\Framework\TestCase
{
    public function testIsToManyAssociationForAToManyAssociation()
    {
        $column = $this->getAssociationColumn([AbstractColumn::TO_MANY_ASSOCIATION]);

        static::assertTrue($column->isToManyAssociation());
    }

    public function testIsToManyAssociationForAToOneAssociation()
    {
        $column = $this->getAssociationColumn([AbstractColumn::TO_ONE_ASSOCIATION]);

        static::assertFalse($column->isToManyAssociation());
    }

    /**
     * A nested dql such as 'comments.author.username' collects one entry per hop; a single
     * to-many hop anywhere in the chain makes the whole column a to-many column.
     */
    public function testIsToManyAssociationForANestedAssociation()
    {
        $column = $this->getAssociationColumn([AbstractColumn::TO_ONE_ASSOCIATION, AbstractColumn::TO_MANY_ASSOCIATION]);

        static::assertTrue($column->isToManyAssociation());
    }

    public function testIsToManyAssociationWithoutAnAssociation()
    {
        $column = new Column();
        $column->initOptions();
        $column->setDql('title');
        $column->setTypeOfAssociation(null);

        static::assertFalse($column->isToManyAssociation());
    }

    /**
     * Until 1.8.0 these were the ClassMetadataInfo::ONE_TO_MANY / MANY_TO_MANY integers,
     * which now have to be rejected rather than silently producing a non-to-many column.
     */
    public function testAddTypeOfAssociationRejectsAnUnknownValue()
    {
        $column = new Column();
        $column->initOptions();

        $this->expectException(\Exception::class);
        // ClassMetadataInfo::ONE_TO_MANY
        $column->addTypeOfAssociation(4);
    }

    public function testSetTypeOfAssociationRejectsAnUnknownValue()
    {
        $column = new Column();
        $column->initOptions();

        $this->expectException(\Exception::class);
        // ClassMetadataInfo::MANY_TO_MANY
        $column->setTypeOfAssociation([8]);
    }

    private function getAssociationColumn(array $typeOfAssociation): Column
    {
        $column = new Column();
        $column->initOptions();
        $column->setDql('comments.title');
        $column->setTypeOfAssociation($typeOfAssociation);

        return $column;
    }
}
