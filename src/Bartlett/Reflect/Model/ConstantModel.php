<?php
/**
 * ConstantModel represents a constant definition.
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

namespace Bartlett\Reflect\Model;

use Bartlett\Reflect\Exception\ModelException;
use Bartlett\Reflect\Model\AbstractModel;

/**
 * The ConstantModel class reports information about a constant.
 *
 * @category PHP
 * @package  PHP_Reflect
 * @author   Laurent Laville <pear@laurent-laville.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version  Release: @package_version@
 * @link     http://php5.laurent-laville.org/reflect/
 * @since    Class available since Release 2.0.0RC1
 */
class ConstantModel extends AbstractModel implements Visitable
{
    protected $short_name;

    /**
     * Constructs a new ConstantModel instance.
     *
     * @param string $qualifiedName The full qualified name of the constant
     */
    public function __construct($qualifiedName, $attributes)
    {
        $struct = array(
            'magic'      => false,
            'namespace'  => false,
            'value'      => null,
        );
        $struct = array_merge($struct, $attributes);
        parent::__construct($struct);

        $this->name = $qualifiedName;

        $parts = explode('\\', $qualifiedName);
        // a constant should be normally in uppercase
        $this->short_name = strtoupper(array_pop($parts));

        $this->struct['namespace'] = implode('\\', $parts);

        if ($this->struct['magic']) {
            $this->struct['extension'] = 'core';
        }
    }

    /**
     * Get a Doc comment from a constant.
     *
     * @return string
     */
    public function getDocComment()
    {
        return $this->struct['docComment'];
    }

    /**
     * Gets the file name from a user-defined function.
     *
     * @return mixed FALSE for an internal constant (when isInternal() returns TRUE),
     *               otherwise string
     */
    public function getFileName()
    {
        if ($this->isInternal()) {
            return false;
        }
        return $this->struct['file'];
    }

    /**
     * Gets the extension information of this constant.
     *
     * @return ReflectionExtension instance that contains the extension information
     * @throws ModelException if extension does not exist (user(405) or not loaded(404))
     */
    public function getExtension()
    {
        if ($this->struct['extension'] === 'user') {
            throw new ModelException(
                'Extension ' . $this->struct['extension'] . ' does not exist.',
                405
            );
        } elseif (!extension_loaded($this->struct['extension'])) {
            throw new ModelException(
                'Extension ' . $this->struct['extension'] . ' does not exist.',
                404
            );
        }

        return new \ReflectionExtension($this->struct['extension']);
    }

    /**
     * Gets the extension name of this constant.
     *
     * @return string
     */
    public function getExtensionName()
    {
        try {
            $name = $this->getExtension()->getName();

        } catch (ModelException $e) {
            if ($e->getCode() === 404) {
                throw $e;  // re-throws original exception
            }
            $name = 'user';
        }
        return $name;
    }

    /**
     * Get the namespace name where the constant is defined.
     *
     * @return string
     */
    public function getNamespaceName()
    {
        return $this->struct['namespace'];
    }

    /**
     * Gets the constant name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the short name of the constant (without the namespace part).
     *
     * @return string
     */
    public function getShortName()
    {
        return $this->short_name;
    }

    /**
     * Gets the constant value.
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->struct['value'];
    }

    /**
     * Checks whether a constant is defined in a namespace.
     *
     * @return bool TRUE if it's in a namespace, otherwise FALSE
     */
    public function inNamespace()
    {
        return (!empty($this->struct['namespace']));
    }

    /**
     * Checks whether it's an internal constant.
     *
     * @return bool TRUE if it's internal, otherwise FALSE
     */
    public function isInternal()
    {
        return ($this->struct['magic'] || $this->getExtensionName() !== 'user');
    }

    /**
     * Checks whether it's a magic constant.
     *
     * @link http://www.php.net/manual/en/language.constants.predefined.php
     * @return bool TRUE if it's magic, otherwise FALSE
     */
    public function isMagic()
    {
        return $this->struct['magic'];
    }

    /**
     * Returns the string representation of the ConstantModel object.
     *
     * @return string
     */
    public function __toString()
    {
        $eol = "\n";

        return sprintf(
            'Constant [ %s ] { %s }%s',
            $this->getName(),
            $this->getValue(),
            $eol
        );
    }
}
