<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Db;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Db\Paginator;

class PaginatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Paginator::reset();
        parent::tearDown();
    }

    // ======================== 构造 & 数据访问 ========================

    public function testBasicAccessors(): void
    {
        $items = [['id' => 1], ['id' => 2], ['id' => 3]];
        $paginator = new Paginator($items, total: 30, pageSize: 10, currentPage: 1);

        $this->assertSame($items, $paginator->items());
        $this->assertSame(30, $paginator->total());
        $this->assertSame(10, $paginator->perPage());
        $this->assertSame(1, $paginator->currentPage());
    }

    public function testMakeDelegatesToConstructor(): void
    {
        $items = [['id' => 1]];
        $paginator = Paginator::make($items, total: 5, pageSize: 2, currentPage: 3);

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertSame($items, $paginator->items());
        $this->assertSame(3, $paginator->currentPage());
    }

    public function testMakeUsesCustomMaker(): void
    {
        $called = false;
        Paginator::maker(function ($items, $total, $pageSize, $currentPage, $simple, $hasMore) use (&$called) {
            $called = true;
            return new Paginator($items, $total, $pageSize, $currentPage, $simple, $hasMore);
        });

        $paginator = Paginator::make([], total: 0, pageSize: 10, currentPage: 1);

        $this->assertTrue($called);
        $this->assertInstanceOf(Paginator::class, $paginator);
    }

    // ======================== lastPage ========================

    public function testLastPageCalculatesCorrectly(): void
    {
        $paginator = new Paginator([], total: 25, pageSize: 10, currentPage: 1);
        $this->assertSame(3, $paginator->lastPage());
    }

    public function testLastPageExactDivision(): void
    {
        $paginator = new Paginator([], total: 20, pageSize: 10, currentPage: 1);
        $this->assertSame(2, $paginator->lastPage());
    }

    public function testLastPageWithZeroTotal(): void
    {
        $paginator = new Paginator([], total: 0, pageSize: 10, currentPage: 1);
        $this->assertSame(1, $paginator->lastPage());
    }

    public function testLastPageSimpleModeReturnsCurrentPage(): void
    {
        $paginator = new Paginator([], total: 100, pageSize: 10, currentPage: 3, simple: true, hasMore: true);
        $this->assertSame(3, $paginator->lastPage());
    }

    // ======================== 状态判断 ========================

    public function testHasPagesFalseWhenSinglePage(): void
    {
        $paginator = new Paginator([['id' => 1]], total: 5, pageSize: 10, currentPage: 1);
        $this->assertFalse($paginator->hasPages());
    }

    public function testHasPagesTrueWhenMultiplePages(): void
    {
        $paginator = new Paginator([], total: 25, pageSize: 10, currentPage: 1);
        $this->assertTrue($paginator->hasPages());
    }

    public function testHasPagesSimpleModeOnFirstPage(): void
    {
        $paginator = new Paginator([], total: 0, pageSize: 10, currentPage: 1, simple: false);
        $this->assertFalse($paginator->hasPages());
    }

    public function testHasPagesSimpleModeWithMore(): void
    {
        $paginator = new Paginator([], total: 0, pageSize: 10, currentPage: 1, simple: true, hasMore: true);
        $this->assertTrue($paginator->hasPages());
    }

    public function testHasPagesSimpleModePreviousPage(): void
    {
        $paginator = new Paginator([], total: 0, pageSize: 10, currentPage: 2, simple: true, hasMore: false);
        $this->assertTrue($paginator->hasPages());
    }

    public function testHasMorePagesFalseOnLastPage(): void
    {
        $paginator = new Paginator([], total: 15, pageSize: 10, currentPage: 2);
        $this->assertFalse($paginator->hasMorePages());
    }

    public function testHasMorePagesTrueNotLastPage(): void
    {
        $paginator = new Paginator([], total: 25, pageSize: 10, currentPage: 1);
        $this->assertTrue($paginator->hasMorePages());
    }

    public function testHasMorePagesSimpleModeTrue(): void
    {
        $paginator = new Paginator([], total: 0, pageSize: 10, currentPage: 1, simple: true, hasMore: true);
        $this->assertTrue($paginator->hasMorePages());
    }

    public function testHasMorePagesSimpleModeFalse(): void
    {
        $paginator = new Paginator([], total: 0, pageSize: 10, currentPage: 1, simple: true, hasMore: false);
        $this->assertFalse($paginator->hasMorePages());
    }

    public function testOnFirstPageTrueWhenPageOne(): void
    {
        $paginator = new Paginator([], total: 25, pageSize: 10, currentPage: 1);
        $this->assertTrue($paginator->onFirstPage());
    }

    public function testOnFirstPageTrueWhenPageLessThanOne(): void
    {
        $paginator = new Paginator([], total: 25, pageSize: 10, currentPage: 0);
        $this->assertTrue($paginator->onFirstPage());
    }

    public function testOnFirstPageFalseWhenPageTwo(): void
    {
        $paginator = new Paginator([], total: 25, pageSize: 10, currentPage: 2);
        $this->assertFalse($paginator->onFirstPage());
    }

    public function testOnLastPageTrueOnLastPage(): void
    {
        $paginator = new Paginator([], total: 15, pageSize: 10, currentPage: 2);
        $this->assertTrue($paginator->onLastPage());
    }

    public function testOnLastPageFalseNotLastPage(): void
    {
        $paginator = new Paginator([], total: 25, pageSize: 10, currentPage: 1);
        $this->assertFalse($paginator->onLastPage());
    }

    public function testOnLastPageSimpleModeReturnsTrueWhenNoMore(): void
    {
        $paginator = new Paginator([], total: 0, pageSize: 10, currentPage: 3, simple: true, hasMore: false);
        $this->assertTrue($paginator->onLastPage());
    }

    public function testOnLastPageSimpleModeReturnsFalseWhenMore(): void
    {
        $paginator = new Paginator([], total: 0, pageSize: 10, currentPage: 3, simple: true, hasMore: true);
        $this->assertFalse($paginator->onLastPage());
    }

    // ======================== 序列化 ========================

    public function testToArray(): void
    {
        $items = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
        $paginator = new Paginator($items, total: 20, pageSize: 2, currentPage: 3);

        $array = $paginator->toArray();

        $this->assertSame(20, $array['total']);
        $this->assertSame(2, $array['pageSize']);
        $this->assertSame(3, $array['currentPage']);
        $this->assertSame(10, $array['lastPage']);
        $this->assertTrue($array['hasMore']);
        $this->assertSame($items, $array['items']);
    }

    public function testToArrayOnLastPageHasMoreFalse(): void
    {
        $paginator = new Paginator([['id' => 1]], total: 15, pageSize: 10, currentPage: 2);

        $array = $paginator->toArray();

        $this->assertFalse($array['hasMore']);
    }

    public function testToArrayWithModelItems(): void
    {
        $mock = $this->createMock(\yuandian\Database\Model\Model::class);
        $mock->expects($this->once())->method('toArray')->willReturn(['id' => 1, 'name' => 'Alice']);

        $paginator = new Paginator([$mock], total: 1, pageSize: 10, currentPage: 1);

        $array = $paginator->toArray();

        $this->assertSame(['id' => 1, 'name' => 'Alice'], $array['items'][0]);
    }

    public function testToArrayWithNonModelItems(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $paginator = new Paginator($items, total: 2, pageSize: 10, currentPage: 1);

        $array = $paginator->toArray();

        $this->assertSame($items, $array['items']);
    }

    public function testToJson(): void
    {
        $items = [['id' => 1, 'name' => 'Alice']];
        $paginator = new Paginator($items, total: 1, pageSize: 10, currentPage: 1);

        $json = $paginator->toJson();
        $decoded = json_decode($json, true);

        $this->assertSame(1, $decoded['total']);
        $this->assertSame($items, $decoded['items']);
    }

    public function testJsonSerialize(): void
    {
        $items = [['id' => 1]];
        $paginator = new Paginator($items, total: 1, pageSize: 10, currentPage: 1);

        $serialized = $paginator->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertSame(1, $serialized['total']);
        $this->assertSame($items, $serialized['items']);
    }

    public function testJsonEncode(): void
    {
        $items = [['id' => 1]];
        $paginator = new Paginator($items, total: 1, pageSize: 10, currentPage: 1);

        $json = json_encode($paginator);
        $decoded = json_decode($json, true);

        $this->assertSame(1, $decoded['total']);
    }

    // ======================== Countable ========================

    public function testCount(): void
    {
        $items = [['id' => 1], ['id' => 2], ['id' => 3]];
        $paginator = new Paginator($items, total: 30, pageSize: 10, currentPage: 1);

        $this->assertCount(3, $paginator);
        $this->assertSame(3, $paginator->count());
    }

    public function testCountEmpty(): void
    {
        $paginator = new Paginator([], total: 0, pageSize: 10, currentPage: 1);

        $this->assertCount(0, $paginator);
    }

    // ======================== IteratorAggregate ========================

    public function testForeach(): void
    {
        $items = [['id' => 1], ['id' => 2], ['id' => 3]];
        $paginator = new Paginator($items, total: 30, pageSize: 10, currentPage: 1);

        $collected = [];
        foreach ($paginator as $item) {
            $collected[] = $item;
        }

        $this->assertSame($items, $collected);
    }

    public function testForeachEmpty(): void
    {
        $paginator = new Paginator([], total: 0, pageSize: 10, currentPage: 1);

        $collected = [];
        foreach ($paginator as $item) {
            $collected[] = $item;
        }

        $this->assertEmpty($collected);
    }

    // ======================== ArrayAccess ========================

    public function testOffsetExists(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $paginator = new Paginator($items, total: 2, pageSize: 10, currentPage: 1);

        $this->assertTrue(isset($paginator[0]));
        $this->assertTrue(isset($paginator[1]));
        $this->assertFalse(isset($paginator[2]));
    }

    public function testOffsetGet(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $paginator = new Paginator($items, total: 2, pageSize: 10, currentPage: 1);

        $this->assertSame(['id' => 1], $paginator[0]);
        $this->assertSame(['id' => 2], $paginator[1]);
        $this->assertNull($paginator[999]);
    }

    public function testOffsetSet(): void
    {
        $items = [['id' => 1]];
        $paginator = new Paginator($items, total: 1, pageSize: 10, currentPage: 1);

        $paginator[1] = ['id' => 2];

        $this->assertSame(['id' => 2], $paginator[1]);
        $this->assertCount(2, $paginator);
    }

    public function testOffsetUnset(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $paginator = new Paginator($items, total: 2, pageSize: 10, currentPage: 1);

        unset($paginator[0]);

        $this->assertNull($paginator[0]);
        $this->assertCount(1, $paginator);
    }

    public function testArrayAccessSyntax(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $paginator = new Paginator($items, total: 2, pageSize: 10, currentPage: 1);

        $first = $paginator[0];
        $this->assertSame(['id' => 1], $first);
    }

    // ======================== currentPageResolver ========================

    public function testGetCurrentPageDefault(): void
    {
        $page = Paginator::getCurrentPage('page', 1);
        $this->assertSame(1, $page);
    }

    public function testGetCurrentPageCustomDefault(): void
    {
        $page = Paginator::getCurrentPage('page', 5);
        $this->assertSame(5, $page);
    }

    public function testGetCurrentPageUsesResolver(): void
    {
        Paginator::currentPageResolver(function ($varPage) {
            return 42;
        });

        $page = Paginator::getCurrentPage('page');
        $this->assertSame(42, $page);
    }

    public function testGetCurrentPageResolverReceivesVarName(): void
    {
        $receivedVar = null;
        Paginator::currentPageResolver(function ($varPage) use (&$receivedVar) {
            $receivedVar = $varPage;
            return 1;
        });

        Paginator::getCurrentPage('p');
        $this->assertSame('p', $receivedVar);
    }

    // ======================== reset ========================

    public function testResetClearsResolvers(): void
    {
        Paginator::currentPageResolver(fn() => 99);
        Paginator::maker(fn() => new Paginator([], 0, 10, 1));

        Paginator::reset();

        $page = Paginator::getCurrentPage('page', 1);
        $this->assertSame(1, $page);

        $paginator = Paginator::make([], total: 0, pageSize: 10, currentPage: 1);
        $this->assertInstanceOf(Paginator::class, $paginator);
    }

    // ======================== 边界情况 ========================

    public function testEmptyItems(): void
    {
        $paginator = new Paginator([], total: 0, pageSize: 10, currentPage: 1);

        $this->assertEmpty($paginator->items());
        $this->assertSame(0, $paginator->total());
        $this->assertSame(1, $paginator->lastPage());
        $this->assertFalse($paginator->hasPages());
        $this->assertFalse($paginator->hasMorePages());
        $this->assertTrue($paginator->onFirstPage());
        $this->assertTrue($paginator->onLastPage());
    }

    public function testSingleItem(): void
    {
        $paginator = new Paginator([['id' => 1]], total: 1, pageSize: 10, currentPage: 1);

        $this->assertCount(1, $paginator);
        $this->assertSame(1, $paginator->lastPage());
        $this->assertFalse($paginator->hasPages());
        $this->assertFalse($paginator->hasMorePages());
        $this->assertTrue($paginator->onFirstPage());
        $this->assertTrue($paginator->onLastPage());
    }

    public function testExactlyOnePage(): void
    {
        $items = array_map(fn($i) => ['id' => $i], range(1, 10));
        $paginator = new Paginator($items, total: 10, pageSize: 10, currentPage: 1);

        $this->assertSame(1, $paginator->lastPage());
        $this->assertFalse($paginator->hasPages());
        $this->assertFalse($paginator->hasMorePages());
        $this->assertTrue($paginator->onFirstPage());
        $this->assertTrue($paginator->onLastPage());
    }

    public function testLargeTotal(): void
    {
        $paginator = new Paginator([], total: 1000, pageSize: 10, currentPage: 50);

        $this->assertSame(100, $paginator->lastPage());
        $this->assertTrue($paginator->hasPages());
        $this->assertTrue($paginator->hasMorePages());
        $this->assertFalse($paginator->onFirstPage());
        $this->assertFalse($paginator->onLastPage());
    }

    public function testPageSizeOne(): void
    {
        $paginator = new Paginator([['id' => 5]], total: 100, pageSize: 1, currentPage: 50);

        $this->assertSame(100, $paginator->lastPage());
        $this->assertTrue($paginator->hasPages());
        $this->assertTrue($paginator->hasMorePages());
    }

    public function testTotalLessThanPageSize(): void
    {
        $paginator = new Paginator([['id' => 1]], total: 3, pageSize: 10, currentPage: 1);

        $this->assertSame(1, $paginator->lastPage());
        $this->assertFalse($paginator->hasPages());
        $this->assertFalse($paginator->hasMorePages());
    }
}
