<?php
namespace PhpRedis;

/**
 * class Redis 
 */
class Redis
{
    /**
     * Redis socket connection
     */
    private $_socket;

    /**
     * The database index, default 0, specified with the "SELECT" command
     */
    private $_database;

    /**
     * The redis command
     */
    private $_command;

    /**
     * The last return result, may be boolean, string, numeric, or list, etc.
     */
    private $_result;


    /**
     * Callback function
     */
    private $_callback;

    /**
     * @param $database 
     */
    public function __construct(int $database = 0)
    {
        $this->_database = $database;
        $this->_conn();
    }

    public function __destruct()
    {
        $this->_exec('QUIT');
        @fclose($this->_socket);
    }

    private $_cmds = [
        'DEL',
        //'DUMP',
        'EXISTS',
        'EXPIRE',
        'EXPIREAT',
        'KEYS',
        //'MIGRATE',
        'MOVE',
        //'OBJECT',
        'PERSIST',
        'PEXPIRE',
        'PEXPIREAT',
        'PTTL',
        'RANDOMKEY',
        'RENAME',
        'RENAMENX',
        //'RESTORE',
        'SORT',
        'TTL',
        'TYPE',
        //'SCAN',
        'APPEND',
        'BITCOUNT',
        'BITOP',
        'BITFIELD',
        'DECR',
        'DECRBY',
        'GET',
        'GETBIT',
        'GETRANGE',
        'GETSET',
        'INCR',
        'INCRBY',
        'INCRBYFLOAT',
        'MGET',
        'MSET',
        'MSETNX',
        'PSETEX',
        'SET',
        'SETBIT',
        'SETEX',
        'SETNX',
        'SETRANGE',
        'STRLEN',
        'HEXISTS',
        'HGET',
        'HGETALL',
        'HINCRBY',
        'HINCRBYFLOAT',
        'HKEYS',
        'HLEN',
        'HMGET',
        'HMSET',
        'HSET',
        'HSETNX',
        'HVALS',
        //'HSCAN',
        'HSTRLEN',
        //'BLPOP',
        //'BRPOP',
        //'BRPOPLPUSH',
        'LINDEX',
        'LINSERT',
        'LLEN',
        'LPOP',
        'LPUSH',
        'LPUSHX',
        'LRANGE',
        'LREM',
        'LSET',
        'LTRIM',
        'RPOP',
        'RPOPLPUSH',
        'RPUSH',
        'RPUSHX',
        'SADD',
        'SCARD',
        'SDIFF',
        'SDIFFSTORE',
        'SINTER',
        'SINTERSTORE',
        'SISMEMBER',
        'SMEMBERS',
        'SMOVE',
        'SPOP',
        'SRANDMEMBER',
        'SREM',
        'SUNION',
        'SUNIONSTORE',
        //'SSCAN',
        'PSUBSCRIBE',
        'PUBLISH',
        //'PUBSUB',
        //'PUNSUBSCRIBE',
        //'SUBSCRIBE',
        'UNSUBSCRIBE',
    ];

    public function __call(string $command, array $args)
    {
        $command = strtoupper($command);

        if ($this->_exec($command, $args)){
            return $this->_result;
        }else{
            return false;
        }
    }

    /**
     * connect redis server
     */
    private function _conn():void
    {
        $retries = Config::$redisConfig['retries'];
        while ($retries > 0) {
            $retries--;
            $this->_socket = fsockopen(Config::$redisConfig['host'], Config::$redisConfig['port'], $errno, $errstr, 30);
            if ($this->_socket){
                break;
            }elseif (!$this->_socket && $retries == 0) {
                throw new \Exception("[" . __METHOD__ . "] " . $errstr . ", errno : " . $errno);
            }
        }
        if (Config::$redisConfig['password']){
            $this->_exec('AUTH', [Config::$redisConfig['password']]);
        }
        if ($this->_database){
            $this->_exec('SELECT', [$this->_database]);
        }
    }

    /**
     * write data to socket
     */
    private function _write(string $command):bool
    {
        $len = fwrite($this->_socket, $command);
        if ($len === false){
            throw new \Exception("[" . __METHOD__ . "] command : " . $this->_command . ", write redis error");
            return false;
        }elseif ($len !== mb_strlen($command, '8bit')){
            throw new \Exception("[" . __METHOD__ . "] command : " . $this->_command . ", writed data length error");
            return false;
        }else{
            return true;
        }
    }

    /**
     * send command to redis
     */
    private function _exec(string $command, array $args = []):bool
    {
        if (!$this->_socket){
            return false;
        }

        $this->_command = $command;

        if ($command == 'PSUBSCRIBE'){
            $this->_callback = array_pop($args);
        }

        $command = "*" . (count($args) + 1) . "\r\n";
        $command.= "$" . mb_strlen($this->_command, '8bit') . "\r\n";
        $command.= $this->_command . "\r\n";
        foreach ($args as $arg) {
            $command .= '$' . mb_strlen($arg, '8bit') . "\r\n" . $arg . "\r\n";
        }
        $this->_write($command);

        $this->_result = $this->_read();
        return true;
    }

    /**
     * read data from socket
     */
    private function _read()
    {
        //listen & callback
        if ($this->_command == 'PSUBSCRIBE')
        {
            while(!feof($this->_socket))
            {
                call_user_func($this->_callback, $this->_parseResult());
            }
        }
        return $this->_parseResult();
    }

    /**
     * Analyze the result according to the redis protocol
     */
    private function _parseResult()
    {
        $result = fgets($this->_socket);
        if ($result === false){
            throw new \Exception("[" . __METHOD__ . "] command : " . $this->_command . ", read redis error");
            return false;
        }

        $type = mb_substr($result, 0, 1, '8bit');
        $data = mb_substr($result, 1, -2, '8bit');
        switch($type)
        {
            case '+':
                return ($data === 'OK') ? true : $data;
                break;
            case '-':
                throw new \Exception("[" . __METHOD__ . "] command : " . $this->_command . ", redis response: " . $result);
                return false;
                break;
            case ':':
                return $data;
                break;
            case '$':
                if ($data == "-1"){
                    return $data;
                }
                $res = '';
                $len = (int)$data + 2;
                while($len > 0){
                    $content = fgets($this->_socket);
                    if ($content){
                        $len-= (int)mb_strlen($content, '8bit');
                        $res.= $content;
                    }else{
                        throw new \Exception("[" . __METHOD__ . "] command : " . $this->_command . ", read redis error");
                        return false;
                    }
                }
                return mb_substr($res, 0, -2, '8bit');
                break;
            case '*':
                $res = [];
                $num = (int)$data;
                while ($num > 0){
                    $res[] = $this->_parseResult();
                    $num--;
                }
                $res = $this->_formatResult($res);
                return $res; 
                break;
            default:
                throw new \Exception("[" . __METHOD__ . "] command : " . $this->_command . ", redis response: " . $result);
                return false;
        }
    }

    /**
     * Formatting the result for some specific commands
     *
     * @param   $res
     */
    private function _formatResult($res)
    {
        if ($this->_command === "HGETALL" && is_array($res)){
            $return = [];
            $count = count($res);
            for ($i = 0; $i < $count; $i++){
                $return[$res[$i]] = $res[++$i];
            }
            return $return;
        }
        return $res;
    }
}
