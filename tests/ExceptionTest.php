<?php
use PhpRedis\Redis;
use PhpRedis\Config;

final class ExceptionTest extends PHPUnit\Framework\TestCase
{
    protected static $redis;
    protected static $config;

    public static function setUpBeforeClass()
    {
        self::$config = Config::$redisConfig;
    }

    /**
     * @expectedException     \Exception
     * @expectedExceptionCode 111
     */
    public function testExceptionConn()
    {
        Config::$redisConfig = self::$config;
        Config::$redisConfig['port'] = 1234;
        try {
            self::$redis = new Redis();
            $this->fail('No exception was thrown.');
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException     \Exception
     */
    public function testExceptionPassword()
    {
        Config::$redisConfig = self::$config;
        Config::$redisConfig['password'] = 123456;
        try {
            self::$redis = new Redis();
            $this->fail('No exception was thrown.');
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException     \Exception
     */
    public function testExceptionErrorCommand()
    {
        Config::$redisConfig = self::$config;
        try {
            self::$redis = new Redis();
            self::$redis->errcommand();
            $this->fail('No exception was thrown.');
        } catch (Exception $e)
        {
            throw $e;
        }
    }

    public static function tearDownAfterClass()
    {
        Config::$redisConfig = self::$config;
    }
}
