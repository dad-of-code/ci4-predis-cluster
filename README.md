# PredisHandler for CodeIgniter 4

A custom cache handler for CodeIgniter 4 that provides enhanced Redis functionality using the Predis library, with support for Redis Cluster and replication.

## Overview

The `PredisHandler` extends CodeIgniter's native cache handling system to provide robust Redis caching capabilities with support for:

- Single Redis server connections
- Redis Cluster configurations
- Advanced key management
- Proper data type handling
- Cluster-aware cache clearing

This handler is designed to be a drop-in replacement for CodeIgniter's built-in `RedisHandler`, offering additional functionality and better performance in clustered environments.

## Copyright Notice

This handler reuses and extends code from the CodeIgniter framework:

```
This file is part of CodeIgniter 4 framework.

(c) CodeIgniter Foundation <admin@codeigniter.com>

For the full copyright and license information, please view
the LICENSE file that was distributed with this source code.
```

The extended functionality for Redis Cluster support and additional features were developed by dad-of-code.

For more information about CodeIgniter 4, visit the official repository: [https://github.com/codeigniter4/CodeIgniter4](https://github.com/codeigniter4/CodeIgniter4)

## Features

- **Redis Cluster Support**: Connect to multiple Redis nodes in a cluster configuration
- **Flexible Configuration**: Support for both single-server and clustered Redis setups
- **Type-Safe Caching**: Preserves PHP data types when storing and retrieving from cache
- **Cluster-Aware Operations**: Properly handles operations across all nodes in a Redis cluster
- **Performance Optimized**: Designed for high-performance applications with distributed caching needs
- **Custom Cache Clearing**: Provides methods to selectively clear cache items across the cluster

## Installation

1. Ensure you have the Predis library installed:

```bash
composer require predis/predis
```

2. Place the `PredisHandler.php` file in your `app/Handlers/` directory.

3. Update your `app/Config/Cache.php` configuration to use the PredisHandler.

## Configuration

### Basic Configuration

To use PredisHandler with a single Redis server, configure your `app/Config/Cache.php` file as follows:

```php
public string $handler = 'predis';
public string $backupHandler = 'file';

// Set this to false for single server mode
public $aggregate_connections = false;

// Single server configuration
public $redis = [
    'scheme'   => 'tcp',
    'host'     => '127.0.0.1',
    'password' => null,
    'port'     => 6379,
    'timeout'  => 0,
];
```

### Redis Cluster Configuration

To use PredisHandler with a Redis Cluster, configure your `app/Config/Cache.php` file as follows:

```php
public string $handler = 'predis';
public string $backupHandler = 'file';

// Enable cluster mode
public $aggregate_connections = true;

// Cluster configuration
public $connections = [
    'cluster' => 'redis',
    'parameters' => [
        'password' => 'your-password',
    ],
    'nodes' => [
        'tcp://redis-node1:6379',
        'tcp://redis-node2:6379',
        'tcp://redis-node3:6379',
        // Add more nodes as needed
    ]
];
```

## Usage

Once configured, you can use the cache service just like any other CodeIgniter cache handler:

```php
$cache = \Config\Services::cache();

// Store an item in the cache (for 5 minutes)
$cache->save('my_item', $data, 300);

// Retrieve the item
$data = $cache->get('my_item');

// Delete the item
$cache->delete('my_item');

// Clear all cache
$cache->clean();

// Clear specific cache items (cluster-aware)
if ($cache instanceof \App\Handlers\PredisHandler) {
    $cache->clearCacheItems('user_data');
}
```

## Advanced Features

### Clearing Specific Cache Items

The `clearCacheItems()` method allows you to clear specific cache items across all nodes in a Redis cluster:

```php
// Clear all cache items with keys starting with 'user_data_'
$cache->clearCacheItems('user_data');
```

### Type Preservation

The handler preserves PHP data types when storing and retrieving from cache:

- Arrays and objects are serialized/unserialized automatically
- Primitive types (boolean, integer, float, string) maintain their type
- NULL values are properly handled

## Troubleshooting

### Connection Issues

If you're experiencing connection issues:

1. Verify that Redis is running and accessible from your application server
2. Check firewall settings to ensure Redis ports are open
3. Verify that the password in your configuration is correct
4. For cluster configurations, ensure all nodes are properly configured and reachable

### Performance Considerations

- For high-traffic applications, consider increasing the number of Redis nodes
- Monitor Redis memory usage and consider implementing key expiration policies
- Use appropriate TTL (Time To Live) values for cached items to prevent cache bloat

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Credits

Developed by dad-of-code for use with CodeIgniter 4 applications.

This handler uses the [Predis/Predis](https://github.com/predis/predis) library for Redis connections and operations.
