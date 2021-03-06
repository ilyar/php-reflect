<?php
/**
 * A parent class for FunctionModel.
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

use Bartlett\Reflect\Model\AbstractModel;

use PhpParser\Node;

/**
 * A parent class for concrete FunctionModel.
 *
 * @category PHP
 * @package  PHP_Reflect
 * @author   Laurent Laville <pear@laurent-laville.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version  Release: @package_version@
 * @link     http://php5.laurent-laville.org/reflect/
 * @since    Class available since Release 2.0.0RC1
 */
abstract class AbstractFunctionModel extends AbstractModel
{
    private $parameters;

    /**
     * Gets the file name from a user-defined function.
     *
     * @return mixed FALSE for an internal function (when isInternal() returns TRUE),
     *               otherwise string
     */
    public function getFileName()
    {
        if ($this->isInternal()) {
            return false;
        }
        return parent::getFileName();
    }

    /**
     * Get the name of the function.
     *
     * @return string
     */
    public function getName()
    {
        if ($this->isClosure()) {
            return '{closure}';
        }
        if (isset($this->node->namespacedName)) {
            return $this->node->namespacedName;
        }
        return $this->node->name;
    }

    /**
     * Get the namespace name where the class or user-function is defined.
     *
     * @return string
     */
    public function getNamespaceName()
    {
        $parts = explode('\\', $this->getName());
        array_pop($parts);
        return implode('\\', $parts);
    }

    /**
     * Get the short name of the function (without the namespace part).
     *
     * @return string
     */
    public function getShortName()
    {
        return $this->node->name;
    }

    /**
     * Get the number of parameters that a function defines,
     * both optional and required.
     *
     * @return int The number of parameters
     */
    public function getNumberOfParameters()
    {
        $parameters = $this->getParameters();
        return count($parameters);
    }

    /**
     * Get the number of required parameters that a function defines.
     *
     * @return int The number of required parameters
     */
    public function getNumberOfRequiredParameters()
    {
        $parameters = $this->getParameters();
        $required   = 0;

        foreach ($parameters as $param) {
            if (!$param->isOptional()) {
                $required++;
            }
        }
        return $required;
    }

    /**
     * Get the parameters.
     *
     * @return array of ParameterModel
     */
    public function getParameters()
    {
        if ($this->parameters === null) {
            // lazy load function parameters list
            $this->parameters = array();
            foreach ($this->node->params as $pos => $param) {
                if ($param instanceof Node\Param) {
                    $this->parameters[] = new ParameterModel($param, $pos);
                }
            }
        }
        return $this->parameters;
    }

    /**
     * Checks whether a function is defined in a namespace.
     *
     * @return bool TRUE if it's in a namespace, otherwise FALSE
     */
    public function inNamespace()
    {
        return $this->getName()->isQualified();
    }

    /**
     * Checks whether it's a closure.
     *
     * @return bool TRUE if it's a closure, otherwise FALSE
     */
    public function isClosure()
    {
        return $this->node instanceof Node\Expr\Closure;
    }

    /**
     * Checks whether it's an internal function.
     *
     * @return bool TRUE if it's internal, otherwise FALSE
     */
    public function isInternal()
    {
        return ($this->getExtensionName() !== 'user');
    }
}
