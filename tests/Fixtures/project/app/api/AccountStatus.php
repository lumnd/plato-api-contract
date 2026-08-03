<?php

declare(strict_types=1);

namespace Fixture\api;

enum AccountStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
