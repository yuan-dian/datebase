<?php

declare(strict_types=1);

// QueryState 架构回归测试：纯内存，无 MySQL 依赖
// 用 Sqlite 连接实例化 Builder，验证 compile* 消费 QueryState 的 SQL 输出

require __DIR__ . '/../vendor/autoload.php';

use yuandian\Database\Db\Builder\Sqlite;
use yuandian\Database\Db\State\QueryState;
use yuandian\Database\Db\State\WhereCondition;
use yuandian\Database\Db\Raw;

$failures = 0;
function check(bool $cond, string $label): void
{
    global $failures;
    echo ($cond ? '  PASS: ' : '  FAIL: ') . $label . "\n";
    if (!$cond) {
        $failures++;
    }
}

$conn = new \yuandian\Database\Db\Connector\Sqlite(['type' => 'sqlite', 'database' => ':memory:']);
// Task 5 阶段 Connection 尚未 implements QueryContext（Task 7 收口），
// 用匿名类委托连接实现最小契约
$context = new class($conn) implements \yuandian\Database\Db\QueryContext {
    public function __construct(private \yuandian\Database\Db\Connection $conn) {}

    public function getTablePrefix(): string
    {
        return $this->conn->getTablePrefix();
    }

    public function newQuery(?string $table = null): \yuandian\Database\Db\BaseQuery
    {
        return $this->conn->table($table);
    }
};
$builder = new Sqlite($context);

// 1. 基础 SELECT
$s = new QueryState();
$s->table = 'users';
$c = $builder->compileSelect($s);
check($c->statement === 'SELECT * FROM "users"', 'compileSelect 基础查询: ' . $c->statement);
check($c->bind === [], '基础查询无 bind');

// 2. WHERE 三元组 + bind
$s = new QueryState();
$s->table = 'users';
$s->where->add('AND', new WhereCondition('id', '=', 5));
$s->where->add('AND', new WhereCondition('status', 'IN', [1, 2]));
$c = $builder->compileSelect($s);
check(str_contains($c->statement, '"id" = ?'), '等值条件生成占位符');
check(str_contains($c->statement, '"status" IN (?, ?)'), 'IN 条件生成占位符');
check($c->bind === [5, 1, 2], 'bind 顺序正确: ' . json_encode($c->bind));

// 3. Raw 条件
$s = new QueryState();
$s->table = 'users';
$s->where->add('AND', new WhereCondition('', 'RAW', new Raw('deleted_at IS NULL')));
$c = $builder->compileSelect($s);
check(str_contains($c->statement, 'deleted_at IS NULL'), 'Raw 条件透传');

// 4. 嵌套 WhereGroup
$s = new QueryState();
$s->table = 'users';
$inner = new \yuandian\Database\Db\State\WhereGroup();
$inner->add('AND', new WhereCondition('a', '=', 1));
$inner->add('OR', new WhereCondition('b', '=', 2));
$s->where->add('AND', new WhereCondition('', 'GROUP', $inner));
$s->where->add('AND', new WhereCondition('c', '=', 3));
$c = $builder->compileSelect($s);
check(str_contains($c->statement, '( "a" = ? OR "b" = ? )'), '嵌套组括号包裹');
check($c->bind === [1, 2, 3], '嵌套组 bind 合并');

// 5. hasCondition 幂等检查
$s = new QueryState();
$s->table = 'users';
$s->where->add('AND', new WhereCondition('deleted_time', 'NULL', ''));
check($s->where->hasCondition('deleted_time', 'NULL') === true, 'hasCondition 命中');
check($s->where->hasCondition('deleted_time', 'NOT NULL') === false, 'hasCondition 未命中');

// 6. copy() 隔离
$s = new QueryState();
$s->table = 'users';
$s->where->add('AND', new WhereCondition('id', '=', 1));
$copy = $s->copy();
$copy->where->add('AND', new WhereCondition('x', '=', 2));
check(count($s->where->and) === 1, 'copy() 后原 state 不被污染');

echo $failures > 0 ? "\n$failures 个断言失败\n" : "\n全部通过\n";
exit($failures > 0 ? 1 : 0);