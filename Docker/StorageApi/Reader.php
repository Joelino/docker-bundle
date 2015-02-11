<?php

namespace Keboola\DockerBundle\Docker\StorageApi;

use Keboola\DockerBundle\Docker\Configuration\Input;
use Keboola\StorageApi\Aws\S3\S3Client;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Options\GetFileOptions;
use Keboola\StorageApi\Options\ListFilesOptions;
use Keboola\StorageApi\TableExporter;
use Symfony\Component\Filesystem\Filesystem;

class Reader
{

    /**
     * @var Client
     */
    protected $client;


    /**
     * @var
     */
    protected $format = 'yaml';

    /**
     * @return mixed
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @param mixed $format
     * @return $this
     */
    public function setFormat($format)
    {
        $this->format = $format;
        return $this;
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param Client $client
     * @return $this
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->setClient($client);
    }

    /**
     * @param $configuration array
     * @param $destination string Destination directory
     */
    public function downloadFiles($configuration, $destination)
    {
        $options = new ListFilesOptions();
        if (isset($configuration["tags"]) && count($configuration["tags"])) {
            $options->setTags($configuration["tags"]);
        }
        if (isset($configuration["query"])) {
            $options->setQuery($configuration["query"]);
        }
        $files = $this->getClient()->listFiles($options);
        foreach ($files as $file) {
            $fileInfo = $this->getClient()->getFile($file["id"], (new GetFileOptions())->setFederationToken(true));
            $this->downloadFile($fileInfo, $destination . "/" . $fileInfo["id"]);
            $this->writeFileManifest($fileInfo, $destination . "/" . $fileInfo["id"] . ".manifest");
        }
    }

    /**
     * @param $fileInfo
     * @param $destination
     * @throws \Exception
     */
    protected function writeFileManifest($fileInfo, $destination)
    {
        $manifest = array(
            "id" => $fileInfo["id"],
            "name" => $fileInfo["name"],
            "created" => $fileInfo["created"],
            "is_public" => $fileInfo["isPublic"],
            "is_encrypted" => $fileInfo["isEncrypted"],
            "tags" => $fileInfo["tags"],
            "max_age_days" => $fileInfo["maxAgeDays"],
            "size_bytes" => $fileInfo["sizeBytes"]
        );

        $adapter = new Input\File\Manifest\Adapter();
        $adapter->setFormat($this->getFormat());
        $adapter->setConfig($manifest);
        $adapter->writeToFile($destination);
    }

    /**
     * @param $fileInfo array file info from Storage API
     * @param $destination string Destination file path
     */
    protected function downloadFile($fileInfo, $destination)
    {
        // Initialize S3Client with credentials from Storage API
        $s3Client = S3Client::factory(array(
            "key" => $fileInfo["credentials"]["AccessKeyId"],
            "secret" => $fileInfo["credentials"]["SecretAccessKey"],
            "token" => $fileInfo["credentials"]["SessionToken"]
        ));

        // CURL options
        $s3Client->getConfig()->set('curl.options', array(
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0
        ));

        $fs = new Filesystem();
        if (!$fs->exists(dirname($destination))) {
            $fs->mkdir($destination);
        }

        /**
         * NonSliced file, just move from temp to destination file
         */
        $s3Client->getObject(array(
            'Bucket' => $fileInfo["s3Path"]["bucket"],
            'Key'    => $fileInfo["s3Path"]["key"],
            'SaveAs' => $destination
        ));
    }

    /**
     * @param $configuration array list of input mappings
     * @param $destination string destination folder
     */
    public function downloadTables($configuration, $destination)
    {
        $tableExporter = new TableExporter($this->getClient());
        foreach ($configuration as $table) {
            if (!isset($table["destination"])) {
                $file = $destination . "/" . $table["source"] . ".csv";
            } else {
                $file = $destination . "/" . $table["destination"] . ".csv";
            }
            $exportOptions = array();
            if (isset($table["columns"]) && count($table["columns"])) {
                $exportOptions["columns"] = $table["columns"];
            }
            if (isset($table["changed_since"])) {
                $exportOptions["changedSince"] = $table["changed_since"];
            }
            if (isset($table["where_column"]) && count($table["where_values"])) {
                $exportOptions["whereColumn"] = $table["where_column"];
                $exportOptions["whereValues"] = $table["where_values"];
                $exportOptions["whereOperator"] = $table["where_operator"];
            }
            $tableExporter->exportTable($table["source"], $file, $exportOptions);
            $tableInfo = $this->getClient()->getTable($table["source"]);
            $this->writeTableManifest($tableInfo, $file . ".manifest");
        }
    }

    /**
     * @param $tableInfo
     * @param $destination
     * @throws \Exception
     */
    protected function writeTableManifest($tableInfo, $destination)
    {
        $manifest = array(
            "id" => $tableInfo["id"],
            "uri" => $tableInfo["uri"],
            "name" => $tableInfo["name"],
            "primary_key" => $tableInfo["primaryKey"],
            "indexed_columns" => $tableInfo["indexedColumns"],
            "created" => $tableInfo["created"],
            "last_change_date" => $tableInfo["lastChangeDate"],
            "last_import_date" => $tableInfo["lastImportDate"],
            "rows_count" => $tableInfo["rowsCount"],
            "data_size_bytes" => $tableInfo["dataSizeBytes"],
            "is_alias" => $tableInfo["isAlias"],
            "columns" => $tableInfo["columns"],
            "attributes" => array()
        );
        foreach ($tableInfo["attributes"] as $attribute) {
            $manifest["attributes"][] = array(
                "name" => $attribute["name"],
                "value" => $attribute["value"],
                "protected" => $attribute["protected"]
            );
        }

        $adapter = new Input\Table\Manifest\Adapter();
        $adapter->setFormat($this->getFormat());
        $adapter->setConfig($manifest);
        $adapter->writeToFile($destination);
    }
}
