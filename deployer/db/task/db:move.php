<?php

namespace Deployer;

task('db:move', function () {
    if (!input()->hasArgument('stage')) {
        throw new \RuntimeException("The source instance is required for db:move command.");
    }
    if (input()->hasArgument('targetStage')) {
        $targetInstanceName = input()->getArgument('targetStage');
        $targetInstanceEnv = Deployer::get()->environments[$targetInstanceName];
        if ($targetInstanceName == null) {
            throw new \RuntimeException(
                "You must set the target instance the database will be copied to as second parameter."
            );
        }
        if ($targetInstanceName == 'live') {
            throw new \RuntimeException(
                "FORBIDDEN: For security its forbidden to move database to live instance!"
            );
        }
        if ($targetInstanceName == 'local') {
            throw new \RuntimeException(
                "FORBIDDEN: For synchro local database use: \ndep db:pull live"
            );
        }
    } else {
        throw new \RuntimeException(
            "The target stage is not set as second parameter. Move should be run as: dep db:move source target"
        );
    }

    $sourceInstance = get('server')['name'];

    $command = Task\Context::get()->getEnvironment()->parse("cd {{deploy_path}}/current && {{bin/php}} deployer.phar -q db:export");
    $databaseDumpResult = run($command);
    $dbExportOnTargetInstanceResponse = json_decode(trim($databaseDumpResult->toString()), true);
    if ($dbExportOnTargetInstanceResponse == null) {
        throw new \RuntimeException(
            "db:export failed on " . $sourceInstance . ". The database dumpCode is null. Try to call: \n" .
            $command . "\n" .
            "on " . $sourceInstance . " instance. \n" .
            "Export task returned: " . $databaseDumpResult->toString() . "\n" .
            "One of the reason can be PHP notices or warnings added to output."
        );
    }

    $dumpCode = $dbExportOnTargetInstanceResponse['dumpCode'];

    runLocally("{{deployer_exec}} db:download --dumpcode=$dumpCode", 0);
    runLocally("{{deployer_exec}} db:process_dump --dumpcode=$dumpCode", 0);

    if (get('instance') == $targetInstanceName) {
        runLocally("{{deployer_exec}} db:import --dumpcode=$dumpCode", 0);
    } else {
        runLocally("{{deployer_exec}} db:upload --dumpcode=$dumpCode", 0);
        run("cd " . $targetInstanceEnv->get('deploy_path') . "/current && " . $targetInstanceEnv->get('bin/php') .
            " deployer.phar -q db:import --dumpcode=$dumpCode");
    }
})->desc('Synchronize database between instances.');
