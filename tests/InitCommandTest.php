<?php

declare(strict_types=1);

test('it creates the config and ignore files from stubs', function () {
    $project = temporaryDirectory('snitch-init');

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/bin/snitch').' init',
        $descriptors,
        $pipes,
        $project,
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Failed to start process');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    expect($exitCode)->toBe(0)
        ->and("{$project}/.betterleaks.toml")->toBeFile()
        ->and("{$project}/.betterleaksignore")->toBeFile()
        ->and(file_get_contents("{$project}/.betterleaks.toml"))->toBe(file_get_contents(dirname(__DIR__).'/stubs/betterleaks.toml'))
        ->and(file_get_contents("{$project}/.betterleaksignore"))->toBe(file_get_contents(dirname(__DIR__).'/stubs/betterleaksignore'));
});

test('it does not overwrite existing files', function () {
    $project = temporaryDirectory('snitch-init');
    file_put_contents("{$project}/.betterleaks.toml", 'existing');

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/bin/snitch').' init',
        $descriptors,
        $pipes,
        $project,
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Failed to start process');
    }

    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    expect($exitCode)->toBe(0)
        ->and(file_get_contents("{$project}/.betterleaks.toml"))->toBe('existing')
        ->and("{$project}/.betterleaksignore")->toBeFile();
});
