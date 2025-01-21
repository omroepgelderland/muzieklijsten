<?php

declare(strict_types=1);

namespace gldstdlib\safe;

/**
 * Perform a regular expression match
 *
 * @link https://php.net/manual/en/function.preg-match.php
 *
 * @param $pattern <p>
 * The pattern to search for, as a string.
 * </p>
 * @param $subject <p>
 * The input string.
 * </p>
 * @param string[] &$matches [optional] <p>
 * If <i>matches</i> is provided, then it is filled with
 * the results of search. $matches[0] will contain the
 * text that matched the full pattern, $matches[1]
 * will have the text that matched the first captured parenthesized
 * subpattern, and so on.
 * </p>
 * @param-out string[] $matches
 * @param 0|256|512|768 $flags [optional] <p>
 * <i>flags</i> can be the following flag:
 * <b>PREG_OFFSET_CAPTURE</b>
 * <blockquote>
 * If this flag is passed, for every occurring match the appendant string
 * offset will also be returned. Note that this changes the value of
 * <i>matches</i> into an array where every element is an
 * array consisting of the matched string at offset 0
 * and its string offset into <i>subject</i> at offset 1.
 * <pre>
 * <code>
 * preg_match('/(foo)(bar)(baz)/', 'foobarbaz', $matches, PREG_OFFSET_CAPTURE);
 * print_r($matches);
 * </code>
 * </pre>
 * The above example will output:
 * <pre>
 * Array
 * (
 *     [0] => Array
 *         (
 *             [0] => foobarbaz
 *             [1] => 0
 *         )
 *
 *     [1] => Array
 *         (
 *             [0] => foo
 *             [1] => 0
 *         )
 *
 *     [2] => Array
 *         (
 *             [0] => bar
 *             [1] => 3
 *         )
 *
 *     [3] => Array
 *         (
 *             [0] => baz
 *             [1] => 6
 *         )
 *
 * )
 * </pre>
 * </blockquote>
 * <b>PREG_UNMATCHED_AS_NULL</b>
 * <blockquote>
 * If this flag is passed, unmatched subpatterns are reported as NULL;
 * otherwise they are reported as an empty string.
 * <pre>
 * <code>
 * preg_match('/(a)(b)*(c)/', 'ac', $matches);
 * var_dump($matches);
 * preg_match('/(a)(b)*(c)/', 'ac', $matches, PREG_UNMATCHED_AS_NULL);
 * var_dump($matches);
 * </code>
 * </pre>
 * The above example will output:
 * <pre>
 * array(4) {
 *   [0]=>
 *   string(2) "ac"
 *   [1]=>
 *   string(1) "a"
 *   [2]=>
 *   string(0) ""
 *   [3]=>
 *   string(1) "c"
 * }
 * array(4) {
 *   [0]=>
 *   string(2) "ac"
 *   [1]=>
 *   string(1) "a"
 *   [2]=>
 *   NULL
 *   [3]=>
 *   string(1) "c"
 * }
 * </pre>
 * </blockquote>
 * @param $offset [optional] <p>
 * Normally, the search starts from the beginning of the subject string.
 * The optional parameter <i>offset</i> can be used to
 * specify the alternate place from which to start the search (in bytes).
 * </p>
 * <p>
 * Using <i>offset</i> is not equivalent to passing
 * substr($subject, $offset) to
 * <b>preg_match</b> in place of the subject string,
 * because <i>pattern</i> can contain assertions such as
 * ^, $ or
 * (?&lt;=x). Compare:
 * <pre>
 * <code>
 * $subject = "abcdef";
 * $pattern = '/^def/';
 * preg_match($pattern, $subject, $matches, PREG_OFFSET_CAPTURE, 3);
 * print_r($matches);
 * </code>
 * </pre>
 * The above example will output:</p>
 * <pre>
 * Array
 * (
 * )
 * </pre>
 * <p>
 * while this example
 * </p>
 * <pre>
 * <code>
 * $subject = "abcdef";
 * $pattern = '/^def/';
 * preg_match($pattern, substr($subject,3), $matches, PREG_OFFSET_CAPTURE);
 * print_r($matches);
 * </code>
 * </pre>
 * <p>
 * will produce
 * </p>
 * <pre>
 * Array
 * (
 *     [0] => Array
 *         (
 *             [0] => def
 *             [1] => 0
 *         )
 * )
 * </pre>
 * Alternatively, to avoid using substr(), use the \G assertion rather
 * than the ^ anchor, or the A modifier instead, both of which work with
 * the offset parameter.
 * </p>
 *
 * @return 0|1 <b>preg_match</b> returns 1 if the <i>pattern</i>
 * matches given <i>subject</i>, 0 if it does not.
 *
 * @throws PcreException if an error occurred.
 */
function preg_match(string $pattern, string $subject, &$matches = [], int $flags = 0, int $offset = 0): int
{
    \error_clear_last();
    // @phpstan-ignore paramOut.type
    $safeResult = \preg_match($pattern, $subject, $matches, $flags, $offset);
    if ($safeResult === false) {
        throw PcreException::createFromPhpError();
    }
    preg_match($pattern, $subject, $m);
    return $safeResult;
}

/**
 * Searches subject for matches to
 * pattern and replaces them with
 * replacement.
 *
 * @param string[]|string $pattern The pattern to search for. It can be either a string or an array with
 * strings.
 * Several PCRE modifiers
 * are also available.
 * @param string[]|string $replacement The string or an array with strings to replace. If this parameter is a
 * string and the pattern parameter is an array,
 * all patterns will be replaced by that string. If both
 * pattern and replacement
 * parameters are arrays, each pattern will be
 * replaced by the replacement counterpart. If
 * there are fewer elements in the replacement
 * array than in the pattern array, any extra
 * patterns will be replaced by an empty string.
 *
 * replacement may contain references of the form
 * \\n or
 * $n, with the latter form
 * being the preferred one. Every such reference will be replaced by the text
 * captured by the n'th parenthesized pattern.
 * n can be from 0 to 99, and
 * \\0 or $0 refers to the text matched
 * by the whole pattern. Opening parentheses are counted from left to right
 * (starting from 1) to obtain the number of the capturing subpattern.
 * To use backslash in replacement, it must be doubled
 * ("\\\\" PHP string).
 *
 * When working with a replacement pattern where a backreference is
 * immediately followed by another number (i.e.: placing a literal number
 * immediately after a matched pattern), you cannot use the familiar
 * \\1 notation for your backreference.
 * \\11, for example, would confuse
 * preg_replace since it does not know whether you
 * want the \\1 backreference followed by a literal
 * 1, or the \\11 backreference
 * followed by nothing.  In this case the solution is to use
 * ${1}1.  This creates an isolated
 * $1 backreference, leaving the 1
 * as a literal.
 *
 * When using the deprecated e modifier, this function escapes
 * some characters (namely ', ",
 * \ and NULL) in the strings that replace the
 * backreferences. This is done to ensure that no syntax errors arise
 * from backreference usage with either single or double quotes (e.g.
 * 'strlen(\'$1\')+strlen("$2")'). Make sure you are
 * aware of PHP's string
 * syntax to know exactly how the interpreted string will look.
 * @param string[]|string $subject The string or an array with strings to search and replace.
 * If subject is an array, then the search and
 * replace is performed on every entry of subject,
 * and the return value is an array as well.
 * @param $limit The maximum possible replacements for each pattern in each
 * subject string. Defaults to
 * -1 (no limit).
 * @param $count If specified, this variable will be filled with the number of
 * replacements done.
 *
 * @return ($subject is string ? string : string[]) preg_replace returns an array if the
 * subject parameter is an array, or a string
 * otherwise.
 * If matches are found, the new subject will
 * be returned, otherwise subject will be
 * returned unchanged.
 *
 * @throws PcreException
 */
function preg_replace(  // @phpstan-ignore parameterByRef.unusedType
    array|string $pattern,
    array|string $replacement,
    array|string $subject,
    int $limit = -1,
    ?int &$count = null,
): array|string {
    \error_clear_last();
    $result = \preg_replace($pattern, $replacement, $subject, $limit, $count);
    if (\preg_last_error() !== \PREG_NO_ERROR || $result === null) {
        throw PcreException::createFromPhpError();
    }
    return $result;
}
