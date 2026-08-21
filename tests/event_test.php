<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Facade\DB;
use yuandian\Database\Model\Model;

// ===================== 测试模型 =====================

#[Table('event_user')]
class EventUser extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public string $name = '';

    public string $slug = '';

    protected function onBeforeInsert(): void
    {
        $this->slug = strtolower(str_replace(' ', '-', $this->name));
    }

    protected function onBeforeDelete()
    {
        if ($this->name === 'protected_user') {
            return false;
        }
    }
}

// ===================== 断言工具 =====================

$GLOBALS['failures'] = 0;

function check(bool $cond, string $label): void
{
    if ($cond) {
        echo "  PASS: {$label}\n";
    } else {
        echo "  FAIL: {$label}\n";
        $GLOBALS['failures']++;
    }
}

function section(string $title): void
{
    echo "\n=== {$title} ===\n";
}

// ===================== 初始化 =====================

DB::setConfig([
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'type' => 'sqlite',
            'database' => ':memory:',
        ],
    ],
]);

$conn = DB::connect('sqlite');
$conn->execute('CREATE TABLE event_user (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL DEFAULT \'\')');

// ===================== 测试 =====================

section('1. onBeforeInsert 自动生成 slug');
EventUser::resetEvents();
$user = new EventUser();
$user->name = 'Hello World';
$user->save();
check($user->slug === 'hello-world', 'slug 自动生成');
$fetched = EventUser::where('id', '=', $user->id)->find();
check($fetched?->slug === 'hello-world', '数据库中 slug 正确');

section('2. onAfterInsert 触发');
$afterInsertFired = false;
EventUser::listen('afterInsert', function ($model) use (&$afterInsertFired) {
    $afterInsertFired = true;
});
$user2 = new EventUser();
$user2->name = 'After Insert Test';
$user2->save();
check($afterInsertFired, 'afterInsert 监听器触发');

section('3. onBeforeUpdate');
$beforeUpdateFired = false;
EventUser::listen('beforeUpdate', function ($model) use (&$beforeUpdateFired) {
    $beforeUpdateFired = true;
});
$user2->name = 'Updated';
$user2->save();
check($beforeUpdateFired, 'beforeUpdate 监听器触发');

section('4. onBeforeDelete 阻止删除');
EventUser::resetEvents();
$protectedUser = new EventUser();
$protectedUser->name = 'protected_user';
$protectedUser->save();
$deleted = $protectedUser->delete();
check($deleted === false, '删除被阻止');
$fetchedProtected = EventUser::where('id', '=', $protectedUser->id)->find();
check($fetchedProtected !== null, '用户未被删除');

section('5. onBeforeDelete 允许删除');
$normalUser = new EventUser();
$normalUser->name = 'normal_user';
$normalUser->save();
$deleted = $normalUser->delete();
check($deleted === true, '删除成功');
check($normalUser->exists() === false, 'exists 置 false');

section('6. listen() 全局监听器');
EventUser::resetEvents();
$log = [];
EventUser::listen('afterInsert', function (EventUser $model) use (&$log) {
    $log[] = 'inserted:' . $model->name;
});
$loggedUser = new EventUser();
$loggedUser->name = 'Logged User';
$loggedUser->save();
check($log === ['inserted:Logged User'], '全局监听器收到事件');

section('7. listen() 阻止操作');
EventUser::resetEvents();
EventUser::listen('beforeInsert', function (EventUser $model) {
    if ($model->name === 'blocked') {
        return false;
    }
});
$blockedUser = new EventUser();
$blockedUser->name = 'blocked';
$blocked = $blockedUser->save();
check($blocked === false, '操作被全局监听器阻止');

section('8. resetEvents() 清理');
EventUser::resetEvents();
$afterInsertFired2 = false;
EventUser::listen('afterInsert', function ($model) use (&$afterInsertFired2) {
    $afterInsertFired2 = true;
});
EventUser::resetEvents();
$resetUser = new EventUser();
$resetUser->name = 'Reset Test';
$resetUser->save();
check($afterInsertFired2 === false, '清理后监听器不再触发');

section('9. save() 路由验证');
EventUser::resetEvents();
$events = [];
EventUser::listen('beforeInsert', function ($model) use (&$events) {
    $events[] = 'beforeInsert';
});
EventUser::listen('afterInsert', function ($model) use (&$events) {
    $events[] = 'afterInsert';
});
EventUser::listen('beforeUpdate', function ($model) use (&$events) {
    $events[] = 'beforeUpdate';
});
EventUser::listen('afterUpdate', function ($model) use (&$events) {
    $events[] = 'afterUpdate';
});
$routeUser = new EventUser();
$routeUser->name = 'Route Test';
$routeUser->save();
check($events === ['beforeInsert', 'afterInsert'], 'save 新增只触发 insert 事件');

section('10. afterRead 触发');
EventUser::resetEvents();
$afterReadFired = false;
$readModelName = '';
EventUser::listen('afterRead', function (EventUser $model) use (&$afterReadFired, &$readModelName) {
    $afterReadFired = true;
    $readModelName = $model->name;
});
$readUser = EventUser::where('id', '=', $loggedUser->id)->find();
check($afterReadFired, 'afterRead 触发');
check($readModelName === 'Logged User', 'afterRead 收到正确的模型');

section('11. afterRead select 触发');
EventUser::resetEvents();
$afterReadCount = 0;
EventUser::listen('afterRead', function ($model) use (&$afterReadCount) {
    $afterReadCount++;
});
$allUsers = EventUser::select();
check($afterReadCount === count($allUsers), 'select 每行触发 afterRead');

section('12. modelListen 模型级监听器');
EventUser::resetEvents();
$modelLog = [];
EventUser::modelListen('afterInsert', function ($model) use (&$modelLog) {
    $modelLog[] = $model->name;
});
$modelUser = new EventUser();
$modelUser->name = 'Model Listen';
$modelUser->save();
check($modelLog === ['Model Listen'], '模型级监听器触发');

section('13. getRegisteredEvents 调试');
EventUser::resetEvents();
EventUser::listen('beforeInsert', function () {});
EventUser::modelListen('afterInsert', function () {});
$registered = EventUser::getRegisteredEvents();
check(in_array('beforeInsert', $registered['global']), '全局事件注册');
check(in_array('afterInsert', $registered['model']), '模型级事件注册');

// ===================== 结果 =====================

echo "\n" . str_repeat('=', 50) . "\n";
$total = $GLOBALS['failures'];
if ($total === 0) {
    echo "ALL PASS\n";
} else {
    echo "FAILURES: {$total}\n";
    exit(1);
}
