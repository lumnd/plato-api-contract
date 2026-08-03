<?php

declare(strict_types=1);

use Lumnd\PlatoApiContract\Platform\Plato\View\ActionView;

/**
 * One controller action: read the declared inputs, validate them, hand them to Logic, answer.
 *
 * @var ActionView $view
 */

$inputLines = [];
foreach ($view->inputs as $name => $expression) {
    $inputLines[] = "            '" . $name . "' => " . $expression . ',';
}
$inputCode = $inputLines === [] ? '[]' : "[\n" . implode("\n", $inputLines) . "\n        ]";

echo <<<PHP
    public function {$view->name}(): {$view->returnType}
    {
        \$input = {$inputCode};
        \$validator = {$view->validatorExpression};

        if (\$validator->fails()) {
            {$view->failureStatement}
        }

        \$request = {$view->requestExpression};
        \$response = {$view->logicExpression};

        return {$view->responseExpression};
    }

PHP;
