<?php

namespace App\Util;

use Exception;

class Strings
{
    /**
     * @param int $length
     * @return string
     * @throws Exception
     */
    public static function randomB36(int $length = 20): string
    {
        return base_convert(self::randomHex($length), 16, 36);
    }

    /**
     * @param int $length
     * @return string
     * @throws Exception
     */
    public static function randomHex(int $length = 20): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * @param string $pwd
     * @param string $salt
     * @return mixed
     */
    public static function keyDerivation(string $pwd, string $salt)
    {
        return hash_pbkdf2('sha256', $pwd, $salt, 10000, 256, true);
    }
}