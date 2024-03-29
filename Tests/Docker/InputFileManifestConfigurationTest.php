<?php

namespace Keboola\DockerBundle\Tests;

use Keboola\DockerBundle\Docker\Configuration\Input\File\Manifest;

class InputFileManifestConfigurationTest extends \PHPUnit_Framework_TestCase
{

    /**
     *
     */
    public function testConfiguration()
    {
        $config = array(
            "id" => 1,
            "name" => "test",
            "created" => "2015-01-23T04:11:18+0100",
            "is_public" => false,
            "is_encrypted" => false,
            "tags" => array("tag1", "tag2"),
            "max_age_days" => 180,
            "size_bytes" => 4
        );
        $expectedResponse = $config;
        $processedConfiguration = (new Manifest())->parse(array("config" => $config));
        $this->assertEquals($expectedResponse, $processedConfiguration);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage The child node "id" at path "file" must be configured.
     */
    public function testEmptyConfiguration()
    {
        (new Manifest())->parse(array("config" => array()));
    }
}
