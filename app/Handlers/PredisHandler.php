<?php

declare(strict_types=1);

namespace App\Handlers;

use CodeIgniter\Exceptions\CriticalError;
use CodeIgniter\I18n\Time;
use Config\Cache;
use Exception;
use Predis\Client;
use Predis\Collection\Iterator\Keyspace;
use CodeIgniter\Cache\Handlers\BaseHandler;

/**
 * Predis cache handler
 *
 * @see \CodeIgniter\Cache\Handlers\PredisHandlerTest
 */
class PredisHandler extends BaseHandler
{
    /**
     * Default config
     *
     * @var array
     */
    protected $config = [
        'scheme'   => 'tcp',
        'host'     => '127.0.0.1',
        'password' => null,
        'port'     => 6379,
        'timeout'  => 0,
    ];

    /**
     * Predis connection
     *
     * @var Client
     */
    protected $redis;


    /** 
     * 
     * Aggregate connections enabled
     * 
     * @var bool
     */
    protected $aggregate_connections = false;

    /**
     * Note: Use `CacheFactory::getHandler()` to instantiate.
     */
    public function __construct(Cache $config)
    {
        $this->prefix = $config->prefix;

        if (isset($config->aggregate_connections)) {
            $this->aggregate_connections = $config->aggregate_connections;
        }

        if (isset($config->redis) && $this->aggregate_connections == false) {
            $this->config = array_merge($this->config, $config->redis);
        }

        if ($this->aggregate_connections) {
            $this->config = $config->connections;
            $this->config = array_merge($this->config, ['prefix' => $this->prefix]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
        try {
            if ($this->aggregate_connections) {
                $this->redis = new Client($this->config['nodes'], $this->config);
            } else {
                $this->redis = new Client($this->config, ['prefix' => $this->prefix]);
                $this->redis->time();
            }
        } catch (Exception $e) {
            throw new CriticalError('Cache: Predis connection refused (' . $e->getMessage() . ').');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key)
    {
        $key = static::validateKey($key);

        $data = array_combine(
            ['__ci_type', '__ci_value'],
            $this->redis->hmget($key, ['__ci_type', '__ci_value'])
        );

        if (! isset($data['__ci_type'], $data['__ci_value']) || $data['__ci_value'] === false) {
            return null;
        }

        return match ($data['__ci_type']) {
            'array', 'object' => unserialize($data['__ci_value']),
            // Yes, 'double' is returned and NOT 'float'
            'boolean', 'integer', 'double', 'string', 'NULL' => settype($data['__ci_value'], $data['__ci_type']) ? $data['__ci_value'] : null,
            default => null,
        };
    }

    /**
     * {@inheritDoc}
     */
    public function save(string $key, $value, int $ttl = 60)
    {
        $key = static::validateKey($key);

        switch ($dataType = gettype($value)) {
            case 'array':
            case 'object':
                $value = serialize($value);
                break;

            case 'boolean':
            case 'integer':
            case 'double': // Yes, 'double' is returned and NOT 'float'
            case 'string':
            case 'NULL':
                break;

            case 'resource':
            default:
                return false;
        }

        if (! $this->redis->hmset($key, ['__ci_type' => $dataType, '__ci_value' => $value])) {
            return false;
        }

        if ($ttl !== 0) {
            $this->redis->expireat($key, Time::now()->getTimestamp() + $ttl);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key)
    {
        $key = static::validateKey($key);

        return $this->redis->del($key) === 1;
    }

    /**
     * {@inheritDoc}
     *
     * @return int
     */
    public function deleteMatching(string $pattern)
    {
        $matchedKeys = [];

        foreach (new Keyspace($this->redis, $pattern) as $key) {
            $matchedKeys[] = $key;
        }

        return $this->redis->del($matchedKeys);
    }

    /**
     * {@inheritDoc}
     */
    public function increment(string $key, int $offset = 1)
    {
        $key = static::validateKey($key);

        return $this->redis->hincrby($key, 'data', $offset);
    }

    /**
     * {@inheritDoc}
     */
    public function decrement(string $key, int $offset = 1)
    {
        $key = static::validateKey($key);

        return $this->redis->hincrby($key, 'data', -$offset);
    }

    /**
     * {@inheritDoc}
     */
    public function clean()
    {
        return $this->redis->flushdb()->getPayload() === 'OK';
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheInfo()
    {
        return $this->redis->info();
    }

    /**
     * {@inheritDoc}
     */
    public function getMetaData(string $key)
    {
        $key = static::validateKey($key);

        $data = array_combine(['__ci_value'], $this->redis->hmget($key, ['__ci_value']));

        if (isset($data['__ci_value']) && $data['__ci_value'] !== false) {
            $time = Time::now()->getTimestamp();
            $ttl  = $this->redis->ttl($key);

            return [
                'expire' => $ttl > 0 ? $time + $ttl : null,
                'mtime'  => $time,
                'data'   => $data['__ci_value'],
            ];
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function isSupported(): bool
    {
        return class_exists(Client::class);
    }

    /**
     * Clear user cache by deleting all keys matching the 'user_data_*' pattern.
     *
     * @return bool True if the cache clearing process was successful.
     */
    public function clearCacheItems($key = null): bool
{
    if ($key === null) {
        log_message('error', 'Error clearing cache: No item specified');
        return false;
    }

    try {
        $cachePattern = $this->prefix . $key . '*'; // Ensure to match all keys with the pattern.

        // Iterate through each node in the cluster.
        foreach ($this->redis->getConnection() as $connection) {
            $nodeClient = new Client($connection->getParameters());

            $iterator = new Keyspace($nodeClient, $cachePattern);

            // Use the Keyspace iterator to scan and delete matching keys.
            foreach ($iterator as $keyItem) {
                $nodeClient->del($keyItem);
                log_message('info', 'Cache cleared for ' . $keyItem . '.');
            }
            log_message('info', 'Cache cleared on node ' . $connection->getParameters() . '.');
        }
        log_message('info', 'Cache cleared for ' . $cachePattern . ' pattern usering supplied key of: ' . $key);
        return true;
    } catch (Exception $e) {
        log_message('error', 'Error clearing cache for ' . $key . ': ' . $e->getMessage());
        return false;
    }
}


    
}
