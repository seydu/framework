<?php

namespace SparkTests\Configuration;

use Auryn\Injector;
use PHPUnit_Framework_TestCase as TestCase;

abstract class ConfigurationTestCase extends TestCase
{
    /**
     * @var Injector
     */
    protected $injector;

    /**
     * @return array
     */
    abstract protected function getConfigurations();

    public function setUp()
    {
        $this->injector = new Injector;

        foreach ($this->getConfigurations() as $config) {
            $config->apply($this->injector);
        }
    }
}
