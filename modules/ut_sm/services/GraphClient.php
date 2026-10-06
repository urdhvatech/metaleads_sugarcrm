<?php
/**
 * This file is part of the "Meta Leads" package.
 *
 * @package Meta Leads
 * @author Urdhva Tech <contact@urdhva-tech.com>
 * @link https://www.urdhva-tech.com
 * @copyright Urdhva Tech
 * @license As specified in the License Agreement supplied with this package.
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

/**
 * Facebook Graph API HTTP client for the Meta Lead Ads integration.
 *
 * Handles GET/POST requests, list pagination, and normalized success/error payloads.
 * Access tokens are sent via POST body when using POST (not in query strings).
 */
class UTSMGraphClient
{
    const API_VERSION = 'v26.0';
    const BASE_URL = 'https://graph.facebook.com/';

    /**
     * @param string $endpoint Path without leading slash, or absolute graph URL
     * @param array $params Query/body params
     * @param string $method GET|POST
     * @return array{ok:bool,error?:string,error_code?:int,data?:array,http_code?:int}
     */
    public function request($endpoint, array $params = array(), $method = 'GET')
    {
        $method = strtoupper($method);
        $url = $this->buildUrl($endpoint);

        if ($method === 'GET' && !empty($params)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
            $params = array();
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || !empty($curlErr)) {
            return array(
                'ok' => false,
                'error' => $curlErr ?: 'cURL request failed',
                'http_code' => $httpCode,
            );
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            // oauth/access_token sometimes returns query-string format
            $parsed = array();
            parse_str($response, $parsed);
            if (!empty($parsed['access_token'])) {
                return array('ok' => true, 'data' => $parsed, 'http_code' => $httpCode);
            }
            return array(
                'ok' => false,
                'error' => 'Invalid Graph API response',
                'http_code' => $httpCode,
            );
        }

        if ($httpCode >= 400 || !empty($data['error']['message'])) {
            $errorCode = isset($data['error']['code']) ? (int) $data['error']['code'] : 0;
            $msg = !empty($data['error']['message']) ? $data['error']['message'] : 'Graph API request failed';
            return array(
                'ok' => false,
                'error' => $msg,
                'error_code' => $errorCode,
                'http_code' => $httpCode,
                'data' => $data,
            );
        }

        return array('ok' => true, 'data' => $data, 'http_code' => $httpCode);
    }

    /**
     * @param string $endpoint
     * @param array $params
     * @return array
     */
    public function get($endpoint, array $params = array())
    {
        return $this->request($endpoint, $params, 'GET');
    }

    /**
     * @param string $endpoint
     * @param array $params
     * @return array
     */
    public function post($endpoint, array $params = array())
    {
        return $this->request($endpoint, $params, 'POST');
    }

    /**
     * Follow pagination for list endpoints that return data + paging.next.
     *
     * @param string $endpoint
     * @param array $params
     * @param int $maxPages
     * @return array{ok:bool,error?:string,error_code?:int,items?:array}
     */
    public function getAll($endpoint, array $params = array(), $maxPages = 20)
    {
        $items = array();
        $page = 0;
        $nextUrl = null;
        $firstParams = $params;

        while ($page < $maxPages) {
            if ($nextUrl !== null) {
                $res = $this->requestAbsolute($nextUrl);
            } else {
                $res = $this->get($endpoint, $firstParams);
            }

            if (!$res['ok']) {
                return $res;
            }

            $data = isset($res['data']['data']) && is_array($res['data']['data']) ? $res['data']['data'] : array();
            foreach ($data as $row) {
                $items[] = $row;
            }

            $nextUrl = isset($res['data']['paging']['next']) ? $res['data']['paging']['next'] : null;
            if (empty($nextUrl)) {
                break;
            }
            $page++;
        }

        return array('ok' => true, 'items' => $items);
    }

    /**
     * @param string $absoluteUrl
     * @return array
     */
    protected function requestAbsolute($absoluteUrl)
    {
        $ch = curl_init($absoluteUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || !empty($curlErr)) {
            return array('ok' => false, 'error' => $curlErr ?: 'cURL request failed', 'http_code' => $httpCode);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return array('ok' => false, 'error' => 'Invalid Graph API response', 'http_code' => $httpCode);
        }

        if ($httpCode >= 400 || !empty($data['error']['message'])) {
            return array(
                'ok' => false,
                'error' => !empty($data['error']['message']) ? $data['error']['message'] : 'Graph API request failed',
                'error_code' => isset($data['error']['code']) ? (int) $data['error']['code'] : 0,
                'http_code' => $httpCode,
            );
        }

        return array('ok' => true, 'data' => $data, 'http_code' => $httpCode);
    }

    /**
     * @param string $endpoint
     * @return string
     */
    protected function buildUrl($endpoint)
    {
        if (strpos($endpoint, 'https://') === 0 || strpos($endpoint, 'http://') === 0) {
            return $endpoint;
        }
        return self::BASE_URL . self::API_VERSION . '/' . ltrim($endpoint, '/');
    }
}
