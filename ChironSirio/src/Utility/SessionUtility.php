<?php

namespace Chiron\Sirio\Utility;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionUtility
{
    public const ATTRIBUTE_NAME = 'sirio-stored-snippet';

    public const UPDATE_FLAG = 'sirio-stored-shouldUpdate';

    public static function injectSessionVars(array $snippet, SessionInterface $session): array
    {
        if (!$session->has(self::UPDATE_FLAG)) {
            return $snippet;
        }

        return $snippet;
    }
}
