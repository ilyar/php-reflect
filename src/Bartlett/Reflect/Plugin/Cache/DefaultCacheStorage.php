<?php
/**
 * Default cache storage implementation.
 *
 * PHP version 5
 *
 * @category PHP
 * @package  PHP_Reflect
 * @author   Laurent Laville <pear@laurent-laville.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version  GIT: $Id$
 * @link     http://php5.laurent-laville.org/reflect/
 */

namespace Bartlett\Reflect\Plugin\Cache;

/**
 * Default cache storage implementation.
 *
 * @category PHP
 * @package  PHP_Reflect
 * @author   Laurent Laville <pear@laurent-laville.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version  Release: @package_version@
 * @link     http://php5.laurent-laville.org/reflect/
 * @since    Class available since Release 2.0.0RC1
 */
class DefaultCacheStorage implements CacheStorageInterface
{
    /**
     * Default cache TTL (Time To Live)
     * @var int
     */
    protected $maxlifetime;

    /**
     * Cache used to store cache data
     * @var CacheAdapterInterface
     */
    protected $cache;

    /**
     * Current manifest
     * @var array
     */
    private $entries;

    /**
     * Key that identify the manifest for data source in cache
     * @var string
     */
    private $key;

    /**
     * Constructs a default cache storage.
     *
     * @param CacheAdapterInterface $adapter Cache adapter used to store cache data
     * @param int                   $ttl     (optional) Default cache TTL
     */
    public function __construct($adapter, $ttl = 3600)
    {
        $this->cache       = $adapter;
        $this->maxlifetime = $ttl;
    }

    /**
     * Checks if cache exists for a request.
     *
     * @param array $request Request data to check for
     *
     * @return bool TRUE if a response exists in cache, FALSE otherwise
     */
    public function exists($request)
    {
        // Hash a request data source into a string that returns cache metadata
        $this->key = sha1($request['source']);

        if ($this->entries = $this->cache->fetch($this->key)) {
            return true;
        }
        return false;
    }

    /**
     * Get a response from the cache for a request.
     *
     * @param array $request Request data to read from cache
     *
     * @return mixed
     */
    public function fetch($request)
    {
        if (!$this->exists($request)) {
            return;
        }

        $manifest = null;
        $entries  = unserialize($this->entries);
        foreach ($entries as $index => $entry) {
            if ($entry['sourceFile'] === $request['file']->getPathname()) {
                $manifest = $entry;
                break;  // we found entry in cache corresponding to current filename
            }
        }

        if (!isset($manifest)) {
            // no cache results for this filename
            return;
        }

        // Ensure that the response is not expired
        if ($manifest['expiration'] < time()
            || $manifest['cacheData'] !== sha1_file($request['file']->getPathname())
        ) {
            // results have expired
            $response = null;
        } else {
            $response = $this->cache->fetch($manifest['cacheData']);
            if ($response) {
                $response = unserialize($response);
            } else {
                // The response is not valid because the body was somehow deleted
                $response = null;
            }
        }

        if ($response === null) {
            // Remove the entry from the metadata and update the cache
            unset($entries[$index]);
            if (count($entries)) {
                $this->cache->save($this->key, serialize($entries));
            } else {
                $this->cache->delete($this->key);
            }
        }

        return $response;
    }

    /**
     * Cache a FILE parse.
     *
     * @param array $request Request being cached
     *
     * @return void
     */
    public function cache($request)
    {
        $currentTime = time();
        $entries     = array();

        if ($this->exists($request)) {
            foreach (unserialize($this->entries) as $entry) {
                if ($entry['expiration'] < $currentTime) {
                    // remove expired entry from the metadata
                    continue;
                }
                if ($entry['sourceFile'] === $request['file']->getPathname()) {
                    // remove old cached content
                    $this->cache->delete($entry['cacheData']);
                } else {
                    $entries[] = $entry;
                }
            }
        }

        // update the manifest
        $key = sha1_file($request['file']->getPathname());
        array_push(
            $entries,
            array(
                'expiration' => $currentTime + $this->maxlifetime,
                'cacheData'  => $key,
                'sourceFile' => $request['file']->getPathname()
            )
        );
        $this->cache->save($this->key, serialize($entries));

        // save user data
        $this->cache->save($key, $request['ast']);
    }

    /**
     * Deletes cache entries that match a request.
     *
     * @param array $request Request to delete from cache
     *
     * @return int
     */
    public function delete($request)
    {
        $entriesCleared = 0;

        if ($this->exists($request)) {
            foreach (unserialize($this->entries) as $entry) {
                if ($entry['cacheData']) {
                    // delete each results of the manifest
                    if ($this->cache->delete($entry['cacheData'])) {
                        $entriesCleared++;
                    }
                }
            }
            // delete the manifest of data source
            $this->cache->delete($this->key);
        }
        return $entriesCleared;
    }

    /**
     * Purge all cache entries for a given data source.
     *
     * @param string $source Name that identify a data source
     *
     * @return int
     */
    public function purge($source)
    {
        $request = array('source' => $source);
        return $this->delete($request);
    }
}
