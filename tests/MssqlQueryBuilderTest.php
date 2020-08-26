<?php

declare(strict_types=1);

namespace Yiisoft\Db\Mssql\Tests;

use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Mssql\Query\MssqlQueryBuilder;
use Yiisoft\Db\TestUtility\TraversableObject;
use Yiisoft\Db\TestUtility\TestQueryBuilderTrait;

/**
 * @group mssql
 */
final class MssqlQueryBuilderTest extends TestCase
{
    use TestQueryBuilderTrait;

    /**
     * @var string ` ESCAPE 'char'` part of a LIKE condition SQL.
     */
    protected string $likeEscapeCharSql = '';

    /**
     * @var array map of values to their replacements in LIKE query params.
     */
    protected $likeParameterReplacements = [
        '\%' => '[%]',
        '\_' => '[_]',
        '[' => '[[]',
        ']' => '[]]',
        '\\\\' => '[\\]',
    ];

    /**
     * @return MssqlQueryBuilder
     */
    protected function getQueryBuilder(): MssqlQueryBuilder
    {
        return new MssqlQueryBuilder($this->getConnection());
    }

    protected function getCommmentsFromTable(string $table): array
    {
        $db = $this->getConnection();

        $sql = "SELECT *
            FROM fn_listextendedproperty (
                N'MS_description',
                'SCHEMA', N'dbo',
                'TABLE', N" . $db->quoteValue($table) . ",
                DEFAULT, DEFAULT
        )";

        return $db->createCommand($sql)->queryAll();
    }

    protected function getCommentsFromColumn(string $table, string $column): array
    {
        $db = $this->getConnection(false, false);

        $sql = "SELECT *
            FROM fn_listextendedproperty (
                N'MS_description',
                'SCHEMA', N'dbo',
                'TABLE', N" . $db->quoteValue($table) . ",
                'COLUMN', N" . $db->quoteValue($column) . "
        )";

        return $db->createCommand($sql)->queryAll();
    }

    protected function runAddCommentOnTable(string $comment, string $table)
    {
        $qb = $this->getQueryBuilder();

        $db = $this->getConnection();

        $sql = $qb->addCommentOnTable($table, $comment);

        return $db->createCommand($sql)->execute();
    }

    protected function runAddCommentOnColumn(string $comment, string $table, string $column)
    {
        $qb = $this->getQueryBuilder();

        $db = $this->getConnection();

        $sql = $qb->addCommentOnColumn($table, $column, $comment);

        return $db->createCommand($sql)->execute();
    }

    protected function runDropCommentFromTable(string $table)
    {
        $qb = $this->getQueryBuilder();

        $db = $this->getConnection();

        $sql = $qb->dropCommentFromTable($table);

        return $db->createCommand($sql)->execute();
    }

    protected function runDropCommentFromColumn(string $table, string $column)
    {
        $qb = $this->getQueryBuilder();

        $db = $this->getConnection();

        $sql = $qb->dropCommentFromColumn($table, $column);

        return $db->createCommand($sql)->execute();
    }

    public function testOffsetLimit(): void
    {
        $expectedQuerySql = 'SELECT [id] FROM [example] ORDER BY (SELECT NULL) OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY';
        $expectedQueryParams = [];

        $query = new Query($this->getConnection());

        $query->select('id')->from('example')->limit(10)->offset(5);

        [$actualQuerySql, $actualQueryParams] = $this->getQueryBuilder()->build($query);

        $this->assertEquals($expectedQuerySql, $actualQuerySql);
        $this->assertEquals($expectedQueryParams, $actualQueryParams);
    }

    public function testLimit(): void
    {
        $expectedQuerySql = 'SELECT [id] FROM [example] ORDER BY (SELECT NULL) OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY';
        $expectedQueryParams = [];

        $query = new Query($this->getConnection());

        $query->select('id')->from('example')->limit(10);

        [$actualQuerySql, $actualQueryParams] = $this->getQueryBuilder()->build($query);

        $this->assertEquals($expectedQuerySql, $actualQuerySql);
        $this->assertEquals($expectedQueryParams, $actualQueryParams);
    }

    public function testOffset(): void
    {
        $expectedQuerySql = 'SELECT [id] FROM [example] ORDER BY (SELECT NULL) OFFSET 10 ROWS';
        $expectedQueryParams = [];

        $query = new Query($this->getConnection());

        $query->select('id')->from('example')->offset(10);

        [$actualQuerySql, $actualQueryParams] = $this->getQueryBuilder()->build($query);

        $this->assertEquals($expectedQuerySql, $actualQuerySql);
        $this->assertEquals($expectedQueryParams, $actualQueryParams);
    }

    public function testCommentAdditionOnTableAndOnColumn(): void
    {
        $table = 'profile';
        $tableComment = 'A comment for profile table.';

        $this->runAddCommentOnTable($tableComment, $table);

        $resultTable = $this->getCommmentsFromTable($table);

        $this->assertEquals([
            'objtype' => 'TABLE',
            'objname' => $table,
            'name' => 'MS_description',
            'value' => $tableComment,
        ], $resultTable[0]);

        $column = 'description';
        $columnComment = 'A comment for description column in profile table.';

        $this->runAddCommentOnColumn($columnComment, $table, $column);

        $resultColumn = $this->getCommentsFromColumn($table, $column);

        $this->assertEquals([
            'objtype' => 'COLUMN',
            'objname' => $column,
            'name' => 'MS_description',
            'value' => $columnComment,
        ], $resultColumn[0]);

        /* Add another comment to the same table to test update */
        $tableComment2 = 'Another comment for profile table.';

        $this->runAddCommentOnTable($tableComment2, $table);

        $result = $this->getCommmentsFromTable($table);

        $this->assertEquals([
            'objtype' => 'TABLE',
            'objname' => $table,
            'name' => 'MS_description',
            'value' => $tableComment2,
        ], $result[0]);

        /* Add another comment to the same column to test update */
        $columnComment2 = 'Another comment for description column in profile table.';

        $this->runAddCommentOnColumn($columnComment2, $table, $column);

        $result = $this->getCommentsFromColumn($table, $column);

        $this->assertEquals([
            'objtype' => 'COLUMN',
            'objname' => $column,
            'name' => 'MS_description',
            'value' => $columnComment2,
        ], $result[0]);
    }

    public function testCommentAdditionOnQuotedTableOrColumn(): void
    {
        $table = 'stranger \'table';
        $tableComment = 'A comment for stranger \'table.';

        $this->runAddCommentOnTable($tableComment, $table);

        $resultTable = $this->getCommmentsFromTable($table);

        $this->assertEquals([
            'objtype' => 'TABLE',
            'objname' => $table,
            'name' => 'MS_description',
            'value' => $tableComment,
        ], $resultTable[0]);

        $column = 'stranger \'field';
        $columnComment = 'A comment for stranger \'field column in stranger \'table.';

        $this->runAddCommentOnColumn($columnComment, $table, $column);

        $resultColumn = $this->getCommentsFromColumn($table, $column);

        $this->assertEquals([
            'objtype' => 'COLUMN',
            'objname' => $column,
            'name' => 'MS_description',
            'value' => $columnComment,
        ], $resultColumn[0]);
    }

    public function testCommentRemovalFromTableAndFromColumn(): void
    {
        $table = 'profile';
        $tableComment = 'A comment for profile table.';

        $this->runAddCommentOnTable($tableComment, $table);
        $this->runDropCommentFromTable($table);

        $result = $this->getCommmentsFromTable($table);

        $this->assertEquals([], $result);

        $column = 'description';
        $columnComment = 'A comment for description column in profile table.';

        $this->runAddCommentOnColumn($columnComment, $table, $column);
        $this->runDropCommentFromColumn($table, $column);

        $result = $this->getCommentsFromColumn($table, $column);

        $this->assertEquals([], $result);
    }

    public function testCommentRemovalFromQuotedTableOrColumn(): void
    {
        $table = 'stranger \'table';
        $tableComment = 'A comment for stranger \'table.';

        $this->runAddCommentOnTable($tableComment, $table);
        $this->runDropCommentFromTable($table);

        $result = $this->getCommmentsFromTable($table);

        $this->assertEquals([], $result);

        $column = 'stranger \'field';
        $columnComment = 'A comment for stranger \'field in stranger \'table.';

        $this->runAddCommentOnColumn($columnComment, $table, $column);
        $this->runDropCommentFromColumn($table, $column);

        $result = $this->getCommentsFromColumn($table, $column);

        $this->assertEquals([], $result);
    }

    public function insertProvider(): array
    {
        $db = $this->getConnection();

        return [
            'regular-values' => [
                'customer',
                [
                    'email' => 'test@example.com',
                    'name' => 'silverfire',
                    'address' => 'Kyiv {{city}}, Ukraine',
                    'is_active' => false,
                    'related_id' => null,
                ],
                [],
                $this->replaceQuotes(
                    'SET NOCOUNT ON;DECLARE @temporary_inserted TABLE ([id] int , [email] varchar(128) , [name]'
                    . ' varchar(128) NULL, [address] text NULL, [status] int NULL, [profile_id] int NULL);'
                    . 'INSERT INTO [[customer]] ([[email]], [[name]], [[address]], [[is_active]], [[related_id]])'
                    . ' OUTPUT INSERTED.* INTO @temporary_inserted VALUES (:qp0, :qp1, :qp2, :qp3, :qp4);'
                    . 'SELECT * FROM @temporary_inserted'
                ),
                [
                    ':qp0' => 'test@example.com',
                    ':qp1' => 'silverfire',
                    ':qp2' => 'Kyiv {{city}}, Ukraine',
                    ':qp3' => false,
                    ':qp4' => null,
                ],
            ],
            'params-and-expressions' => [
                '{{%type}}',
                [
                    '{{%type}}.[[related_id]]' => null,
                    '[[time]]' => new Expression('now()'),
                ],
                [],
                'SET NOCOUNT ON;DECLARE @temporary_inserted TABLE ([int_col] int , [int_col2] int NULL, [tinyint_col]'
                . ' tinyint NULL, [smallint_col] smallint NULL, [char_col] char(100) , [char_col2] varchar(100) NULL,'
                . ' [char_col3] text NULL, [float_col] decimal , [float_col2] float NULL, [blob_col] varbinary(MAX)'
                . ' NULL, [numeric_col] decimal NULL, [time] datetime , [bool_col] tinyint , [bool_col2] tinyint NULL);'
                . 'INSERT INTO {{%type}} ({{%type}}.[[related_id]], [[time]]) OUTPUT INSERTED.*'
                . ' INTO @temporary_inserted VALUES (:qp0, now());'
                . 'SELECT * FROM @temporary_inserted',
                [
                    ':qp0' => null,
                ],
            ],
            'carry passed params' => [
                'customer',
                [
                    'email' => 'test@example.com',
                    'name' => 'sergeymakinen',
                    'address' => '{{city}}',
                    'is_active' => false,
                    'related_id' => null,
                    'col' => new Expression('CONCAT(:phFoo, :phBar)', [':phFoo' => 'foo']),
                ],
                [':phBar' => 'bar'],
                $this->replaceQuotes(
                    'SET NOCOUNT ON;DECLARE @temporary_inserted TABLE ([id] int , [email] varchar(128) , [name]'
                    . ' varchar(128) NULL, [address] text NULL, [status] int NULL, [profile_id] int NULL);'
                    . 'INSERT INTO [[customer]] ([[email]], [[name]], [[address]], [[is_active]], [[related_id]],'
                    . ' [[col]]) OUTPUT INSERTED.* INTO @temporary_inserted VALUES (:qp1, :qp2, :qp3, :qp4, :qp5,'
                    . ' CONCAT(:phFoo, :phBar));'
                    . 'SELECT * FROM @temporary_inserted'
                ),
                [
                    ':phBar' => 'bar',
                    ':qp1' => 'test@example.com',
                    ':qp2' => 'sergeymakinen',
                    ':qp3' => '{{city}}',
                    ':qp4' => false,
                    ':qp5' => null,
                    ':phFoo' => 'foo',
                ],
            ],
            'carry passed params (query)' => [
                'customer',
                (new Query($db))
                    ->select([
                        'email',
                        'name',
                        'address',
                        'is_active',
                        'related_id',
                    ])
                    ->from('customer')
                    ->where([
                        'email' => 'test@example.com',
                        'name' => 'sergeymakinen',
                        'address' => '{{city}}',
                        'is_active' => false,
                        'related_id' => null,
                        'col' => new Expression('CONCAT(:phFoo, :phBar)', [':phFoo' => 'foo']),
                    ]),
                [':phBar' => 'bar'],
                $this->replaceQuotes(
                    'SET NOCOUNT ON;DECLARE @temporary_inserted TABLE ([id] int , [email] varchar(128) , [name]'
                    . ' varchar(128) NULL, [address] text NULL, [status] int NULL, [profile_id] int NULL);'
                    . 'INSERT INTO [[customer]] ([[email]], [[name]], [[address]], [[is_active]], [[related_id]])'
                    . ' OUTPUT INSERTED.* INTO @temporary_inserted SELECT [[email]], [[name]], [[address]],'
                    . ' [[is_active]], [[related_id]] FROM [[customer]] WHERE ([[email]]=:qp1) AND ([[name]]=:qp2)'
                    . ' AND ([[address]]=:qp3) AND ([[is_active]]=:qp4) AND ([[related_id]] IS NULL)'
                    . ' AND ([[col]]=CONCAT(:phFoo, :phBar));'
                    . 'SELECT * FROM @temporary_inserted'
                ),
                [
                    ':phBar' => 'bar',
                    ':qp1' => 'test@example.com',
                    ':qp2' => 'sergeymakinen',
                    ':qp3' => '{{city}}',
                    ':qp4' => false,
                    ':phFoo' => 'foo',
                ],
            ],
        ];
    }

    public function upsertProvider(): array
    {
        $concreteData = [
            'regular values' => [
                3 => 'MERGE [T_upsert] WITH (HOLDLOCK) USING (VALUES (:qp0, :qp1, :qp2, :qp3)) AS [EXCLUDED] ([email],'
                . ' [address], [status], [profile_id]) ON ([T_upsert].[email]=[EXCLUDED].[email]) WHEN MATCHED THEN'
                . ' UPDATE SET [address]=[EXCLUDED].[address], [status]=[EXCLUDED].[status], [profile_id]'
                . '=[EXCLUDED].[profile_id] WHEN NOT MATCHED THEN INSERT ([email], [address], [status], [profile_id])'
                . ' VALUES ([EXCLUDED].[email], [EXCLUDED].[address], [EXCLUDED].[status], [EXCLUDED].[profile_id]);'
            ],

            'regular values with update part' => [
                3 => 'MERGE [T_upsert] WITH (HOLDLOCK) USING (VALUES (:qp0, :qp1, :qp2, :qp3)) AS [EXCLUDED] ([email],'
                . ' [address], [status], [profile_id]) ON ([T_upsert].[email]=[EXCLUDED].[email]) WHEN MATCHED THEN'
                . ' UPDATE SET [address]=:qp4, [status]=:qp5, [orders]=T_upsert.orders + 1 WHEN NOT MATCHED THEN'
                . ' INSERT ([email], [address], [status], [profile_id]) VALUES ([EXCLUDED].[email],'
                . ' [EXCLUDED].[address], [EXCLUDED].[status], [EXCLUDED].[profile_id]);'
            ],

            'regular values without update part' => [
                3 => 'MERGE [T_upsert] WITH (HOLDLOCK) USING (VALUES (:qp0, :qp1, :qp2, :qp3)) AS [EXCLUDED] ([email],'
                . ' [address], [status], [profile_id]) ON ([T_upsert].[email]=[EXCLUDED].[email]) WHEN NOT MATCHED THEN'
                . ' INSERT ([email], [address], [status], [profile_id]) VALUES ([EXCLUDED].[email],'
                . ' [EXCLUDED].[address], [EXCLUDED].[status], [EXCLUDED].[profile_id]);'
            ],

            'query' => [
                3 => 'MERGE [T_upsert] WITH (HOLDLOCK) USING (SELECT [email], 2 AS [status] FROM [customer] WHERE'
                . ' [name]=:qp0 ORDER BY (SELECT NULL) OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY) AS [EXCLUDED] ([email],'
                . ' [status]) ON ([T_upsert].[email]=[EXCLUDED].[email]) WHEN MATCHED THEN UPDATE SET'
                . ' [status]=[EXCLUDED].[status] WHEN NOT MATCHED THEN INSERT ([email], [status]) VALUES'
                . ' ([EXCLUDED].[email], [EXCLUDED].[status]);'
            ],

            'query with update part' => [
                3 => 'MERGE [T_upsert] WITH (HOLDLOCK) USING (SELECT [email], 2 AS [status] FROM [customer] WHERE'
                . ' [name]=:qp0 ORDER BY (SELECT NULL) OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY) AS [EXCLUDED] ([email],'
                . ' [status]) ON ([T_upsert].[email]=[EXCLUDED].[email]) WHEN MATCHED THEN UPDATE SET'
                . ' [address]=:qp1, [status]=:qp2, [orders]=T_upsert.orders + 1 WHEN NOT MATCHED THEN'
                . ' INSERT ([email], [status]) VALUES ([EXCLUDED].[email], [EXCLUDED].[status]);'
            ],

            'query without update part' => [
                3 => 'MERGE [T_upsert] WITH (HOLDLOCK) USING (SELECT [email], 2 AS [status] FROM [customer] WHERE'
                . ' [name]=:qp0 ORDER BY (SELECT NULL) OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY) AS [EXCLUDED] ([email],'
                . ' [status]) ON ([T_upsert].[email]=[EXCLUDED].[email]) WHEN NOT MATCHED THEN INSERT ([email],'
                . ' [status]) VALUES ([EXCLUDED].[email], [EXCLUDED].[status]);'
            ],

            'values and expressions' => [
                3 => 'SET NOCOUNT ON;DECLARE @temporary_inserted TABLE ([id] int , [ts] int NULL, [email] varchar(128)'
                . ' , [recovery_email] varchar(128) NULL, [address] text NULL, [status] tinyint , [orders] int ,'
                . ' [profile_id] int NULL);'
                . 'INSERT INTO {{%T_upsert}} ({{%T_upsert}}.[[email]], [[ts]]) OUTPUT INSERTED.*'
                . ' INTO @temporary_inserted VALUES (:qp0, now());'
                . 'SELECT * FROM @temporary_inserted'
            ],

            'values and expressions with update part' => [
                3 => 'SET NOCOUNT ON;DECLARE @temporary_inserted TABLE ([id] int , [ts] int NULL, [email] varchar(128)'
                . ' , [recovery_email] varchar(128) NULL, [address] text NULL, [status] tinyint , [orders] int ,'
                . ' [profile_id] int NULL);'
                . 'INSERT INTO {{%T_upsert}} ({{%T_upsert}}.[[email]], [[ts]]) OUTPUT INSERTED.*'
                . ' INTO @temporary_inserted VALUES (:qp0, now());'
                . 'SELECT * FROM @temporary_inserted'
            ],

            'values and expressions without update part' => [
                3 => 'SET NOCOUNT ON;DECLARE @temporary_inserted TABLE ([id] int , [ts] int NULL, [email] varchar(128)'
                . ' , [recovery_email] varchar(128) NULL, [address] text NULL, [status] tinyint , [orders] int ,'
                . ' [profile_id] int NULL);'
                . 'INSERT INTO {{%T_upsert}} ({{%T_upsert}}.[[email]], [[ts]]) OUTPUT INSERTED.*'
                . ' INTO @temporary_inserted VALUES (:qp0, now());'
                . 'SELECT * FROM @temporary_inserted'
            ],

            'query, values and expressions with update part' => [
                3 => 'MERGE {{%T_upsert}} WITH (HOLDLOCK) USING (SELECT :phEmail AS [email], now() AS [[time]]) AS'
                . ' [EXCLUDED] ([email], [[time]]) ON ({{%T_upsert}}.[email]=[EXCLUDED].[email]) WHEN MATCHED THEN'
                . ' UPDATE SET [ts]=:qp1, [[orders]]=T_upsert.orders + 1 WHEN NOT MATCHED THEN INSERT ([email],'
                . ' [[time]]) VALUES ([EXCLUDED].[email], [EXCLUDED].[[time]]);'
            ],

            'query, values and expressions without update part' => [
                3 => 'MERGE {{%T_upsert}} WITH (HOLDLOCK) USING (SELECT :phEmail AS [email], now() AS [[time]])'
                . ' AS [EXCLUDED] ([email], [[time]]) ON ({{%T_upsert}}.[email]=[EXCLUDED].[email]) WHEN MATCHED THEN'
                . ' UPDATE SET [ts]=:qp1, [[orders]]=T_upsert.orders + 1 WHEN NOT MATCHED THEN INSERT ([email],'
                . ' [[time]]) VALUES ([EXCLUDED].[email], [EXCLUDED].[[time]]);'
            ],
            'no columns to update' => [
                3 => 'MERGE [T_upsert_1] WITH (HOLDLOCK) USING (VALUES (:qp0)) AS [EXCLUDED] ([a]) ON'
                . ' ([T_upsert_1].[a]=[EXCLUDED].[a]) WHEN NOT MATCHED THEN INSERT ([a]) VALUES ([EXCLUDED].[a]);'
            ],
        ];

        $newData = $this->upsertProviderTrait();

        foreach ($concreteData as $testName => $data) {
            $newData[$testName] = array_replace($newData[$testName], $data);
        }

        return $newData;
    }

    public function batchInsertProvider(): array
    {
        $data = $this->batchInsertProviderTrait();

        $data['escape-danger-chars']['expected'] = "INSERT INTO [customer] ([address])"
        . " VALUES ('SQL-danger chars are escaped: ''); --')";
        $data['bool-false, bool2-null']['expected'] = 'INSERT INTO [type] ([bool_col], [bool_col2]) VALUES (0, NULL)';
        $data['bool-false, time-now()']['expected'] = 'INSERT INTO {{%type}} ({{%type}}.[[bool_col]], [[time]])'
        . ' VALUES (0, now())';

        return $data;
    }

    public function conditionProvider(): array
    {
        $data = $this->conditionProviderTrait();

        $data['composite in'] = [
            ['in', ['id', 'name'], [['id' => 1, 'name' => 'oy']]],
            '(([id] = :qp0 AND [name] = :qp1))',
            [':qp0' => 1, ':qp1' => 'oy'],
        ];
        $data['composite in using array objects'] = [
            ['in', new TraversableObject(['id', 'name']), new TraversableObject([
                ['id' => 1, 'name' => 'oy'],
                ['id' => 2, 'name' => 'yo'],
            ])],
            '(([id] = :qp0 AND [name] = :qp1) OR ([id] = :qp2 AND [name] = :qp3))',
            [':qp0' => 1, ':qp1' => 'oy', ':qp2' => 2, ':qp3' => 'yo'],
        ];

        return $data;
    }

    public function buildFromDataProvider(): array
    {
        $data = $this->buildFromDataProviderTrait();

        $data[] = ['[test]', '[[test]]'];
        $data[] = ['[test] [t1]', '[[test]] [[t1]]'];
        $data[] = ['[table.name]', '[[table.name]]'];
        $data[] = ['[table.name.with.dots]', '[[table.name.with.dots]]'];
        $data[] = ['[table name]', '[[table name]]'];
        $data[] = ['[table name with spaces]', '[[table name with spaces]]'];

        return $data;
    }
}