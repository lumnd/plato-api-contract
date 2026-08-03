<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Platform\Plato\View\LogicView;

/**
 * The first Logic skeleton of one operation.
 *
 * This file is written once and never overwritten, so this template decides what a developer starts
 * from: adjust it to inject your own service base class, logging or transaction wrapper.
 *
 * @var LogicView $view
 */

$useBlock = '';
foreach ($view->imports as $import) {
    $useBlock .= 'use ' . $import . ";\n";
}

$doc = [];
if ($view->requestShape !== '') {
    $doc[] = '     * @param ' . $view->requestShape . ' $request';
}
if ($view->responseShape !== '') {
    $doc[] = '     * @return ' . $view->responseShape;
}
$docBlock = $doc === [] ? '' : "    /**\n" . implode("\n", $doc) . "\n     */\n";

echo <<<PHP
<?php

declare(strict_types=1);

namespace {$view->namespace};

{$useBlock}
final class {$view->class}
{
{$docBlock}    public static function handle(
        {$view->requestType} \$request,
        {$view->contextType} \$context,
    ): {$view->responseType} {
        throw new LogicException('Implement {$view->namespace}\\{$view->class}::handle().');
    }
}

PHP;
