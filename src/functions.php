<?php

/**
 * use status_code get Exception class 
 *
 * @param integer $status_code
 * @param string $error
 * @param integer $code
 * @return veryfi\errors\VerifyClientError|Exception
 */
function error_map($status_code, $error = "", $code)
{
    $maps = [
        404 => \veryfi\errors\BadRequest::class,
        401 => \veryfi\errors\UnauthorizedAccessToken::class,
        405 => \veryfi\errors\UnexpectedHTPPMethod::class,
        409 => \veryfi\errors\AccessLimitReached::class,
        500 => \veryfi\errors\InternalError::class,
    ]; 
    $className = $maps[$status_code] ?? Exception::class;
    $class = new ReflectionClass($className);
    $instance = $class->newInstanceArgs([$error, $code]);
    return $instance;
}