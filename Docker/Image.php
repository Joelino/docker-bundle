<?php

namespace Keboola\DockerBundle\Docker;

use Keboola\DockerBundle\Docker\Image\DockerHub;
use Syrup\ComponentBundle\Exception\UserException;

class Image
{
    /**
     *
     * Image Id
     *
     * @var string
     */
    protected $id;

    /**
     *
     * Memory allowance
     *
     * @var string
     */
    protected $memory = '64m';

    /**
     *
     * Processor allowance
     *
     * @var int
     */
    protected $cpuShares = 1024;

    /**
     * @var string
     */
    protected $configFormat = 'yaml';

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getMemory()
    {
        return $this->memory;
    }

    /**
     * @param string $memory
     * @return $this
     */
    public function setMemory($memory)
    {
        $this->memory = $memory;
        return $this;
    }

    /**
     * @return int
     */
    public function getCpuShares()
    {
        return $this->cpuShares;
    }

    /**
     * @param int $cpuShares
     * @return $this
     */
    public function setCpuShares($cpuShares)
    {
        $this->cpuShares = $cpuShares;
        return $this;
    }

    /**
     * @return string
     */
    public function getConfigFormat()
    {
        return $this->configFormat;
    }

    /**
     * @param $configFormat
     * @return $this
     * @throws \Exception
     */
    public function setConfigFormat($configFormat)
    {
        if (!in_array($configFormat, array('yaml', 'json'))) {
            throw new \Exception("Configuration format '{$configFormat}' not supported");
        }
        $this->configFormat = $configFormat;
        return $this;
    }

    /**
     *
     */
    public static function factory($config=array())
    {
        if (isset($config["definition"]["dockerhub"])) {
            $instance = new DockerHub();
            $instance->setDockerHubImageId($config["definition"]["dockerhub"]);
        } else {
            $instance = new self;
        }
        if (isset($config["id"])) {
            $instance->setId($config["id"]);
        }
        if (isset($config["cpu_shares"])) {
            $instance->setCpuShares($config["cpu_shares"]);
        }
        if (isset($config["memory"])) {
            $instance->setMemory($config["memory"]);
        }
        return $instance;
    }

    /**
     * @param Container $container
     * @throws \Exception
     */
    public function prepare(Container $container)
    {
        throw new \Exception("Not implemented");
    }
}
