<?php

declare(strict_types=1);

namespace gldstdlib\safe;

/**
 * Bind to LDAP directory
 *
 * @link https://php.net/manual/en/function.ldap-bind.php
 *
 * @param $ldap <p>
 * An LDAP link identifier, returned by <b>ldap_connect</b>.
 * </p>
 * @param $dn [optional]
 * @param $password [optional]
 *
 * @throws LdapException on failure.
 */
function ldap_bind(\LDAP\Connection $ldap, ?string $dn, ?string $password): void
{
    \error_clear_last();
    if ($password !== null) {
        $safeResult = \ldap_bind($ldap, $dn, $password);
    } elseif ($dn !== null) {
        $safeResult = \ldap_bind($ldap, $dn);
    } else {
        $safeResult = \ldap_bind($ldap);
    }
    if ($safeResult === false) {
        throw LdapException::create($ldap);
    }
}

/**
 * Get the current value for given option
 *
 * @link https://php.net/manual/en/function.ldap-get-option.php
 *
 * @param $ldap <p>
 * An LDAP link identifier, returned by <b>ldap_connect</b>.
 * </p>
 * @param $option <p>
 * The parameter <i>option</i> can be one of:
 * <tr valign="top">
 * <td>Option</td>
 * <td>Type</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_DEREF</b></td>
 * <td>integer</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_SIZELIMIT</b></td>
 * <td>integer</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_TIMELIMIT</b></td>
 * <td>integer</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_NETWORK_TIMEOUT</b></td>
 * <td>integer</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_PROTOCOL_VERSION</b></td>
 * <td>integer</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_ERROR_NUMBER</b></td>
 * <td>integer</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_REFERRALS</b></td>
 * <td>bool</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_RESTART</b></td>
 * <td>bool</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_HOST_NAME</b></td>
 * <td>string</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_ERROR_STRING</b></td>
 * <td>string</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_MATCHED_DN</b></td>
 * <td>string</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_SERVER_CONTROLS</b></td>
 * <td>array</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_CLIENT_CONTROLS</b></td>
 * <td>array</td>
 * </tr>
 * </p>
 * @param &$value <p>
 * This will be set to the option value.
 * </p>
 *
 * @throws LdapException on failure.
 */
function ldap_get_option(
    \LDAP\Connection $ldap,
    int $option,
    mixed &$value = null
): void {
    \error_clear_last();
    $safeResult = \ldap_get_option($ldap, $option, $value);
    if ($safeResult === false) {
        throw LdapException::create($ldap);
    }
}

/**
 * Set the value of the given option
 *
 * @link https://php.net/manual/en/function.ldap-set-option.php
 *
 * @param $ldap <p>
 * An LDAP link identifier, returned by <b>ldap_connect</b>.
 * </p>
 * @param $option <p>
 * The parameter <i>option</i> can be one of:
 * <tr valign="top">
 * <td>Option</td>
 * <td>Type</td>
 * <td>Available since</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_DEREF</b></td>
 * <td>integer</td>
 * <td></td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_SIZELIMIT</b></td>
 * <td>integer</td>
 * <td></td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_TIMELIMIT</b></td>
 * <td>integer</td>
 * <td></td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_NETWORK_TIMEOUT</b></td>
 * <td>integer</td>
 * <td>PHP 5.3.0</td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_PROTOCOL_VERSION</b></td>
 * <td>integer</td>
 * <td></td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_ERROR_NUMBER</b></td>
 * <td>integer</td>
 * <td></td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_REFERRALS</b></td>
 * <td>bool</td>
 * <td></td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_RESTART</b></td>
 * <td>bool</td>
 * <td></td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_HOST_NAME</b></td>
 * <td>string</td>
 * <td></td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_ERROR_STRING</b></td>
 * <td>string</td>
 * <td></td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_MATCHED_DN</b></td>
 * <td>string</td>
 * <td></td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_SERVER_CONTROLS</b></td>
 * <td>array</td>
 * <td></td>
 * </tr>
 * <tr valign="top">
 * <td><b>LDAP_OPT_CLIENT_CONTROLS</b></td>
 * <td>array</td>
 * <td></td>
 * </tr>
 * </p>
 * <p>
 * <b>LDAP_OPT_SERVER_CONTROLS</b> and
 * <b>LDAP_OPT_CLIENT_CONTROLS</b> require a list of
 * controls, this means that the value must be an array of controls. A
 * control consists of an oid identifying the control,
 * an optional value, and an optional flag for
 * criticality. In PHP a control is given by an
 * array containing an element with the key oid
 * and string value, and two optional elements. The optional
 * elements are key value with string value
 * and key iscritical with boolean value.
 * iscritical defaults to <b>FALSE</b>
 * if not supplied. See draft-ietf-ldapext-ldap-c-api-xx.txt
 * for details. See also the second example below.
 * </p>
 * @param array<mixed>|bool|int|string $value <p>
 * The new value for the specified <i>option</i>.
 * </p>
 *
 * @throws LdapException on failure.
 */
function ldap_set_option(
    ?\LDAP\Connection $ldap,
    int $option,
    mixed $value
): void {
    \error_clear_last();
    $safeResult = \ldap_set_option($ldap, $option, $value);
    if ($safeResult === false && isset($ldap)) {
        throw LdapException::create($ldap);
    } elseif ($safeResult === false) {
        throw LdapException::createFromPhpError();
    }
}

/**
 * Search LDAP tree
 *
 * @link https://php.net/manual/en/function.ldap-search.php
 *
 * @param $ldap <p>
 * An LDAP link identifier, returned by <b>ldap_connect</b>.
 * </p>
 * @param string|string[] $base <p>
 * The base DN for the directory.
 * </p>
 * @param string|string[] $filter <p>
 * The search filter can be simple or advanced, using boolean operators in
 * the format described in the LDAP documentation (see the Netscape Directory SDK for full
 * information on filters).
 * </p>
 * @param string[] $attributes <p>
 * An array of the required attributes, e.g. array("mail", "sn", "cn").
 * Note that the "dn" is always returned irrespective of which attributes
 * types are requested.
 * </p>
 * <p>
 * Using this parameter is much more efficient than the default action
 * (which is to return all attributes and their associated values).
 * The use of this parameter should therefore be considered good
 * practice.
 * </p>
 * @param $attributes_only <p>
 * Should be set to 1 if only attribute types are wanted. If set to 0
 * both attributes types and attribute values are fetched which is the
 * default behaviour.
 * </p>
 * @param $sizelimit [optional] <p>
 * Enables you to limit the count of entries fetched. Setting this to 0
 * means no limit.
 * </p>
 * <p>
 * This parameter can NOT override server-side preset sizelimit. You can
 * set it lower though.
 * </p>
 * <p>
 * Some directory server hosts will be configured to return no more than
 * a preset number of entries. If this occurs, the server will indicate
 * that it has only returned a partial results set. This also occurs if
 * you use this parameter to limit the count of fetched entries.
 * </p>
 * @param $timelimit [optional] <p>
 * Sets the number of seconds how long is spend on the search. Setting
 * this to 0 means no limit.
 * </p>
 * <p>
 * This parameter can NOT override server-side preset timelimit. You can
 * set it lower though.
 * </p>
 * @param $deref <p>
 * Specifies how aliases should be handled during the search. It can be
 * one of the following:
 * <b>LDAP_DEREF_NEVER</b> - (default) aliases are never
 * dereferenced.</p>
 * @param ?string[] $controls Array of LDAP Controls to send with the request.
 *
 * @return \LDAP\Result|\LDAP\Result[]
 *
 * @throws LdapException
 */
function ldap_search(
    \LDAP\Connection $ldap,
    array|string $base,
    array|string $filter,
    array $attributes = [],
    int $attributes_only = 0,
    int $sizelimit = -1,
    int $timelimit = -1,
    int $deref = 0,
    ?array $controls = null
): \LDAP\Result|array {
    \error_clear_last();
    $safeResult = \ldap_search(
        $ldap,
        $base,
        $filter,
        $attributes,
        $attributes_only,
        $sizelimit,
        $timelimit,
        $deref,
        $controls
    );
    if ($safeResult === false) {
        throw LdapException::create($ldap);
    }
    return $safeResult;
}

/**
 * Get all result entries
 *
 * @link https://php.net/manual/en/function.ldap-get-entries.php
 *
 * @param $ldap <p>
 * An LDAP link identifier, returned by <b>ldap_connect</b>.
 * </p>
 * @param $result
 *
 * @return array<mixed> a complete result information in a multi-dimensional array.
 * </p>
 * <p>
 * The structure of the array is as follows.
 * The attribute index is converted to lowercase. (Attributes are
 * case-insensitive for directory servers, but not when used as
 * array indices.)
 * <pre>
 * return_value["count"] = number of entries in the result
 * return_value[0] : refers to the details of first entry
 * return_value[i]["dn"] = DN of the ith entry in the result
 * return_value[i]["count"] = number of attributes in ith entry
 * return_value[i][j] = NAME of the jth attribute in the ith entry in the result
 * return_value[i]["attribute"]["count"] = number of values for
 * attribute in ith entry
 * return_value[i]["attribute"][j] = jth value of attribute in ith entry
 * </pre>
 *
 * @throws LdapException
 */
function ldap_get_entries(
    \LDAP\Connection $ldap,
    \LDAP\Result $result
): array {
    \error_clear_last();
    $safeResult = \ldap_get_entries($ldap, $result);
    if ($safeResult === false) {
        throw LdapException::create($ldap);
    }
    return $safeResult;
}
