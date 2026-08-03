<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

/**
 * The version of the data templates receive.
 *
 * Templates are a public API of this package, the IR is not. A view model may gain fields within one
 * version; removing or renaming a field, or changing what an existing field means, raises this
 * number. A project template can assert on `$view->templateApiVersion()` to fail loudly instead of
 * rendering something subtly wrong after an upgrade.
 */
final class TemplateApi
{
    /**
     * 3: Removed the unused ActionView::$responseDataExpression; templates receive the complete
     *    responseExpression instead.
     *
     * 2: ActionView::$failureExpression became $failureStatement -- a whole statement, so that a
     *    project that registered an exception class throws from there instead of returning a
     *    response.
     */
    public const VERSION = 3;

    private function __construct()
    {
    }
}
