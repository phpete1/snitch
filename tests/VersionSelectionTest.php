<?php

declare(strict_types=1);

test('it reads the Betterleaks version from composer.json and reports a missing release', function () {
    $project = temporaryDirectory('snitch-project');
    file_put_contents(
        "{$project}/composer.json",
        json_encode(['extra' => ['snitch' => ['version' => '0.0.0-does-not-exist']]]),
    );

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/bin/snitch'),
        $descriptors,
        $pipes,
        $project,
    );

    if (! is_resource($process)) {
        throw new \RuntimeException('Failed to start process');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    expect($exitCode)->toBe(2)
        ->and($stdout.$stderr)->toContain('Download failed');
});