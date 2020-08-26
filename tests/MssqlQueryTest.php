<?php

declare(strict_types=1);

namespace Yiisoft\Db\Mssql\Tests;

use Yiisoft\Db\Query\Query;
use Yiisoft\Db\TestUtility\TestQueryTrait;

/**
 * @group mssql
 */
final class MssqlQueryTest extends TestCase
{
    use TestQueryTrait;

    public function testUnion(): void
    {
        $connection = $this->getConnection();

        /* MSSQL supports limit only in sub queries with UNION */
        $query = (new Query($connection))
            ->select(['id', 'name'])
            ->from(
                (new Query($connection))
                    ->select(['id', 'name'])
                    ->from('item')
                    ->limit(2)
            )
            ->union(
                (new Query($connection))
                    ->select(['id', 'name'])
                    ->from(
                        (new Query($connection))
                            ->select(['id', 'name'])
                            ->from(['category'])
                            ->limit(2)
                    )
            );

        $result = $query->all();

        $this->assertNotEmpty($result);
        $this->assertCount(4, $result);
    }
}