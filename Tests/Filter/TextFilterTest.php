<?php

/*
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Tests\Filter;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\QueryBuilder;
use Sg\DatatablesBundle\Datatable\Filter\TextFilter;

/**
 * @internal
 * @coversNothing
 */
final class TextFilterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * ColumnInterface::getTypeOfField() returns null for every column where
     * isSelectColumn() is false, so searching a VirtualColumn passed null to
     * preg_match() in AbstractFilter::getExpression().
     */
    public function testANullTypeOfFieldIsNotPassedToPregMatch()
    {
        $filter = new TextFilter();
        $filter->set(['search_type' => 'like']);

        $parameterCounter = 100;

        set_error_handler(static function ($severity, $message) {
            throw new \ErrorException($message, 0, $severity);
        }, \E_DEPRECATED | \E_USER_DEPRECATED);

        try {
            $andExpr = $filter->addAndExpression(new Andx(), $this->getQueryBuilderMock(), 'post.title', 'foo', null, $parameterCounter);
        } finally {
            restore_error_handler();
        }

        // a null type of field is not a StringExpression, so 'like' stays 'like' only
        // because the type is unknown - what matters here is that it did not blow up
        static::assertSame(1, $andExpr->count());
    }

    private function getQueryBuilderMock(): QueryBuilder
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $qb = $this->createMock(QueryBuilder::class);
        /** @noinspection PhpUndefinedMethodInspection */
        $qb->method('expr')->willReturn(new Expr());

        return $qb;
    }
}
