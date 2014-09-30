<?php
/**
 * Unit Test Case that covers specific Symfony Finder data source provider.
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    PHP_Reflect
 * @subpackage Tests
 * @author     Laurent Laville <pear@laurent-laville.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    GIT: $Id$
 * @link       http://php5.laurent-laville.org/reflect/
 * @since      Class available since Release 2.0.0RC1
 */

namespace Bartlett\Tests\Reflect\Provider;

use Bartlett\Reflect\Provider\SymfonyFinderProvider;
use Symfony\Component\Finder\Finder;

if (!defined('TEST_FILES_PATH')) {
    define(
        'TEST_FILES_PATH',
        dirname(__DIR__) . DIRECTORY_SEPARATOR .
        '_files' . DIRECTORY_SEPARATOR
    );
}

/**
 * Tests for the PHP_CompatInfo, retrieving components informations
 * about any extension.
 *
 * @category   PHP
 * @package    PHP_Reflect
 * @subpackage Tests
 * @author     Laurent Laville <pear@laurent-laville.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://php5.laurent-laville.org/reflect/
 */
class SymfonyFinderProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests the count of item from the data source of the provider.
     *
     *  covers \Bartlett\Reflect\Provider\SymfonyFinderProvider::count
     * @return void
     */
    public function testProviderCount()
    {
        $finder = new Finder();
        $finder->files()
            ->name('c*.php')
            ->in(TEST_FILES_PATH);

        $provider = new SymfonyFinderProvider($finder);

        $this->assertEquals(
            2,
            count($provider),
            'Data source items count does not match.'
        );
    }

    /**
     * Tests if full results ara available in the data source provider.
     *
     *  covers \Bartlett\Reflect\Provider\SymfonyFinderProvider::__invoke
     * @return void
     */
    public function testProviderFullResults()
    {
        $finder = new Finder();
        $finder->files()
            ->name('c*.php')
            ->sortByName()
            ->in(TEST_FILES_PATH);

        $provider = new SymfonyFinderProvider($finder);

        $this->assertEquals(
            array(
                TEST_FILES_PATH . 'classes.php',
                TEST_FILES_PATH . 'constants.php',
            ),
            array_keys($provider()),
            'Provider full results does not match.'
        );
    }

    /**
     * Tests if a single result is available in the data source provider.
     *
     *  covers \Bartlett\Reflect\Provider\SymfonyFinderProvider::__invoke
     * @return void
     */
    public function testProviderSingleResult()
    {
        $finder = new Finder();
        $finder->files()
            ->name('c*.php')
            ->in(TEST_FILES_PATH);

        $provider = new SymfonyFinderProvider($finder);

        $this->assertEquals(
            array(
                TEST_FILES_PATH . 'classes.php',
            ),
            array_keys($provider(TEST_FILES_PATH . 'classes.php')),
            'Provider single result does not match.'
        );
    }

    /**
     * Tests illegal result in the data source provider.
     *
     *  covers \Bartlett\Reflect\Provider\SymfonyFinderProvider::__invoke
     * @return void
     */
    public function testProviderIllegalResult()
    {
        $finder = new Finder();
        $finder->files()
            ->name('*.php')
            ->in(TEST_FILES_PATH);

        $provider = new SymfonyFinderProvider($finder);
        $uri      = TEST_FILES_PATH . 'unknown.php';

        try {
            $provider($uri);

        } catch (\OutOfRangeException $expected) {
            $this->assertEquals(
                "$uri does not exist in this provider.",
                $expected->getMessage(),
                'Expected exception message does not match'
            );
            return;
        }
        $this->fail(
            'An expected \OutOfRangeException exception' .
            ' has not been raised.'
        );
    }
}
