<?php

namespace App\Exceptions\Auth;

use Exception;

/**
 * Thrown when a refresh token cannot be rotated: unknown, expired, or revoked
 * (including a detected replay). The service performs any family revocation
 * side-effect before throwing; callers should respond 401.
 */
class RefreshTokenInvalid extends Exception
{
    //
}
