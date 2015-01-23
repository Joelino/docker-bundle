<?php

namespace Keboola\DockerBundle\Tests;

use Keboola\DockerBundle\Docker\Configuration\Input\Table\Manifest;
use Symfony\Component\Config\Definition\Processor;

class InputTableManifestConfigurationTest extends \PHPUnit_Framework_TestCase
{

    /**
     *
     */
    public function testConfiguration()
    {
        $processor = new Processor();
        $configurationDefinition = new Manifest();
        $config = array(
            "id" => "in.c-docker-test.test",
            "uri" => "https://connection.keboola.com//v2/storage/tables/in.c-docker-test.test",
            "name" => "test",
            "primary_key" => array("col1", "col2"),
            "indexed_columns" => array("col1", "col2"),
            "created" => "2015-01-23T04:11:18+0100",
            "last_import_date" => "2015-01-23T04:11:18+0100",
            "last_change_date" => "2015-01-23T04:11:18+0100",
            "rows_count" => 100,
            "data_size_bytes" => 32768,
            "is_alias" => false,
            "columns" => array("col1", "col2", "col3", "col4"),
            "attributes" => array(array("name" => "test", "value" => "test", "protected" => false))
        );
        $expectedResponse = $config;
        $processedConfiguration = $processor->processConfiguration($configurationDefinition, array("config" => $config));
        $this->assertEquals($expectedResponse, $processedConfiguration);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage The child node "id" at path "table" must be configured.
     */
    public function testEmptyConfiguration()
    {
        $processor = new Processor();
        $configurationDefinition = new Manifest();
        $processor->processConfiguration($configurationDefinition, array("config" => array()));
    }

}
