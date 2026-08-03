<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Exception\TemplateException;
use Lumnd\PlatoApiContract\Generation\GenerationContext;
use Lumnd\PlatoApiContract\Generation\PhpTemplateRenderer;
use Lumnd\PlatoApiContract\Generation\TemplateApi;
use Lumnd\PlatoApiContract\Generation\TemplateLocator;
use Lumnd\PlatoApiContract\Ir\ContractCollection;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoConfig;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoControllerGenerator;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoPlatformAdapter;
use Lumnd\PlatoApiContract\Platform\Plato\PlatoTemplates;

/**
 * An empty directory a test can drop template overrides into.
 */
function template_workspace(string $name): string
{
    $path = sys_get_temp_dir() . '/plato-api-templates-' . $name . '-' . getmypid();
    if (is_dir($path)) {
        foreach (glob($path . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($path);
    }
    mkdir($path, 0777, true);

    return $path;
}

/**
 * @return array<string, string> path to contents
 */
function template_artifacts(?string $override): array
{
    $adapter = new PlatoPlatformAdapter(new PlatoConfig(
        'App\\control',
        'App\\logic',
        templateDirectory: $override,
    ));

    $files = [];
    foreach ($adapter->generate(
        fixture_contracts('ping'),
        new GenerationContext('/virtual/project'),
    ) as $artifact) {
        $files[$artifact->path] = $artifact->contents;
    }

    return $files;
}

it('lets a project override one template and keeps the built-ins for everything else', function () {
    $directory = template_workspace('override');
    file_put_contents($directory . '/controller.php', <<<'PHP'
        <?php

        /** @var \Lumnd\PlatoApiContract\Platform\Plato\View\ControllerView $view */
        echo "<?php\n\n// project layout for {$view->class}\n";
        foreach ($view->actions as $action) {
            echo "// {$action->httpMethod} {$action->path} -> {$action->name}()\n";
        }
        PHP);

    $builtIn = template_artifacts(null);
    $overridden = template_artifacts($directory);

    expect(array_keys($overridden))->toBe(array_keys($builtIn))
        ->and($overridden['app/control/ctl_ping.php'])->toBe(
            "<?php\n\n// project layout for ctl_ping\n// GET /ping/index -> index()\n",
        );

    unset($builtIn['app/control/ctl_ping.php'], $overridden['app/control/ctl_ping.php']);
    expect($overridden)->toBe($builtIn);

    unlink($directory . '/controller.php');
    rmdir($directory);
});

it('keeps output paths out of template control', function () {
    $directory = template_workspace('paths');
    // A template can only produce contents. Where the contents land stays adapter configuration.
    file_put_contents($directory . '/context.php', "<?php\n\necho \"<?php\\n\";\n");

    expect(array_keys(template_artifacts($directory)))->toBe(array_keys(template_artifacts(null)));

    unlink($directory . '/context.php');
    rmdir($directory);
});

it('names the searched directories when a template is missing', function () {
    $directory = template_workspace('missing');
    $locator = new TemplateLocator([$directory]);
    $searched = implode(', ', $locator->directories());

    expect(static fn (): string => $locator->locate('controller'))
        ->toThrow(TemplateException::class, 'Template "controller" was not found. Searched: ' . $searched . '.');

    rmdir($directory);
});

it('refuses template names that could leave the template directory', function () {
    $locator = PlatoTemplates::locator();

    expect(static fn (): string => $locator->locate('../../../etc/passwd'))
        ->toThrow(TemplateException::class, 'Invalid template name')
        ->and(static fn (): string => $locator->locate('Controller'))
        ->toThrow(TemplateException::class);
});

it('refuses a template directory that does not exist', function () {
    expect(static fn (): TemplateLocator => PlatoTemplates::locator('/nowhere/plato-templates'))
        ->toThrow(TemplateException::class, 'Template directory does not exist: /nowhere/plato-templates');
});

it('reports the template that failed and leaves no output buffer behind', function () {
    $directory = template_workspace('failure');
    file_put_contents(
        $directory . '/controller.php',
        "<?php\n\necho 'half a file';\nthrow new RuntimeException('the override is broken');\n",
    );

    $renderer = PlatoTemplates::renderer($directory);
    $level = ob_get_level();
    $message = '';
    try {
        $renderer->render('controller', ['view' => null]);
    } catch (TemplateException $exception) {
        $message = $exception->getMessage();
    }

    expect($message)->toContain('The template "controller"')
        ->and($message)->toContain($directory . '/controller.php')
        ->and($message)->toContain('the override is broken')
        ->and(ob_get_level())->toBe($level);

    unlink($directory . '/controller.php');
    rmdir($directory);
});

it('changes the template fingerprint only when a template really differs', function () {
    $directory = template_workspace('fingerprint');
    $builtIn = PlatoTemplates::renderer()->fingerprint();

    // Copying a built-in template without editing it must not invalidate generated artifacts.
    copy(PlatoTemplates::directory() . '/controller.php', $directory . '/controller.php');
    expect(PlatoTemplates::renderer($directory)->fingerprint())->toBe($builtIn);

    file_put_contents($directory . '/controller.php', "<?php\n\necho \"<?php\\n\";\n");
    $edited = PlatoTemplates::renderer($directory)->fingerprint();

    expect($edited)->not->toBe($builtIn)
        ->and(PlatoTemplates::renderer($directory)->fingerprint())->toBe($edited)
        ->and((new PlatoPlatformAdapter(new PlatoConfig(templateDirectory: $directory)))->templateFingerprint())
        ->toBe($edited);

    unlink($directory . '/controller.php');
    rmdir($directory);
});

it('ships one template per generated file and renders each of them', function () {
    $locator = PlatoTemplates::locator();
    foreach (PlatoTemplates::NAMES as $name) {
        expect(is_file($locator->locate($name)))->toBeTrue();
    }

    $rendered = template_artifacts(null);
    expect(array_keys($rendered))->toBe([
        'app/control/ctl_ping.php',
        'app/logic/ping_index.php',
    ]);
});

it('hands templates a versioned read-only view, never the IR', function () {
    $view = (new PlatoControllerGenerator())->view(
        ping_contract(),
        new PlatoConfig('App\\control', 'App\\logic'),
    );

    expect($view->templateApiVersion())->toBe(TemplateApi::VERSION)
        ->and($view->class)->toBe('ctl_ping')
        ->and($view->actions[0]->templateApiVersion())->toBe(TemplateApi::VERSION)
        ->and($view->actions[0]->inputs)->toBe(['message' => "req::get('message')"])
        ->and($view->actions[0]->responseExpression)->toContain('resp::response');
});

it('renders a sub template through the renderer it is given', function () {
    $directory = template_workspace('compose');
    file_put_contents($directory . '/action.php', <<<'PHP'
        <?php

        /** @var \Lumnd\PlatoApiContract\Platform\Plato\View\ActionView $view */
        echo "    // {$view->name}\n";
        PHP);

    $contents = template_artifacts($directory)['app/control/ctl_ping.php'];

    expect($contents)->toContain('    // index')
        ->and($contents)->toContain('final class ctl_ping')
        ->and($contents)->not->toContain('$validator');

    unlink($directory . '/action.php');
    rmdir($directory);
});

it('exposes the renderer that a host would inject into its own adapter', function () {
    $renderer = new PhpTemplateRenderer(PlatoTemplates::locator());

    expect($renderer->locator()->directories())->toBe([PlatoTemplates::directory()])
        ->and($renderer->fingerprint())->toBe(PlatoTemplates::renderer()->fingerprint());
});
