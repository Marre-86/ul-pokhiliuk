<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use OpenApi\Generator;

#[Signature('app:generate-open-api')]
#[Description('Generate OpenAPI documentation')]
class GenerateOpenApi extends Command
{
    public function handle()
    {
        $generator = new Generator();
        $analysis = $generator->generate([
            'app/Http/Controllers',
        ]);

        $json = $analysis->toJson();
        $outputPath = public_path('api-docs/openapi.json');
        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }
        file_put_contents($outputPath, $json);

        $this->info('OpenAPI documentation generated successfully!');
        return Command::SUCCESS;
    }
}
