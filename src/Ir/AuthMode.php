<?php

declare(strict_types=1);

namespace Lumnd\PlatoApiContract\Ir;

/**
 * What an operation asks of the caller's identity.
 *
 * The contract states the requirement and the generated controller hands it to the router, which
 * decides before dispatch. Nothing beyond "is somebody signed in" belongs here: which identities
 * may do what is the application's own question, and the contract has no vocabulary for it.
 */
enum AuthMode: string
{
    /** Authentication is not required for the operation. */
    case None = 'none';

    /** Authentication may provide an identity; the operation runs either way. */
    case Optional = 'optional';

    /** Authentication must produce an identity or refuse the request. */
    case Required = 'required';

    /** Whether the framework refuses the request itself when nobody is signed in. */
    public function requiresIdentity(): bool
    {
        return $this === self::Required;
    }
}
