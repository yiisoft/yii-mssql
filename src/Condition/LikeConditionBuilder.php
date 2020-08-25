<?php

declare(strict_types=1);

namespace Yiisoft\Db\Mssql\Condition;

use Yiisoft\Db\Query\Conditions\LikeConditionBuilder as AbstractLikeConditionBuilder;

final class LikeConditionBuilder extends AbstractLikeConditionBuilder
{
    /**
     * {@inheritdoc}
     */
    protected $escapingReplacements = [
        '%' => '[%]',
        '_' => '[_]',
        '[' => '[[]',
        ']' => '[]]',
        '\\' => '[\\]',
    ];
}