<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\model;

use yuandian\Database\Attribute\Connection;
use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

/**
 * MongoDB 软删除测试模型（独立集合，不影响 MongoTestModel 的物理删除断言）
 *
 * 验证 N7：模型层 Mongo 查询应过滤已软删数据、delete() 应走软删更新而非物理删除。
 *
 * @date 2026/8/19
 * @author 原点 467490186@qq.com
 */
#[Table('mongo_test_soft')]
#[Connection('mongodb')]
#[SoftDelete]
class MongoSoftDeleteModel extends Model
{
    #[TableId(IdType::ASSIGN_ID)]
    public int $id = 0;

    public string $name = '';

    public int $status = 0;
}
