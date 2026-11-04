<?php

declare(strict_types=1);

test('it reads the Betterleaks version from composer.json and reports a missing release', function () {
    $project = temporaryDirectory('snitch-project');
    file_put_contents(
        "{$project}/composer.json",
        json_encode(['extra' => ['snitch' => ['version' => '0.0.0-does-not-exist']]]),
    );

    $command = 'cd '.escapeshellarg($project)
        .' && '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/bin/snitch').' 2>&1';

    exec($command, $output, $exitCode);

    expect($exitCode)->toBe(2)
        ->and(implode("\n", $output))->toContain('Download failed');
});