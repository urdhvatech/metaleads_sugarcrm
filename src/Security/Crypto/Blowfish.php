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

namespace Sugarcrm\Sugarcrm\Security\Crypto;

use phpseclib3\Crypt\Blowfish as BlowfishImplementation;

/**
 * Blowfish encryption
 *
 * @internal
 */
class Blowfish
{
    /**
     * retrieves the system's private key; will build one if not found, but anything encrypted before is gone...
     *
     * @param string $type
     *
     * @return string key
     */
    public static function getKey($type)
    {
        $key = [];

        $type = str_rot13($type);

        $keyCache = "custom/blowfish/{$type}.php";

        // build cache dir if needed
        if (!file_exists('custom/blowfish')) {
            mkdir_recursive('custom/blowfish');
        }

        // get key from cache, or build if not exists
        if (file_exists($keyCache)) {
            include $keyCache;
        } else {
            // create a key
            $key[0] = create_guid();
            write_array_to_file('key', $key, $keyCache);
        }
        return $key[0];
    }

    /**
     * Uses blowfish to encrypt data and base 64 encodes it
     *
     * @param string $key key to base encoding off of
     * @param string $data string to be encrypted and encoded
     *
     * @return string
     */
    public static function encode($key, $data): string
    {
        $key = self::padKey($key);
        $implementation = self::getImplementation($key);

        if (!is_string($data)) {
            $data = (string) $data;
        }

        // To be backwards compatible with how mcrypt/Pear_BlowFish works, use zeropadding
        $data = $data . str_repeat(chr(0), 8 - ((strlen($data) % 8) ?: 8));

        return base64_encode($implementation->encrypt($data));
    }

    /**
     * Uses blowfish to decode data assumes data has been base64 encoded
     *
     * @param string $key key to base decoding off of
     * @param string $encoded base64 encoded blowfish encrypted data
     *
     * @return string
     */
    public static function decode($key, $encoded): string
    {
        if (!is_string($encoded)) {
            $encoded = '';
        }
        $decoded = base64_decode($encoded, true);
        if ($decoded === false || strlen($decoded) % 8 !== 0) {
            return '';
        }

        $key = self::padKey($key);
        $implementation = self::getImplementation($key);

        return rtrim($implementation->decrypt($decoded), chr(0));
    }

    /**
     * Get a Blowfish implementation with the given key and ECB mode, with padding disabled.
     * This is needed for backwards compatibility with old Blowfish implementations that used ECB mode and manual
     * zero padding.
     * The key should be padded to at least 16 bytes before calling this method.
     *
     * Note that ECB mode is not secure and should not be used for new implementations, but we need to use it here for
     * backwards compatibility.
     *
     * @param string $key
     * @return BlowfishImplementation
     */
    private static function getImplementation(string $key): BlowfishImplementation
    {
        $implementation = new BlowfishImplementation('ecb');
        $implementation->disablePadding();
        $implementation->setKey($key);

        return $implementation;
    }

    /**
     * Pad the key to at least 16 bytes by repeating it. This is needed for backwards compatibility with old Blowfish
     * implementations that used 16 byte keys and cycled over shorter keys.
     *
     * According to https://www.schneier.com/academic/archives/1994/09/description_of_a_new.html short keys should be
     * cycled over so keys A-AA-AAA are equivalent.
     *
     * @param string $key
     *
     * @return string
     */
    private static function padKey($key): string
    {
        if (!is_string($key) || $key === '') {
            $key = "\0";
        }

        $keyLen = strlen($key);

        return $keyLen < 16 ? str_repeat($key, ceil(16 / $keyLen)) : $key;
    }
}
