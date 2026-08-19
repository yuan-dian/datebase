<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\model;

use yuandian\Database\Attribute\Connection;
use yuandian\Database\Attribute\HasMany;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

/**
 * MongoDB 测试模型
 *
 * 主键策略：ASSIGN_ID（snowflake 整数）+ pk_convert_id=true，
 * 应用侧预生成整数 id，MongoDB 侧存为 _id 字段（int 类型，无需 ObjectID 转换）。
 *
 * @date 2026/8/18
 * @author 原点 467490186@qq.com
 */
#[Table('mongo_test')]
#[Connection('mongodb')]
class MongoTestModel extends Model
{
    #[TableId(IdType::ASSIGN_ID)]
    public int $id = 0;

    public string $name = '';

    public int $status = 0;

    public ?string $remark = null;

    public string $createTime = '';

    public int $parentId = 0;

    #[HasMany(MongoTestModel::class, 'parentId', 'id')]
    public array $children = [];
}
