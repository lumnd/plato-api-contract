<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Generation\TemplateRenderer;
use Lumnd\PlatoApiContract\Platform\Plato\View\ControllerView;

/**
 * The generated PlatoPHP controller class.
 *
 * Copy this file into your own template directory and pass it with --templates to change the layout.
 * Everything that decides behaviour is already resolved in the view; this file only writes it out.
 *
 * @var ControllerView $view
 * @var TemplateRenderer $templates
 */

$useBlock = '';
foreach ($view->imports as $import) {
    $useBlock .= 'use ' . $import . ";\n";
}

$methods = [];
foreach ($view->actions as $action) {
    $methods[] = $templates->render('action', ['view' => $action]);
}
$methodsCode = implode("\n", $methods);

echo <<<PHP
<?php

declare(strict_types=1);

namespace {$view->namespace};

{$useBlock}
{$view->marker}
final class {$view->class}
{
    /** @var array<string, array{methods: list<string>, auth: string}> */
    public static array \$actions = {$view->actionsCode};

{$methodsCode}}

PHP;
