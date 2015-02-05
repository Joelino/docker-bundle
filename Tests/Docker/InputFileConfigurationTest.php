<?php

namespace Keboola\DockerBundle\Tests;

use Keboola\DockerBundle\Docker\Configuration\Input\File;

class InputFileConfigurationTest extends \PHPUnit_Framework_TestCase
{

    /**
     *
     */
    public function testConfiguration()
    {
        $config = array(
                "tags" => array("tag1", "tag2"),
                "query" => "esquery"
            );
        $expectedResponse = $config;
        $processedConfiguration = (new File())->parse(array("config" => $config));
        $this->assertEquals($expectedResponse, $processedConfiguration);

    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Invalid configuration for path "file": At least one of 'tags' or 'query' parameters must be defined.
     */
    public function testEmptyConfiguration()
    {
        (new File())->parse(array("config" => array()));
    }

}