<?php
/**
 * Unit Test Case that covers the Parameter Model representative.
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

namespace Bartlett\Tests\Reflect\Model;

use Bartlett\Reflect;
use Bartlett\Reflect\ProviderManager;
use Bartlett\Reflect\Provider\SymfonyFinderProvider;
use Bartlett\Reflect\Exception\ModelException;
use Symfony\Component\Finder\Finder;

if (!defined('TEST_FILES_PATH')) {
    define(
        'TEST_FILES_PATH',
        dirname(__DIR__) . DIRECTORY_SEPARATOR .
        '_files' . DIRECTORY_SEPARATOR
    );
}

/**
 * Unit Test Case that covers Bartlett\Reflect\Model\ParameterModel
 *
 * @category   PHP
 * @package    PHP_Reflect
 * @subpackage Tests
 * @author     Laurent Laville <pear@laurent-laville.org>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://php5.laurent-laville.org/reflect/
 */
class ParameterModelTest extends \PHPUnit_Framework_TestCase
{
    protected static $interfaces;
    protected static $classes;
    protected static $functions;

    /**
     * Sets up the shared fixture.
     *
     * @return void
     * @link   http://phpunit.de/manual/current/en/fixtures.html#fixtures.sharing-fixture
     */
    public static function setUpBeforeClass()
    {
        $finder = new Finder();
        $finder->files()
            ->name('functions.php')
            ->in(TEST_FILES_PATH);

        $pm = new ProviderManager;
        $pm->set('test_files', new SymfonyFinderProvider($finder));

        $reflect = new Reflect();
        $reflect->setProviderManager($pm);
        $reflect->parse();

        foreach ($reflect->getPackages() as $package) {
            foreach ($package->getInterfaces() as $rc) {
                self::$interfaces[] = $rc;
            }
            foreach ($package->getClasses() as $rc) {
                self::$classes[] = $rc;
            }
            foreach ($package->getFunctions() as $rf) {
                self::$functions[] = $rf;
            }
        }
    }

    /**
     * Tests name of the parameter.
     *
     *  covers ParameterModel::getName
     * @return void
     */
    public function testNameAccessor()
    {
        $c = 0;  // class Foo
        $m = 'otherfunction';
        $p = 0;  // parameter $baz

        $methods    = self::$classes[$c]->getMethods();
        $parameters = $methods[$m]->getParameters();

        $this->assertEquals(
            'baz',
            $parameters[$p]->getName(),
            $methods[$m]->getName()
            . ", parameter #$p name does not match."
        );
    }

    /**
     * Tests position of the parameter.
     *
     *  covers ParameterModel::getPosition
     * @return void
     */
    public function testPositionAccessor()
    {
        $f = 0;  // function singleFunction
        $p = 1;  // parameter $somethingelse

        $parameters = self::$functions[$f]->getParameters();

        $this->assertEquals(
            1,
            $parameters[$p]->getPosition(),
            self::$functions[$f]->getName() . ", parameter #$p position does not match."
        );
    }

    /**
     * Tests type hinting of the parameter.
     *
     *  covers ParameterModel::getTypeHint
     * @return void
     */
    public function testTypeHintAccessor()
    {
        $f = 0;  // function singleFunction
        $p = 0;  // parameter $someparam

        $parameters = self::$functions[$f]->getParameters();

        $this->assertEquals(
            'array',
            $parameters[$p]->getTypeHint(),
            self::$functions[$f]->getName() . ", parameter #$p type hint does not match."
        );
    }

    /**
     * Tests whether the parameter allows NULL
     * when type hint is defined without default value for a class method.
     *
     *  covers ParameterModel::allowsNull
     * @return void
     */
    public function testAllowsNullWhenOnlyTypeHintDefinedOnClassMethod()
    {
        $i = 0;  // interface iB extends iA
        $m = 'baz';
        $p = 0;  // parameter $baz

        $methods    = self::$interfaces[$i]->getMethods();
        $parameters = $methods[$m]->getParameters();

        $this->assertFalse(
            $parameters[$p]->allowsNull(),
            $methods[$m]->getName()
            . ", parameter #$p does not allow null with type hint without null default value."
        );
    }

    /**
     * Tests whether the parameter allows NULL
     * when type hint is defined with default value is null for a class method.
     *
     *  covers ParameterModel::allowsNull
     * @return void
     */
    public function testAllowsNullWhenTypeHintDefinedWithNullDefaultValueOnClassMethod()
    {
        $c = 0;  // class Foo
        $m = 'myfunction';
        $p = 0;  // parameter $param

        $methods    = self::$classes[$c]->getMethods();
        $parameters = $methods[$m]->getParameters();

        $this->assertTrue(
            $parameters[$p]->allowsNull(),
            $methods[$m]->getName()
            . ", parameter #$p allows null with type hint and null default value."
        );
    }

    /**
     * Tests whether the parameter allows NULL
     * when type hint is not defined for a class method.
     *
     *  covers ParameterModel::allowsNull
     * @return void
     */
    public function testAllowsNullWithoutTypeHintDefinedOnClassMethod()
    {
        $c = 0;  // class Foo
        $m = 'otherfunction';
        $p = 1;  // parameter $param

        $methods    = self::$classes[$c]->getMethods();
        $parameters = $methods[$m]->getParameters();

        $this->assertTrue(
            $parameters[$p]->allowsNull(),
            $methods[$m]->getName()
            . ", parameter #$p allows null without type hint."
        );
    }

    /**
     * Tests whether the parameter allows NULL
     * for a user-defined function.
     *
     *  covers ParameterModel::allowsNull
     * @return void
     */
    public function testAllowsNullOnUserFunction()
    {
        $f = 0;  // function singleFunction
        $p = 2;  // parameter $lastone

        $parameters = self::$functions[$f]->getParameters();

        $this->assertTrue(
            $parameters[$p]->allowsNull(),
            self::$functions[$f]->getName() . ", parameter #$p allows null when default value is null."
        );
    }

    /**
     * Tests if the parameter is optional.
     *
     *  covers ParameterModel::isOptional
     * @return void
     */
    public function testIsOptional()
    {
        $f = 0;  // function singleFunction
        $p = 1;  // parameter $somethingelse

        $parameters = self::$functions[$f]->getParameters();

        $this->assertFalse(
            $parameters[$p]->isOptional(),
            self::$functions[$f]->getName() . ", parameter #$p is required."
        );
    }

    /**
     * Checks if the parameter is passed in by reference.
     *
     *  covers ParameterModel::isPassedByReference
     * @return void
     */
    public function testIsPassedByReference()
    {
        $f = 1;  // function myprocess
        $p = 1;  // parameter $myresult

        $parameters = self::$functions[$f]->getParameters();

        $this->assertTrue(
            $parameters[$p]->isPassedByReference(),
            self::$functions[$f]->getName() . ", parameter #$p is passed by reference."
        );
    }

    /**
     * Tests if the parameter expects an array.
     *
     *  covers ParameterModel::isArray
     * @return void
     */
    public function testIsArray()
    {
        $f = 0;  // function singleFunction
        $p = 0;  // parameter $someparam

        $parameters = self::$functions[$f]->getParameters();

        $this->assertTrue(
            $parameters[$p]->isArray(),
            self::$functions[$f]->getName() . ", parameter #$p expects an array."
        );
    }

    /**
     * Tests if a default value for the parameter is available
     * for a user-defined function.
     *
     *  covers ParameterModel::isDefaultValueAvailable
     * @return void
     */
    public function testIsDefaultValueAvailableOnUserFunction()
    {
        $f = 0;  // function singleFunction
        $p = 2;  // parameter $lastone

        $parameters = self::$functions[$f]->getParameters();

        $this->assertTrue(
            $parameters[$p]->isDefaultValueAvailable(),
            self::$functions[$f]->getName() . ", parameter #$p have a default value."
        );
    }

    /**
     * Tests the default value of the parameter for a class method.
     *
     *  covers ParameterModel::getDefaultValue
     * @return void
     */
    public function testDefaultValueAccessorOnClassMethod()
    {
        $c = 0;  // class Foo
        $m = 'myfunction';
        $p = 1;  // parameter $otherparam

        $methods    = self::$classes[$c]->getMethods();
        $parameters = $methods[$m]->getParameters();

        $this->assertEquals(
            'TRUE',
            $parameters[$p]->getDefaultValue(),
            $methods[$m]->getName()
            . ", parameter #$p default value does not match."
        );
    }

    /**
     * Tests the default value of the parameter for a user-defined function.
     *
     *  covers ParameterModel::getDefaultValue
     * @return void
     */
    public function testDefaultValueAccessorOnUserFunction()
    {
        $f = 0;  // function singleFunction
        $p = 2;  // parameter $lastone

        $parameters = self::$functions[$f]->getParameters();

        $this->assertEquals(
            'NULL',
            $parameters[2]->getDefaultValue(),
            self::$functions[$f]->getName() . ", parameter #$p default value does not match."
        );
    }

    /**
     * Tests the default value of a parameter not optional.
     *
     *  covers ParameterModel::getDefaultValue
     * @return void
     */
    public function testDefaultValueAccessorOnRequiredParameter()
    {
        try {
            $f = 0;  // function singleFunction
            $p = 0;  // parameter $someparam

            $parameters = self::$functions[$f]->getParameters();

            $parameters[$p]->getDefaultValue();

        } catch (ModelException $expected) {
            $this->assertEquals(
                'Parameter #0 [$someparam] is not optional.',
                $expected->getMessage(),
                'Expected exception message does not match'
            );
            return;
        }
        $this->fail(
            'An expected Bartlett\Reflect\Exception\ModelException exception' .
            ' has not been raised.'
        );
    }

    /**
     * Tests string representation of the ParameterModel object
     * for a required parameter.
     *
     *  covers ParameterModel::__toString
     * @return void
     */
    public function testToStringRequiredParameter()
    {
        $f = 1;  // function myprocess
        $p = 1;  // parameter $myresult

        $parameters = self::$functions[$f]->getParameters();

        $this->assertEquals(
            'Parameter #1 [ <required> &$myresult ]' . "\n",
            $parameters[$p]->__toString(),
            self::$functions[$f]->getName() . ", parameter #$p string representation does not match."
        );
    }

    /**
     * Tests string representation of the ParameterModel object
     * for an optional parameter.
     *
     *  covers ParameterModel::__toString
     * @return void
     */
    public function testToStringOptionalParameter()
    {
        $c = 0;  // class Foo
        $m = 'myfunction';
        $p = 0;  // parameter $param

        $methods    = self::$classes[$c]->getMethods();
        $parameters = $methods[$m]->getParameters();

        $this->assertEquals(
            'Parameter #0 [ <optional> stdClass $param = NULL ]' . "\n",
            $parameters[$p]->__toString(),
            $methods[$m]->getName()
            . ", parameter #$p string representation does not match."
        );
    }
}
