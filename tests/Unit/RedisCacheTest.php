<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Cache\RedisCache;
use App\Cache\RedisClientInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RedisCacheTest extends TestCase
{

    #[Test]
    public function createReturnsDisconnectedInstanceWhenRedisUnavailable(): void
    {
        // Port 1 is never open; Predis will fail to connect
        $cache = RedisCache::create('127.0.0.1', 1);
        $this->assertFalse($cache->isConnected());
    }

    #[Test]
    public function isConnectedReturnsFalseWithNullClient(): void
    {
        $cache = new RedisCache(null);
        $this->assertFalse($cache->isConnected());
    }


    #[Test]
    public function getReturnsNullWhenClientIsNull(): void
    {
        $this->assertNull((new RedisCache(null))->get('any-key'));
    }

    #[Test]
    public function setDoesNothingWhenClientIsNull(): void
    {
        (new RedisCache(null))->set('key', 'value', 60);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function incrementDoesNothingWhenClientIsNull(): void
    {
        (new RedisCache(null))->increment('counter');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function hashIncrementDoesNothingWhenClientIsNull(): void
    {
        (new RedisCache(null))->hashIncrement('myhash', 'field');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function getIntReturnsZeroWhenClientIsNull(): void
    {
        $this->assertSame(0, (new RedisCache(null))->getInt('counter'));
    }

    #[Test]
    public function getAllHashReturnsEmptyArrayWhenClientIsNull(): void
    {
        $this->assertSame([], (new RedisCache(null))->getAllHash('myhash'));
    }

    /**
     * Build a connected RedisCache backed by a mock RedisClientInterface.
     *
     * @return array{RedisCache, RedisClientInterface&MockObject}
     */
    private function makeConnectedCache(): array
    {
        /** @var RedisClientInterface&MockObject $redis */
        $redis = $this->createMock(RedisClientInterface::class);
        $redis->expects($this->once())->method('ping');

        return [new RedisCache($redis), $redis];
    }

    #[Test]
    public function isConnectedReturnsTrueAfterSuccessfulPing(): void
    {
        [$cache] = $this->makeConnectedCache();
        $this->assertTrue($cache->isConnected());
    }

    #[Test]
    public function getReturnsValueFromRedis(): void
    {
        [$cache, $redis] = $this->makeConnectedCache();

        $redis->expects($this->once())->method('get')->with('mykey')->willReturn('myvalue');

        $this->assertSame('myvalue', $cache->get('mykey'));
    }

    #[Test]
    public function getReturnsNullWhenKeyMissing(): void
    {
        [$cache, $redis] = $this->makeConnectedCache();

        $redis->expects($this->once())->method('get')->with('missing')->willReturn(null);

        $this->assertNull($cache->get('missing'));
    }

    #[Test]
    public function setCallsSetexWithCorrectArguments(): void
    {
        [$cache, $redis] = $this->makeConnectedCache();

        $redis->expects($this->once())->method('setex')->with('mykey', 300, 'myvalue');

        $cache->set('mykey', 'myvalue', 300);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function setUsesDefaultTtlOf600(): void
    {
        [$cache, $redis] = $this->makeConnectedCache();

        $redis->expects($this->once())->method('setex')->with('mykey', 600, 'val');

        $cache->set('mykey', 'val');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function incrementCallsIncrOnRedis(): void
    {
        [$cache, $redis] = $this->makeConnectedCache();

        $redis->expects($this->once())->method('incr')->with('mycounter');

        $cache->increment('mycounter');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function hashIncrementCallsHincrby(): void
    {
        [$cache, $redis] = $this->makeConnectedCache();

        $redis->expects($this->once())->method('hincrby')->with('myhash', 'field', 1);

        $cache->hashIncrement('myhash', 'field');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function getIntReturnsIntegerFromRedis(): void
    {
        [$cache, $redis] = $this->makeConnectedCache();

        $redis->expects($this->once())->method('get')->with('counter')->willReturn('42');

        $this->assertSame(42, $cache->getInt('counter'));
    }

    #[Test]
    public function getAllHashReturnsHashFromRedis(): void
    {
        [$cache, $redis] = $this->makeConnectedCache();

        $expected = ['field1' => '10', 'field2' => '20'];
        $redis->expects($this->once())->method('hgetall')->with('myhash')->willReturn($expected);

        $this->assertSame($expected, $cache->getAllHash('myhash'));
    }

    #[Test]
    public function getAllHashReturnsEmptyArrayWhenRedisReturnsNull(): void
    {
        [$cache, $redis] = $this->makeConnectedCache();

        $redis->expects($this->once())->method('hgetall')->with('myhash')->willReturn(null);

        $this->assertSame([], $cache->getAllHash('myhash'));
    }

    #[Test]
    public function hashIncrementFloatDoesNothingWhenClientIsNull(): void
    {
        (new RedisCache(null))->hashIncrementFloat('myhash', 'field', 0.123);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function hashIncrementFloatCallsHincrbyFloat(): void
    {
        [$cache, $redis] = $this->makeConnectedCache();

        $redis->expects($this->once())->method('hincrbyfloat')->with('myhash', 'field', 0.123);

        $cache->hashIncrementFloat('myhash', 'field', 0.123);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function hashIncrementFloatSilentlyIgnoresRedisException(): void
    {
        [$cache, $redis] = $this->makeConnectedCache();

        $redis->expects($this->once())
            ->method('hincrbyfloat')
            ->willThrowException(new \RuntimeException('connection lost'));

        $cache->hashIncrementFloat('myhash', 'field', 0.5);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function getReturnsNullOnRedisException(): void
    {
        [$cache, $redis] = $this->makeConnectedCache();

        $redis->expects($this->once())
            ->method('get')
            ->willThrowException(new \RuntimeException('connection lost'));

        $this->assertNull($cache->get('key'));
    }

    #[Test]
    public function incrementSilentlyIgnoresRedisException(): void
    {
        [$cache, $redis] = $this->makeConnectedCache();

        $redis->expects($this->once())
            ->method('incr')
            ->willThrowException(new \RuntimeException('connection lost'));

        $cache->increment('counter');
        $this->addToAssertionCount(1);
    }
}
