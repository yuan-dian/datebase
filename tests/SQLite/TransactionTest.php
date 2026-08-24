<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\Post;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();

        $this->createTable('CREATE TABLE test_post (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL DEFAULT "",
            body TEXT NOT NULL DEFAULT "",
            test_user_id INTEGER NOT NULL DEFAULT 0
        )');
    }

    public function testClosureTransactionCommit(): void
    {
        $result = DB::transaction(function () {
            $post = new Post();
            $post->title = '事务文章';
            $post->body = '事务内容';
            $post->save();
            return $post->id;
        });

        $this->assertGreaterThan(0, $result);
        $rows = $this->queryTable('test_post');
        $this->assertCount(1, $rows);
        $this->assertSame('事务文章', $rows[0]['title']);
    }

    public function testClosureTransactionRollbackOnException(): void
    {
        try {
            DB::transaction(function () {
                $post = new Post();
                $post->title = '会回滚';
                $post->body = '内容';
                $post->save();
                throw new \RuntimeException('模拟异常');
            });
            $this->fail('应该抛出异常');
        } catch (\RuntimeException $e) {
            $this->assertSame('模拟异常', $e->getMessage());
        }

        $rows = $this->queryTable('test_post');
        $this->assertCount(0, $rows, '事务回滚后数据不应存在');
    }

    public function testClosureTransactionReturnValue(): void
    {
        $value = DB::transaction(function () {
            return 'return_value';
        });

        $this->assertSame('return_value', $value);
    }

    public function testClosureTransactionWithNullReturn(): void
    {
        $value = DB::transaction(function () {
            return null;
        });

        $this->assertNull($value);
    }

    public function testClosureTransactionMultipleOperations(): void
    {
        DB::transaction(function () {
            $post1 = new Post();
            $post1->title = '文章1';
            $post1->body = '内容1';
            $post1->save();

            $post2 = new Post();
            $post2->title = '文章2';
            $post2->body = '内容2';
            $post2->save();
        });

        $rows = $this->queryTable('test_post');
        $this->assertCount(2, $rows);
    }

    public function testManualTransactionCommit(): void
    {
        DB::startTrans();
        try {
            $post = new Post();
            $post->title = '手动事务';
            $post->body = '内容';
            $post->save();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }

        $rows = $this->queryTable('test_post');
        $this->assertCount(1, $rows);
        $this->assertSame('手动事务', $rows[0]['title']);
    }

    public function testManualTransactionRollback(): void
    {
        DB::startTrans();
        try {
            $post = new Post();
            $post->title = '回滚';
            $post->body = '内容';
            $post->save();
            DB::rollback();
        } catch (\Throwable $e) {
            DB::rollback();
            throw $e;
        }

        $rows = $this->queryTable('test_post');
        $this->assertCount(0, $rows, '手动回滚后数据不应存在');
    }

    public function testManualTransactionCommitWithoutOperations(): void
    {
        DB::startTrans();
        DB::commit();

        $rows = $this->queryTable('test_post');
        $this->assertCount(0, $rows);
    }

    public function testManualTransactionRollbackWithoutStart(): void
    {
        DB::rollback();
        $rows = $this->queryTable('test_post');
        $this->assertCount(0, $rows);
    }

    public function testNestedStartTransCommitOuter(): void
    {
        DB::startTrans();
        DB::startTrans();

        $post = new Post();
        $post->title = '嵌套';
        $post->body = '内容';
        $post->save();

        DB::commit();
        DB::commit();

        $rows = $this->queryTable('test_post');
        $this->assertCount(1, $rows);
    }

    public function testNestedStartTransRollbackAll(): void
    {
        DB::startTrans();
        DB::startTrans();

        $post = new Post();
        $post->title = '嵌套回滚';
        $post->body = '内容';
        $post->save();

        DB::rollback();
        DB::rollback();

        $rows = $this->queryTable('test_post');
        $this->assertCount(0, $rows);
    }

    public function testNestedStartTransInnerRollbackOuterCommit(): void
    {
        DB::startTrans();

        $post1 = new Post();
        $post1->title = '外层';
        $post1->body = '内容';
        $post1->save();

        DB::startTrans();

        $post2 = new Post();
        $post2->title = '内层';
        $post2->body = '内容';
        $post2->save();

        DB::rollback();

        DB::commit();

        // SQLite supportSavepoint=false, inner rollback won't actually rollback — both rows persist
        $rows = $this->queryTable('test_post');
        $this->assertCount(2, $rows);
    }

    public function testTransactionDataVisibleWithinSameTransaction(): void
    {
        DB::startTrans();

        $post = new Post();
        $post->title = '可见';
        $post->body = '内容';
        $post->save();

        $found = DB::table('test_post')->where('id', '=', $post->id)->find();
        $this->assertNotNull($found);
        $this->assertSame('可见', $found['title']);

        DB::commit();
    }

    public function testSecondTransactionSeesNothingUntilCommit(): void
    {
        $rows = $this->queryTable('test_post');
        $this->assertCount(0, $rows);

        DB::startTrans();
        $post = new Post();
        $post->title = '未提交';
        $post->body = '内容';
        $post->save();
        DB::rollback();

        $rows = $this->queryTable('test_post');
        $this->assertCount(0, $rows, '回滚后不应有数据');
    }

    public function testTransTimesStaysNonNegative(): void
    {
        DB::rollback();
        DB::rollback();
        DB::rollback();

        DB::startTrans();
        $post = new Post();
        $post->title = '正常';
        $post->body = '内容';
        $post->save();
        DB::commit();

        $rows = $this->queryTable('test_post');
        $this->assertCount(1, $rows);
    }

    public function testCanStartNewTransactionAfterExceptionInClosure(): void
    {
        try {
            DB::transaction(function () {
                throw new \RuntimeException('第一个事务异常');
            });
        } catch (\RuntimeException $e) {
        }

        $result = DB::transaction(function () {
            $post = new Post();
            $post->title = '恢复';
            $post->body = '内容';
            $post->save();
            return true;
        });

        $this->assertTrue($result);
        $rows = $this->queryTable('test_post');
        $this->assertCount(1, $rows);
    }

    public function testMultipleConsecutiveTransactions(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            DB::transaction(function () use ($i) {
                $post = new Post();
                $post->title = "事务{$i}";
                $post->body = "内容{$i}";
                $post->save();
            });
        }

        $rows = $this->queryTable('test_post');
        $this->assertCount(5, $rows);

        $titles = array_column($rows, 'title');
        for ($i = 1; $i <= 5; $i++) {
            $this->assertContains("事务{$i}", $titles);
        }
    }
}
