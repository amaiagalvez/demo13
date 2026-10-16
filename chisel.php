<?php

require getenv('LARAVEL_INSTALLER_AUTOLOADER') ?: __DIR__.'/vendor/autoload.php';

use Laravel\Chisel\Chisel;

use function Laravel\Prompts\task;

use Laravel\Prompts\Support\Logger;
use Symfony\Component\Process\Process;

function chiselRun(array $command, string $label): void
{
    $process = task(
        label: $label,
        keepSummary: true,
        callback: function (Logger $logger) use ($command) {
            $process = new Process($command);
            $process->run(function ($type, $line) use ($logger) {
                $logger->line($line);
            });

            if ($process->isSuccessful()) {
                $logger->success(implode(' ', $command));

                return $process;
            }

            $logger->error(implode(' ', $command));
            $logger->error('Error output: '.trim($process->getErrorOutput()));
            $logger->error('Chisel: Your project may be in a partially-modified state — review the output above before continuing.');

            return $process;
        },
    );

    if (! $process->isSuccessful()) {
        exit($process->getExitCode());
    }
}

return Chisel::script(__DIR__)
    ->apply(function (Chisel $c): void {
        chiselRun(['composer', 'lint'], 'Composer Lint');

        $c->file('composer.json')
            ->removeLinesContaining('"@php artisan install:features --ansi"');

        $c->files(
            'app/Console/Commands/InstallFeaturesCommand.php',
            'chisel.php',
            'chisel-paths.php',
        )->delete();
    });
