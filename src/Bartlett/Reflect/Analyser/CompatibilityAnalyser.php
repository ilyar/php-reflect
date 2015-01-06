<?php
/**
 *
 * @category PHP
 * @package  PHP_Reflect
 * @author   Laurent Laville <pear@laurent-laville.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version  GIT: $Id$
 * @link     http://php5.laurent-laville.org/reflect/
 */

namespace Bartlett\Reflect\Analyser;

use Bartlett\Reflect\Environment;
use Bartlett\Reflect\Collection\ReferenceCollection;

use PhpParser\Node;

/**
 *
 * @category PHP
 * @package  PHP_Reflect
 * @author   Laurent Laville <pear@laurent-laville.org>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version  Release: @package_version@
 * @link     http://php5.laurent-laville.org/reflect/
 * @since    Class available since Release 3.0.0-alpha1
 */
class CompatibilityAnalyser extends AbstractAnalyser
{
    const GLOBAL_NAMESPACE = '+global';

    protected static $php4 = array(
        'ext.name' => 'user',
        'ext.min'  => '',
        'ext.max'  => '',
        'php.min'  => '4.0.0',
        'php.max'  => '',
    );

    private $aliases;
    private $references;
    private $contextStack;
    private $localVersions;

    public function __construct()
    {
        $pdo = Environment::initRefDb();

        $this->metrics = array(
            'versions'   => array(),
            'extensions' => array(),
            'namespaces' => array(),
            'interfaces' => array(),
            'traits'     => array(),
            'classes'    => array(),
            'methods'    => array(),
            'functions'  => array(),
            'constants'  => array(),
            'conditions' => array(),
        );

        $this->references = new ReferenceCollection(array(), $pdo);
    }

    public function beforeTraverse(array $nodes)
    {
        parent::beforeTraverse($nodes);

        $element  = 'namespaces';
        $name     = self::GLOBAL_NAMESPACE;
        $versions = array(
            'ext.name' => 'Core',
            'ext.min'  => '',
            'ext.max'  => '',
            'php.min'  => '4.0.0',
            'php.max'  => '',
        );
        $this->updateElementVersion($element, $name, $versions);
        $this->updateElementVersion('extensions', $versions['ext.name'], $versions);
        $this->contextStack = array(
            array($element, $name)
        );
    }

    public function afterTraverse(array $nodes)
    {
        parent::afterTraverse($nodes);

        $this->computeNamespaceVersions();
    }

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->iniUsertNamespace($node);

        } elseif ($node instanceof Node\Stmt\Class_) {
            $this->initUserClass($node);

        } elseif ($node instanceof Node\Stmt\Interface_) {
            $this->initUserInterface($node);

        } elseif ($node instanceof Node\Stmt\Trait_) {
            $this->initUserTrait($node);

        } elseif ($node instanceof Node\Stmt\Function_
            || $node instanceof Node\Expr\Closure
            || $node instanceof Node\Stmt\ClassMethod
        ) {
            $this->initUserFunction($node);

        } elseif ($node instanceof Node\Expr\Assign
            && $node->expr instanceof Node\Expr\New_
        ) {
            $this->initClassAliasResolver($node);

        } elseif ($node instanceof Node\Param) {
            $this->initFunctionArguments($node);
        }
    }

    public function leaveNode(Node $node)
    {
        parent::leaveNode($node);

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->computeNamespaceVersions();

        } elseif ($node instanceof Node\Stmt\Class_) {
            $this->computeClassVersions($node);

        } elseif ($node instanceof Node\Stmt\Interface_) {
            $this->computeInterfaceVersions($node);

        } elseif ($node instanceof Node\Stmt\Trait_) {
            $this->computeTraitVersions($node);

        } elseif ($node instanceof Node\Stmt\Function_
            || $node instanceof Node\Expr\Closure
            || $node instanceof Node\Stmt\ClassMethod
        ) {
            $this->computeFunctionVersions($node);

        } elseif ($node instanceof Node\Expr\New_
            && $node->class instanceof Node\Name
        ) {
            $this->computeClassCallVersions($node);

        } elseif ($node instanceof Node\Expr\FuncCall
            && $node->name instanceof Node\Name
        ) {
            $this->computeFunctionCallVersions($node);

        } elseif ($node instanceof Node\Expr\MethodCall
            && is_string($node->name)
        ) {
            $this->computeClassMethodCallVersions($node);

        } elseif ($node instanceof Node\Stmt\Use_) {
            $this->computePhpFeatureVersions($node);

        } elseif ($node instanceof Node\Expr\Array_) {
            $this->computePhpFeatureVersions($node);

        } elseif ($node instanceof Node\Expr\ArrayDimFetch
            && $node->var instanceof Node\Expr\FuncCall
        ) {
            $this->computePhpFeatureVersions($node);

        } elseif ($node instanceof Node\Stmt\Const_) {
            foreach ($node->consts as $const) {
                // user constant does not require to search in REF database
                $versions = $const->getAttribute('compatinfo');
                if ($versions === null) {
                    $versions = self::$php4;

                    if (!$const->value instanceof Node\Scalar) {
                        // Constant scalar expressions
                        $versions['php.min'] = '5.6.0';
                    }
                    $const->setAttribute('compatinfo', $versions);
                }
                $this->computeConstantVersions($const, $const->name);
            }

        } elseif ($node instanceof Node\Expr\ConstFetch) {
            $name = (string) $node->name;
            $this->computeConstantVersions($node, $name);

        } elseif ($node instanceof Node\Scalar\MagicConst) {
            $this->computeConstantVersions($node, $node->getName());
        }
    }

    /**
     * Update the base version if current ref version is greater
     *
     * @param string $current Current version
     * @param string &$base   Base version
     *
     * @return void
     */
    protected static function updateVersion($current, &$base)
    {
        if (version_compare($current, $base, 'gt')) {
            $base = $current;
        }
    }

    /**
     * Update an element versions of the project
     *
     * @param string $element
     * @param string $name
     * @param array  $versions
     *
     * @return void
     */
    protected function updateElementVersion($element, $name, $versions)
    {
        $versions = array_merge(self::$php4, $versions);

        if (!isset($this->metrics[$element][$name])) {
            $versions['matches'] = 0;
            $this->metrics[$element][$name] = $versions;
        }

        if (isset($this->metrics[$element][$name]['arg.max'])
            && $this->metrics[$element][$name]['arg.max'] < $versions['arg.max']
        ) {
            $this->metrics[$element][$name]['arg.max'] = $versions['arg.max'];
        }

        self::updateVersion(
            $versions['php.min'],
            $this->metrics[$element][$name]['php.min']
        );
        self::updateVersion(
            $versions['php.max'],
            $this->metrics[$element][$name]['php.max']
        );

        if ('user' == $versions['ext.name']) {
            return;
        }
        self::updateVersion(
            $versions['ext.min'],
            $this->metrics[$element][$name]['ext.min']
        );
        self::updateVersion(
            $versions['ext.max'],
            $this->metrics[$element][$name]['ext.max']
        );
    }

    /**
     * Updates parent container (class|interface|trait|namespace|method)
     *
     * @param array $versions
     *
     * @return void
     */
    protected function updateContextVersion($versions)
    {
        list($element, $name) = array_pop($this->contextStack);
        $this->contextStack[] = array($element, $name);

        $this->updateElementVersion(
            $element,
            $name,
            $versions
        );
    }

    /**
     * Update the global versions of the project
     *
     * @param string $min The PHP min version to check
     * @param string $max The PHP max version to check
     *
     * @return void
     */
    protected function updateGlobalVersion($min, $max)
    {
        if (empty($this->metrics['versions'])) {
            $this->metrics['versions'] = array(
                'php.min'  => '4.0.0',
                'php.max'  => '',
            );
        }

        self::updateVersion(
            $min,
            $this->metrics['versions']['php.min']
        );
        self::updateVersion(
            $max,
            $this->metrics['versions']['php.max']
        );
    }

    /**
     * Initialize a new User Namespace
     *
     * @param Node $node
     *
     * @return void
     */
    private function iniUsertNamespace(Node $node)
    {
        if (!isset($node->name)) {
            // Namespace without name
            $node->name = new Node\Name(self::GLOBAL_NAMESPACE);
        }

        $element  = 'namespaces';
        $name     = (string)$node->name;
        $versions = array('php.min' => '5.3.0');
        $this->updateElementVersion($element, $name, $versions);
        $this->contextStack[] = array($element, $name);
    }

    /**
     * Initialize a new User Class
     *
     * @param Node $node
     *
     * @return void
     */
    private function initUserClass(Node $node)
    {
        if (isset($node->namespacedName)
            && $node->namespacedName instanceof Node\Name
            && $node->namespacedName->isQualified()
        ) {
            $min = '5.3.0';
        } else {
            if ($node->isAbstract()
                || $node->isFinal()
            ) {
                $min = '5.0.0';
            } else {
                $min = '4.0.0';
            }
        }
        $max = '';

        // parent class
        if (isset($node->extends)) {
            if ($node->extends->isFullyQualified()) {
                $versions = array('php.min' => '5.3.0');
            } else {
                $versions = array();
            }
            $name = (string) $node->extends;
            $this->updateElementVersion('classes', $name, $versions);
            ++$this->metrics['classes'][$name]['matches'];
        }

        // interfaces
        foreach ($node->implements as $interface) {
            $name     = (string) $interface;
            $versions = $this->references->find('interfaces', $name);
            $this->updateElementVersion('interfaces', $name, $versions);

            $this->updateVersion($versions['php.min'], $min);
            $this->updateVersion($versions['php.max'], $max);
        }

        $element  = 'classes';
        $name     = (string) $node->namespacedName;
        $versions = array('php.min' => $min, 'php.max' => $max);
        $this->updateElementVersion($element, $name, $versions);
        $this->contextStack[] = array($element, $name);
    }

    /**
     * Initialize a new User Interface
     *
     * @param Node $node
     *
     * @return void
     */
    private function initUserInterface(Node $node)
    {
        if (isset($node->namespacedName)
            && $node->namespacedName instanceof Node\Name
            && $node->namespacedName->isQualified()
        ) {
            $min = '5.3.0';
        } else {
            $min = '5.0.0';
        }

        $element  = 'interfaces';
        $name     = (string)$node->namespacedName;
        $versions = array('php.min' => $min);
        $this->updateElementVersion($element, $name, $versions);
        $this->contextStack[] = array($element, $name);
    }

    /**
     * Initialize a new User Trait
     *
     * @param Node $node
     *
     * @return void
     */
    private function initUserTrait(Node $node)
    {
        $min      = '5.4.0';
        $element  = 'traits';
        $name     = (string)$node->namespacedName;
        $versions = array('php.min' => $min);
        $this->updateElementVersion($element, $name, $versions);
        $this->contextStack[] = array($element, $name);
    }

    /**
     * Initialize a new User Function (anonymous or qualified) or a Method
     *
     * @param Node $node
     *
     * @return void
     */
    private function initUserFunction(Node $node)
    {
        $this->initLocalScope();

        if ($node instanceof Node\Stmt\ClassMethod) {
            list($element, $name) = array_pop($this->contextStack);
            $this->contextStack[] = array($element, $name);

            $element  = 'methods';
            $name     = sprintf('%s::%s', $name, $node->name);
            $versions = array();
            $this->updateElementVersion($element, $name, $versions);
            $this->contextStack[] = array($element, $name);
            return;
        }

        if ($node instanceof Node\Expr\Closure) {
            $min  = '5.3.0';
            $name = sprintf(
                'closure-%d-%d',
                $node->getAttribute('startLine', 0),
                $node->getAttribute('endLine', 0)
            );

        } else {
            if (isset($node->namespacedName)
                && $node->namespacedName instanceof Node\Name
                && $node->namespacedName->isQualified()
            ) {
                $min = '5.3.0';
            } else {
                $min = '4.0.0';
            }
            $name = (string)$node->namespacedName;
        }

        $element  = 'functions';
        $versions = array('php.min' => $min);
        $this->updateElementVersion($element, $name, $versions);
        $this->contextStack[] = array($element, $name);
    }

    /**
     * Checks for function arguments
     * (anonymous or qualified function, class|interface|trait method)
     *
     * @param Node $node
     *
     * @return void
     * @link http://www.php.net/manual/en/functions.arguments.php
     */
    private function initFunctionArguments(Node $node)
    {
        list($element, $name) = array_pop($this->contextStack);
        $this->contextStack[] = array($element, $name);

        if ($node->variadic) {
            // Variadic functions
            $this->updateVersion('5.6.0', $this->localVersions['php.min']);

        } elseif ($node->type instanceof Node\Name\FullyQualified) {
            // type hint

            // updates container (class, interface or trait) versions
            $versions = array('php.min' => '5.3.0');
            $this->updateElementVersion($element, $name, $versions);

            // introduces parameter object (if not yet defined)
            $name = (string)$node->type;
            $this->updateElementVersion('classes', $name, $versions);
            ++$this->metrics['classes'][$name]['matches'];
        }
    }

    /**
     * Initialize local scope environment (reference versions and class aliases)
     *
     * @return void
     */
    private function initLocalScope()
    {
        /*
         * reset class aliases
         * to resolve method calls in local scope (class method or function)
         */
        $this->aliases = array();

        // initialize reference versions
        $this->localVersions = self::$php4;
    }

    /**
     * Creates an alias that identify the original class.
     *
     * @param Node $node
     *
     * @return void
     */
    private function initClassAliasResolver(Node $node)
    {
        // variable or property that hold an instance of a new class statement
        $class = $node->expr->class;

        if (!$class instanceof Node\Name) {
            /*
             * when the class name is an expression,
             * we consider it as unresolved
             */
            return;
        }
        $assign = $node->var;
        if ($assign instanceof Node\Expr\PropertyFetch
            && is_string($assign->name)
        ) {
            $property = $assign->name;

            if ($assign->var instanceof Node\Expr\Variable
                && is_string($assign->var->name)
                && is_string($property)
            ) {
                $object = $assign->var->name;

                $this->aliases[$object .'_'. $property] = (string) $class;
            }

        } elseif ($assign instanceof Node\Expr\Variable
            && is_string($assign->name)
        ) {
            $this->aliases[$assign->name] = (string) $class;
        }
    }

    /**
     * Compute the namespace's version.
     *
     * This is the sum of all versions of class, interface, trait, function,
     * and internal function.
     *
     * @return void
     */
    private function computeNamespaceVersions()
    {
        list($element, $name) = array_pop($this->contextStack);

        $versions = $this->metrics[$element][$name];

        if (self::GLOBAL_NAMESPACE == $name) {
            // global namespace is part of Core
            $versions['ext.min'] = '4.0.0';
            $this->updateElementVersion('extensions', $versions['ext.name'], $versions);
        }

        $this->updateGlobalVersion($versions['php.min'], $versions['php.max']);
    }

    /**
     * Compute the class's version.
     *
     * This is the sum of all method's versions
     *
     * @param Node $node
     *
     * @return void
     */
    private function computeClassVersions(Node $node)
    {
        // remove class context
        array_pop($this->contextStack);

        $versions = $this->metrics['classes'][(string)$node->namespacedName];

        $this->updateContextVersion($versions);
    }

    /**
     * Compute the interface's version.
     *
     * @param Node $node
     *
     * @return void
     */
    private function computeInterfaceVersions(Node $node)
    {
        // remove interface context
        array_pop($this->contextStack);

        $versions = $this->metrics['interfaces'][(string)$node->namespacedName];

        $this->updateContextVersion($versions);
    }

    /**
     * Compute the trait's version.
     *
     * This is the sum of all method's versions
     *
     * @param Node $node
     *
     * @return void
     */
    private function computeTraitVersions(Node $node)
    {
        // remove trait context
        array_pop($this->contextStack);

        $versions = $this->metrics['traits'][(string)$node->namespacedName];

        $this->updateContextVersion($versions);
    }

    /**
     * Compute the function's version.
     *
     * This is the sum of all extension's elements versions
     *
     * @param Node $node
     *
     * @return void
     */
    private function computeFunctionVersions(Node $node)
    {
        // remove function context
        array_pop($this->contextStack);

        $this->updateContextVersion($this->localVersions);
    }

    /**
     * Compute the version of the class called.
     *
     * @param Node $node
     *
     * @return void
     */
    private function computeClassCallVersions(Node $node)
    {
        $element = (string) $node->class;

        $this->computeInternalVersions($node, $element, 'classes');
    }

    /**
     * Compute the version of the function called (user or internal).
     *
     * @param Node $node
     *
     * @return void
     */
    private function computeFunctionCallVersions(Node $node)
    {
        $element = (string) $node->name;

        $this->computeInternalVersions($node, $element, 'functions');

        if (strcasecmp('define', $element) === 0) {
            // user defined constant
            $name = $node->args[0]->value;
            if (!$name instanceof Node\Scalar\String) {
                // cannot resolved indirect definition
                return;
            }
            $this->updateElementVersion('constants', $name->value, self::$php4);
            return;
        }

        $conditionalFunctions = array(
            'extension_loaded' => 'extensions',
            'function_exists'  => 'functions',
            'class_exists'     => 'classes',
            'interface_exists' => 'interfaces',
            'trait_exists'     => 'traits',
            'defined'          => 'constants',
        );

        if (array_key_exists($element, $conditionalFunctions)) {
            // conditional functions
            $name    = $element;
            $context = $conditionalFunctions[$element];

            $element = $node->args[0]->value;

            if (!$element instanceof Node\Scalar\String) {
                // cannot resolve variable argument
                return;
            }
            $versions = $this->references->find($context, $element->value);

            // marked argument as optional
            $this->updateElementVersion($context, $element->value, $versions);
            $this->metrics[$context][$element->value]['optional'] = true;

            // update versions of conditional elements
            $condition = sprintf('%s(%s)', $name, $element->value);
            $this->updateElementVersion('conditions', $condition, $versions);
            ++$this->metrics['conditions'][$condition]['matches'];
        }
    }

    /**
     * Compute the version of the method's class called.
     *
     * @param Node $node
     *
     * @return void
     */
    private function computeClassMethodCallVersions(Node $node)
    {
        // direct call from a local variable or a property
        $caller = $node->var;

        if ($caller instanceof Node\Expr\PropertyFetch) {
            if (!is_string($caller->name)) {
                // indirect method call
                return;
            }
            $propertyName = $caller->name;
            if ($caller->var instanceof Node\Expr\Variable
                && is_string($caller->var->name)
                && isset($this->aliases[$caller->var->name . '_' . $propertyName])
            ) {
                $qualifiedClassName = $this->aliases[$caller->var->name . '_' . $propertyName];
            } else {
                // class name resolver failure
                return;
            }

        } elseif ($caller instanceof Node\Expr\Variable) {
            if (!is_string($caller->name)) {
                // indirect method call
                return;
            }
            if (!isset($this->aliases[$caller->name])) {
                // class name resolver failure
                return;
            }
            $qualifiedClassName = $this->aliases[$caller->name];

        } else {
            // indirect method call
            return;
        }
    }

    /**
     * Compute the version of the constant (user or internal).
     *
     * @param Node   $node
     * @param string $name
     *
     * @return void
     */
    private function computeConstantVersions(Node $node, $name)
    {
        $this->computeInternalVersions($node, $name, 'constants');
        $this->updateContextVersion($this->localVersions);
    }

    /**
     * Compute the version of specific PHP feature.
     *
     * @param Node $node
     *
     * @return void
     */
    private function computePhpFeatureVersions(Node $node)
    {
        list($element, $name) = array_pop($this->contextStack);
        $this->contextStack[] = array($element, $name);

        if ($node instanceof Node\Stmt\Use_) {
            if ($node::TYPE_FUNCTION || $node::TYPE_CONSTANT) {
                // use const, use function
                $versions = array('php.min' => '5.6.0');
                $this->updateElementVersion($element, $name, $versions);
            }

        } elseif ($node instanceof Node\Expr\Array_) {
            if ($this->isShortArraySyntax($this->tokens, $node)) {
                // Array Short Syntax
                // http://php.net/manual/en/migration54.new-features.php
                $versions = array('php.min' => '5.4.0');
                $this->updateElementVersion($element, $name, $versions);
            }

        } elseif ($node instanceof Node\Expr\ArrayDimFetch
            && $node->var instanceof Node\Expr\FuncCall
        ) {
            // Array Dereferencing
            // http://php.net/manual/en/migration54.new-features.php
            $versions = array('php.min' => '5.4.0');
            $this->updateElementVersion($element, $name, $versions);
        }
    }

    /**
     * Compute the version of an internal function.
     *
     * @param Node   $node
     * @param string $element
     * @param string $context
     *
     * @return void
     */
    private function computeInternalVersions(Node $node, $element, $context)
    {
        $versions = $node->getAttribute('compatinfo');
        if ($versions === null) {
            // find reference info
            $versions = $this->references->find($context, $element, count($node->args));

            if (is_array($node->args)) {
                foreach ($node->args as $arg) {
                    if ($arg->value instanceof Node\Expr\BinaryOp\Pow) {
                        // Exponentiation
                        $this->updateVersion('5.6.0', $versions['php.min']);
                    }
                }
            }

            // cache to speed-up later uses
            $node->setAttribute('compatinfo', $versions);
        }
        $node->setAttribute('fileName', $this->file);

        // update versions of $element
        $this->updateElementVersion($context, $element, $versions);
        ++$this->metrics[$context][$element]['matches'];

        if ($versions['ext.name'] !== 'user') {
            // update versions of extension's $element
            $this->updateElementVersion('extensions', $versions['ext.name'], $versions);

            $this->updateVersion($versions['ext.min'], $this->localVersions['ext.min']);
            $this->updateVersion($versions['ext.max'], $this->localVersions['ext.max']);
        }

        $this->updateVersion($versions['php.min'], $this->localVersions['php.min']);
        $this->updateVersion($versions['php.max'], $this->localVersions['php.max']);

        $this->updateContextVersion($this->localVersions);
    }
}
