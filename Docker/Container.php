<?php

namespace Keboola\DockerBundle\Docker;

use Monolog\Logger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Keboola\Syrup\Exception\ApplicationException;
use Keboola\Syrup\Exception\UserException;

class Container
{
    /**
     *
     * Image Id
     *
     * @var string
     */
    protected $id;

    /**
     * @var Image
     */
    protected $image;

    /**
     * @var string
     */
    protected $version = 'latest';

    /**
     * @var string
     */
    protected $dataDir;

    /**
     * @var array
     */
    protected $environmentVariables = array();

    /**
     * @var Logger
     */
    private $log;

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
     * @return Image
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * @param Image $image
     * @return $this
     */
    public function setImage($image)
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param string $version
     * @return $this
     */
    public function setVersion($version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @param Image $image
     * @param Logger $logger
     */
    public function __construct(Image $image, Logger $logger)
    {
        $this->log = $logger;
        $this->setImage($image);
    }

    /**
     * @return string
     */
    public function getDataDir()
    {
        return $this->dataDir;
    }

    /**
     * @param string $dataDir
     * @return $this
     */
    public function setDataDir($dataDir)
    {
        $this->dataDir = $dataDir;
        return $this;
    }

    /**
     * @return array
     */
    public function getEnvironmentVariables()
    {
        return $this->environmentVariables;
    }

    /**
     * @param array $environmentVariables
     * @return $this
     */
    public function setEnvironmentVariables($environmentVariables)
    {
        $this->environmentVariables = $environmentVariables;
        return $this;
    }

    /**
     * @param string $containerId container id
     * @return Process
     * @throws ApplicationException
     */
    public function run($containerId = "")
    {
        if (!$this->getDataDir()) {
            throw new ApplicationException("Data directory not set.");
        }

        $id = $this->getImage()->prepare($this);
        $this->setId($id);

        // Run container
        $process = new Process($this->getRunCommand($containerId));
        $process->setTimeout($this->getImage()->getProcessTimeout());

        try {
            $this->log->debug("Executing docker process.");
            if ($this->getImage()->isStreamingLogs()) {
                $process->run(function ($type, $buffer) {
                    if ($type === Process::ERR) {
                        $this->log->error($buffer);
                    } else {
                        $this->log->info($buffer);
                    }
                });
            } else {
                $process->run();
            }
            $this->log->debug("Docker process finished.");
        } catch (ProcessTimedOutException $e) {
            throw new UserException(
                "Running container exceeded the timeout of {$this->getImage()->getProcessTimeout()} seconds."
            );
        }

        if (!$process->isSuccessful()) {
            $message = substr($process->getErrorOutput(), 0, 8192);
            if (!$message) {
                $message = substr($process->getOutput(), 0, 8192);
            }
            if (!$message) {
                $message = "No error message.";
            }
            $data = [
                "output" => substr($process->getOutput(), 0, 8192),
                "errorOutput" => substr($process->getErrorOutput(), 0, 8192)
            ];

            if ($process->getExitCode() == 1) {
                throw new UserException("Container '{$this->getId()}' failed: {$message}", null, $data);
            } else {
                // syrup will make sure that the actual exception message will be hidden to end-user
                throw new ApplicationException(
                    "Container '{$this->getId()}' failed: ({$process->getExitCode()}) {$message}",
                    null,
                    $data
                );
            }
        }
        return $process;
    }

    /**
     * @param $root
     * @return $this
     */
    public function createDataDir($root)
    {
        $fs = new Filesystem();
        $structure = array(
            $root . "/data",
            $root . "/data/in",
            $root . "/data/in/tables",
            $root . "/data/in/files",
            $root . "/data/out",
            $root . "/data/out/tables",
            $root . "/data/out/files"
        );

        $fs->mkdir($structure);
        $this->setDataDir($root . "/data");
        return $this;
    }

    /**
     * Remove whole directory structure
     */
    public function dropDataDir()
    {
        $fs = new Filesystem();
        $structure = array(
            $this->getDataDir() . "/in/tables",
            $this->getDataDir() . "/in/files",
            $this->getDataDir() . "/in",
            $this->getDataDir() . "/out/files",
            $this->getDataDir() . "/out/tables",
            $this->getDataDir() . "/out",
            $this->getDataDir()
        );
        $finder = new Finder();
        $finder->files()->in($structure);
        $fs->remove($finder);
        $fs->remove($structure);
    }

    /**
     * @param string $containerId
     * @return string
     */
    public function getRunCommand($containerId = "")
    {
        setlocale(LC_CTYPE, "en_US.UTF-8");
        $envs = "";
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $dataDir = str_replace(DIRECTORY_SEPARATOR, '/', str_replace(':', '', '/' . lcfirst($this->dataDir)));
            foreach ($this->getEnvironmentVariables() as $key => $value) {
                $envs .= " -e " . escapeshellarg($key) . "=" . str_replace(' ', '\\ ', escapeshellarg($value));
            }
            $command = "docker run";
        } else {
            $dataDir = $this->dataDir;
            foreach ($this->getEnvironmentVariables() as $key => $value) {
                $envs .= " -e \"" . str_replace('"', '\"', $key) . "=" . str_replace('"', '\"', $value). "\"";
            }
            $command = "sudo docker run";
        }

        $command .= " --volume=" . escapeshellarg($dataDir) . ":/data"
            . " --memory=" . escapeshellarg($this->getImage()->getMemory())
            . " --cpu-shares=" . escapeshellarg($this->getImage()->getCpuShares())
            . $envs
            . " --rm"
            . " --name=" . escapeshellarg($containerId)
            . " " . escapeshellarg($this->getId());
        return $command;
    }
}
