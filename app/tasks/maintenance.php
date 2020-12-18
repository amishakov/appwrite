<?php

global $cli;

require_once __DIR__.'/../init.php';

use Appwrite\Database\Database;
use Appwrite\Database\Document;
use Appwrite\Database\Adapter\MySQL as MySQLAdapter;
use Appwrite\Database\Adapter\Redis as RedisAdapter;
use Appwrite\Database\Validator\Authorization;
use Utopia\App;
use Utopia\CLI\Console;
use Utopia\Config\Config;

define("DELETE_QUEUE_NAME", "v1-deletes");
define("DELETE_CLASS_NAME", "DeletesV1");

// TODO: Think of a better way to access consoleDB
function getConsoleDB() {
    global $register;
    $consoleDB = new Database();
    $consoleDB->setAdapter(new RedisAdapter(new MySQLAdapter($register), $register));
    $consoleDB->setNamespace('app_console'); // Main DB
    $consoleDB->setMocks(Config::getParam('collections', []));
    return $consoleDB;
}

function notifyDeleteExecutionLogs(array $projectIds)
{
    Resque::enqueue(DELETE_QUEUE_NAME, DELETE_CLASS_NAME, [
        'collection' => Database::SYSTEM_COLLECTION_EXECUTIONS,
        'document' => new Document([
            'projectIds' => $projectIds
        ])
    ]);
}

function notifyDeleteAbuseLogs(array $projectIds) 
{
    Resque::enqueue(DELETE_QUEUE_NAME, DELETE_CLASS_NAME, [
        'collection' => Database::SYSTEM_COLLECTION_EXECUTIONS,
        'document' => new Document([
            'projectIds' => $projectIds
        ])
    ]);
}

function notifyDeleteAuditLogs(array $projectIds) 
{
    Resque::enqueue(DELETE_QUEUE_NAME, DELETE_CLASS_NAME, [
        'collection' => Database::SYSTEM_COLLECTION_EXECUTIONS,
        'document' => new Document([
            'projectIds' => $projectIds
        ])
    ]);
}

$cli
    ->task('maintenance')
    ->desc('Schedules maintenance tasks and publishes them to resque')
    ->action(function () {
        // # of days in seconds (1 day = 86400s)
        $interval = App::getEnv('_APP_MAINTENANCE_INTERVAL', '') + 0;
        //Convert Seconds to microseconds
        $interval = $interval * 1000000;

        $consoleDB = getConsoleDB();

        Console::loop(function() use ($consoleDB){

            Authorization::disable();
            $projects = $consoleDB->getCollection([
                'filters' => [
                    '$collection='.Database::SYSTEM_COLLECTION_PROJECTS,
                ],
            ]);
            Authorization::reset();

            $projectIds = array_map (function ($project) { 
                return $project->getId(); 
            }, $projects);

            notifyDeleteExecutionLogs($projectIds);
            notifyDeleteAbuseLogs($projectIds);
            notifyDeleteAuditLogs($projectIds);
            
        }, $interval);

    });