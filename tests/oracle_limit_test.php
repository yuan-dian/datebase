<?php

declare(strict_types=1);

// Oracle parseLimit OFFSET/FETCH 顺序回归测试（Oracle 12c+ 要求 OFFSET 在前）
// 纯 SQL 生成验证——用 Sqlite 连接实例化 Oracle builder，无需真实 Oracle 服务
// Oracle 12c+ 要求 OFFSET 在 FETCH 前，否则 ORA-00933

require __DIR__ . '/../vendor/autoload.php';

use yuandian\Database\Db\Builder\Oracle;
use yuandian\Database\Db\Connector\Sqlite;
use yuandian\Database\Db\State\QueryState;

$failures = 0;
function check(bool $cond, string $label): void
{
    global $failures;
    echo ($cond ? '  PASS: ' : '  FAIL: ') . $label . "\n";
    if (!$cond) {
        $failures++;
    }
}

$conn = new Sqlite(['type' => 'sqlite', 'database' => ':memory:']);
// Connection 尚未 implements QueryContext，用匿名类委托连接实现最小契约
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
$builder = new Oracle($context);

function buildSelect(Oracle $builder, array $overrides = []): string
{
    $options = array_merge([
        'table' => 'users',
        'alias' => '',
        'field' => ['*'],
        'join' => [],
        'order' => [],
        'group' => [],
        'having' => [],
        'lock' => false,
        'union' => [],
        'comment' => '',
    ], $overrides);

    $state = new QueryState();
    $state->table = $options['table'];
    $state->alias = $options['alias'];
    $state->field = $options['field'];
    $state->join = $options['join'];
    $state->order = $options['order'];
    $state->group = $options['group'];
    $state->having = $options['having'];
    $state->lock = $options['lock'];
    $state->union = $options['union'];
    $state->comment = $options['comment'];
    $state->limit = $options['limit'] ?? null;
    $state->offset = $options['offset'] ?? null;

    return $builder->compileSelect($state)->statement;
}

// ===================== 测试 1: limit + offset 同时存在，OFFSET 必须在 FETCH 前 =====================

echo "\n== 测试 1: limit + offset，OFFSET 在 FETCH 前 ==\n";

$sql1 = buildSelect($builder, ['limit' => 10, 'offset' => 5]);
check(
    strpos($sql1, 'OFFSET 5 ROWS') !== false && strpos($sql1, 'FETCH NEXT 10 ROWS ONLY') !== false,
    'SQL 同时包含 OFFSET 5 ROWS 与 FETCH NEXT 10 ROWS ONLY'
);
check(
    strpos($sql1, 'OFFSET 5 ROWS') < strpos($sql1, 'FETCH NEXT 10 ROWS ONLY'),
    'OFFSET 位于 FETCH 之前（Oracle 要求顺序）'
);
check(
    str_contains($sql1, 'SELECT * FROM "USERS"') ,
    'Oracle 标识符大写双引号包裹'
);
echo "  SQL: $sql1\n";

// ===================== 测试 2: 仅 limit（无 offset） =====================

echo "\n== 测试 2: 仅 limit ==\n";

$sql2 = buildSelect($builder, ['limit' => 10]);
check(
    str_contains($sql2, 'FETCH NEXT 10 ROWS ONLY') && !str_contains($sql2, 'OFFSET'),
    '仅 FETCH NEXT 10 ROWS ONLY，无 OFFSET 段'
);
echo "  SQL: $sql2\n";

// ===================== 测试 3: 仅 offset（无 limit） =====================

echo "\n== 测试 3: 仅 offset ==\n";

$sql3 = buildSelect($builder, ['offset' => 5]);
check(
    str_contains($sql3, 'OFFSET 5 ROWS') && !str_contains($sql3, 'FETCH'),
    '仅 OFFSET 5 ROWS，无 FETCH 段'
);
echo "  SQL: $sql3\n";

// ===================== 测试 4: 无 limit/offset（全表查询不受影响） =====================

echo "\n== 测试 4: 无分页 ==\n";

$sql4 = buildSelect($builder);
check(
    !str_contains($sql4, 'OFFSET') && !str_contains($sql4, 'FETCH'),
    '无分页时无 OFFSET/FETCH 段'
);
echo "  SQL: $sql4\n";

// ===================== 汇总 =====================

echo "\n" . ($failures === 0 ? 'ALL PASS' : "$failures FAILED") . "\n";
exit($failures === 0 ? 0 : 1);