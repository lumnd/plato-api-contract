<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Console\ExitCode;

function template_pack_directory(string $case): string
{
    $directory = generate_output_directory('pack-' . $case);

    file_put_contents($directory . '/platform.php', <<<'PHP'
<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Contract\StandardRouteConvention;
use Lumnd\PlatoApiContract\Generation\Ownership;
use Lumnd\PlatoApiContract\Generation\TemplateContext;
use Lumnd\PlatoApiContract\Generation\TemplatePack;
use Lumnd\PlatoApiContract\Php\PhpTypes;

return TemplatePack::define('plain-php', new StandardRouteConvention(), __DIR__)
    ->helper('types', new PhpTypes())
    ->eachApi(
        'controller',
        static fn (TemplateContext $view): string => 'generated/' . $view->api?->name . '_controller.php',
        Ownership::Generated,
    )
    ->eachOperation(
        'logic',
        static fn (TemplateContext $view): string => 'generated/' . $view->api?->name . '_'
            . $view->operation?->action . '.php',
        Ownership::User,
    );
PHP);

    file_put_contents($directory . '/controller.php', <<<'PHP'
<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Generation\TemplateContext;

/** @var TemplateContext $view */
$name = ucfirst((string) $view->api?->name) . 'Controller';
$methods = '';
foreach ($view->api?->operations ?? [] as $operation) {
    $methods .= "    public function {$operation->action}(): array\n    {\n        return [];\n    }\n\n";
}

echo "<?php\n\ndeclare(strict_types=1);\n\nfinal class {$name}\n{\n{$methods}}\n";
PHP);

    file_put_contents($directory . '/logic.php', <<<'PHP'
<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Generation\TemplateContext;
use Lumnd\PlatoApiContract\Php\PhpTypes;

/** @var TemplateContext $view */
$types = $view->helpers->get('types');
if (!$types instanceof PhpTypes) {
    throw new LogicException('The types helper is missing.');
}
$api = ucfirst((string) $view->api?->name);
$action = ucfirst((string) $view->operation?->action);
$field = $view->operation?->requestFields[0] ?? null;
$type = $field === null ? 'mixed' : $types->field($field);

echo "<?php\n\ndeclare(strict_types=1);\n\n/** First request field: {$type}. */\nfinal class {$api}{$action}\n{\n}\n";
PHP);

    return $directory;
}

it('loads a framework from one platform definition and templates, without a custom adapter class', function () {
    $platform = template_pack_directory('cli');
    $output = generate_output_directory('pack-output');

    $result = run_generate_cli(generate_arguments(
        'ping',
        $output,
        '--platform=' . $platform . '/platform.php',
    ));

    expect($result['code'])->toBe(ExitCode::SUCCESS)
        ->and($result['stderr'])->toBe('')
        ->and($result['stdout'])->toContain('adapter plain-php')
        ->and(generated_files($output))->toBe([
            'api/manifest.json',
            'docs/api/openapi.json',
            'generated/ping_controller.php',
            'generated/ping_index.php',
        ])
        ->and((string) file_get_contents($output . '/generated/ping_controller.php'))
        ->toContain('final class PingController')
        ->and((string) file_get_contents($output . '/generated/ping_index.php'))
        ->toContain('First request field: string.');

    remove_directory($output);
    remove_directory($platform);
});

it('reports a missing platform definition as configuration, not an internal error', function () {
    $output = generate_output_directory('pack-missing');
    $result = run_generate_cli(generate_arguments(
        'ping',
        $output,
        '--platform=' . $output . '/missing.php',
    ));

    expect($result['code'])->toBe(ExitCode::GENERATION_CONFLICT)
        ->and($result['stderr'])->toContain('Platform definition file does not exist')
        ->and(generated_files($output))->toBe([]);

    remove_directory($output);
});
