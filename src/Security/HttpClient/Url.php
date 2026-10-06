<?php

/*
 * Your installation or use of this SugarCRM file is subject to the applicable
 * terms available at
 * http://support.sugarcrm.com/Resources/Master_Subscription_Agreements/.
 * If you do not agree to all of the applicable terms or do not have the
 * authority to bind the entity as an authorized representative, then do not
 * install or use this SugarCRM file.
 *
 * Copyright (C) SugarCRM Inc. All rights reserved.
 */

declare(strict_types=1);

namespace Sugarcrm\Sugarcrm\Security\HttpClient;

final class Url
{
    /**
     * Resolves a relative URL according to RFC 2396 section 5.2
     * @param string $url
     * @param string $base
     * @return string
     */
    public static function resolve(string $url, string $base): string
    {
        if ($base === '') {
            return $url;
        }
        if ($url === '') {
            return $base;
        }
        // already absolute url
        if (preg_match('~^[a-z]+:~i', $url)) {
            return $url;
        }
        $base = parse_url($base);
        if (str_starts_with($url, '#')) {
            $base['fragment'] = substr($url, 1);
            return self::build($base);
        }
        unset($base['fragment']);
        unset($base['query']);
        if (str_starts_with($url, '//')) {
            return self::build([
                'scheme' => $base['scheme'],
                'path' => substr($url, 2),
            ]);
        }
        if (str_starts_with($url, '/')) {
            $base['path'] = $url;
        } else {
            $path = explode('/', $base['path']);
            $url_path = explode('/', $url);
            array_pop($path);
            $end = array_pop($url_path);
            foreach ($url_path as $segment) {
                if ($segment == '.') {
                    continue;
                }
                if ($segment === '..' && $path && $path[safeCount($path) - 1] !== '..') {
                    array_pop($path);
                } else {
                    $path[] = $segment;
                }
            }
            if ($end === '.') {
                $path[] = '';
            } else {
                if ($end === '..' && $path && $path[safeCount($path) - 1] !== '..') {
                    $path[safeCount($path) - 1] = '';
                } else {
                    $path[] = $end;
                }
            }
            $base['path'] = implode('/', $path);
        }
        return self::build($base);
    }

    /**
     * Builds URL from the provided parts and replaces hostname with the IP address if one has been provided
     * @param array $parts
     * @param string|null $ip
     * @return string URL
     */
    public static function build(array $parts, ?string $ip = null): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';

        if ($ip === null) {
            $host = $parts['host'] ?? '';
        } else {
            $host = $ip;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
    }
}
