<?php
/**
 * Data source provider for the Symfony Finder component.
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

namespace Bartlett\Reflect\Provider;

use Symfony\Component\Finder\Finder;

/**
 * Data source provider for the Symfony Finder component.
 *
 * @category PHP
 * @package  PHP_Reflect
 * @author   Laurent Laville <pear@laurent-laville.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version  Release: @package_version@
 * @link     http://php5.laurent-laville.org/reflect/
 * @since    Class available since Release 2.0.0RC1
 */
class SymfonyFinderProvider implements ProviderInterface
{
    protected $provider;

    /**
     * Construct a new Symfony Finder data source provider.
     *
     * @param \Symfony\Component\Finder\Finder $finder A Symfony Finder instance
     *
     * @return SymfonyFinderProvider
     */
    public function __construct(Finder $finder)
    {
        $this->provider = $finder;
    }

    /**
     * Returns results of the data source provider.
     *
     * @param string $uri Limit results to a target filename
     *
     * @return array
     * @throws \OutOfRangeException if $uri is illegal (unknown in this provider)
     */
    public function __invoke($uri = '')
    {
        $results = iterator_to_array($this->provider->getIterator());

        if (!$uri) {
            return $results;
        }
        if (isset($results[$uri])) {
            return array($uri => $results[$uri]);
        }
        throw new \OutOfRangeException("$uri does not exist in this provider.");
    }

    /**
     * Returns an Iterator for the current Symfony Finder configuration.
     *
     * @return \Iterator
     */
    public function getIterator()
    {
        return $this->provider->getIterator();
    }

    /**
     * Gets the count of item in the data source.
     *
     * @return int
     */
    public function count()
    {
        return $this->provider->count();
    }
}
