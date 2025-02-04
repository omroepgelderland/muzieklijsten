<?php

declare(strict_types=1);

namespace gldstdlib\safe;

class LdapException extends SafeException
{
    public static function create(\LDAP\Connection $ldap): static
    {
        $error = \error_get_last();
        return new static(\ldap_error($ldap), \ldap_errno($ldap), $error['type'] ?? 1);
    }
}
