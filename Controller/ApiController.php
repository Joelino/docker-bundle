<?php

namespace Keboola\DockerBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Syrup\ComponentBundle\Exception\UserException;

class ApiController extends \Syrup\ComponentBundle\Controller\ApiController
{

    public function preExecute(Request $request)
    {

        parent::preExecute($request);

        // Check list of components
        $components = $this->storageApi->indexAction();
        foreach($components["components"] as $c) {
            if ($c["id"] == $request->get("component")) {
                $component = $c;
            }
        }

        if (!isset($component)) {
            throw new UserException("Component '{$request->get("component")}' not found.");
        }

        $this->container->get('syrup.monolog.json_formatter')->setAppName($component["id"]);
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function runAction(Request $request) {
        if (!$request->get("component")) {
            throw new UserException("Component not set.");
        }

        if ($request->get("config") && $request->get("configData")) {
            throw new UserException("Cannot specify both 'config' and 'configData'.");
        }

        return parent::runAction($request);
    }

    /**
     *
     * Add component property to JSON
     *
     * @param Request $request
     * @return array
     */
    protected function getPostJson(Request $request)
    {
        $json = parent::getPostJson($request);
        $json["component"] = $request->get("component");
        return $json;
    }


}