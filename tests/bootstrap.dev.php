<?php

$baseDir   = dirname(__DIR__);
$vendorDir = $baseDir . '/vendor';

$loader = require_once $vendorDir . '/autoload.php';
$loader->addClassMap(
    array(
        'Bartlett\Tests\Reflect\Analyser\FooAnalyser'
            => __DIR__ . '/Analyser/FooAnalyser.php',
        'Bartlett\Tests\Reflect\Analyser\BarAnalyser'
            => __DIR__ . '/Analyser/BarAnalyser.php',
        'Bartlett\Tests\Reflect\Model\GenericModelTest'
            => __DIR__ . '/Model/GenericModelTest.php',
        'Bartlett\LoggerTestListener'
            =>  __DIR__ . '/../vendor/bartlett/phpunit-loggertestlistener/src/Bartlett/LoggerTestListener.php',
        'Monolog\Handler\GrowlHandler'
            =>  __DIR__ . '/../vendor/bartlett/phpunit-loggertestlistener/extra/GrowlHandler.php',
        'Monolog\Handler\AdvancedFilterHandler'
            =>  __DIR__ . '/../vendor/bartlett/phpunit-loggertestlistener/extra/AdvancedFilterHandler.php',
    )
);

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\GrowlHandler;
use Monolog\Handler\AdvancedFilterHandler;

class Psr3Logger extends Logger
{
    public function __construct($name = 'PHPUnit')
    {
        // filter to be notified only on end test suite.
        $filter1 = function($record) {
            return (preg_match('/^TestSuite(.*)ended\. Tests/', $record['message']) === 1);
        };

        $stream = new RotatingFileHandler('/var/logs/phpreflect.log', 30);
        $stream->setFilenameFormat('{filename}-{date}', 'Ymd');

        $handlers = array($stream);

        try {
            $growl = new GrowlHandler(array(), Logger::NOTICE);

            $filterGrowl = new AdvancedFilterHandler(
                $growl,
                array($filter1)
            );
            $handlers[] = $filterGrowl;

        } catch (\Exception $e) {
            // Growl client is probably not started
            echo $e->getMessage(), PHP_EOL, PHP_EOL;
        }

        parent::__construct($name, $handlers);
    }
}
