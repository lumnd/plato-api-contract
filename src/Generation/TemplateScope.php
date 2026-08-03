<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Generation;

/** How often one artifact template is rendered. */
enum TemplateScope: string
{
    case Collection = 'collection';
    case Api = 'api';
    case Operation = 'operation';
}
