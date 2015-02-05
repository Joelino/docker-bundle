<?php

namespace Keboola\DockerBundle\Tests;

use Keboola\Csv\CsvFile;
use Keboola\DockerBundle\Docker\Container;
use Keboola\DockerBundle\Docker\Executor;
use Keboola\DockerBundle\Docker\Image;
use Keboola\StorageApi\Client;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Class MockContainer
 * @package Keboola\DockerBundle\Tests
 */
class MockContainer extends Container
{
    /**
     * @return Process
     */
    public function run() {
        $fs = new Filesystem();
        $fs->dumpFile($this->getDataDir() .  "/out/tables/sliced.csv", "id,text,row_number\n1,test,1\n1,test,2\n1,test,3");
        $process = new Process('echo "Processed 1 rows."');
        $process->run();
        return $process;
    }
}

class ExecutorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $tmpDir;

    public function setUp()
    {
        // Create folders
        $root = "/tmp/docker/" . uniqid("", true);
        $fs = new Filesystem();
        $fs->mkdir($root);
        $this->tmpDir = $root;

        $this->client = new Client(array("token" => STORAGE_API_TOKEN));

        // Create bucket
        if (!$this->client->bucketExists("in.c-docker-test")) {
            $this->client->createBucket("docker-test", Client::STAGE_IN, "Docker Testsuite");
        }

        // Create table
        if (!$this->client->tableExists("in.c-docker-test.test")) {
            $csv = new CsvFile($this->tmpDir . "/upload.csv");
            $csv->writeRow(array("id", "text"));
            $csv->writeRow(array("test", "testtesttest"));
            $this->client->createTableAsync("in.c-docker-test", "test", $csv);
        }
    }

    public function tearDown()
    {
        // Delete local files
        $finder = new Finder();
        $fs = new Filesystem();
        $fs->remove($finder->files()->in($this->tmpDir));
        $fs->remove($this->tmpDir);

        // Delete tables
        foreach($this->client->listTables("in.c-docker-test") as $table) {
            $this->client->dropTable($table["id"]);
        }

        // Delete bucket
        $this->client->dropBucket("in.c-docker-test");
    }
    
    public function testExecutorRun()
    {
        $imageConfig = array(
            "definition" => array(
                "type" => "dockerhub",
                "uri" => "ondrejhlavacek/docker-demo"
            ),
            "cpu_shares" => 1024,
            "memory" => "64m",
            "configuration_format" => "yaml"
        );

        $config = array(
            "storage" => array(
                "input" => array(
                    "tables" => array(
                        array(
                            "source" => "in.c-docker-test.test"
                        )
                    )
                ),
                "output" => array(
                    "tables" => array(
                        array(
                            "source" => "sliced.csv",
                            "destination" => "in.c-docker-test.out"
                        )
                    )
                )
            ),
            "user" => array(
                "primary_key_column" => "id",
                "data_column" => "text",
                "string_length" => "4"
            )
        );

        $log = new \Symfony\Bridge\Monolog\Logger("null");
        $log->pushHandler(new \Monolog\Handler\NullHandler());

        $image = Image::factory($imageConfig);
        $container = new MockContainer($image);
        $executor = new Executor($this->client, $log);
        $executor->setTmpFolder($this->tmpDir);
        $process = $executor->run($container, $config);
        $this->assertEquals("Processed 1 rows.", trim($process->getOutput()));
    }

}
