<?php

namespace Library;

/**
 *
 */
class Json
{
    /**
     * Encode
     *
     * @param $data
     * @param $flags
     * @return string|false
     * @throws \JsonException
     */
    public static function encode($data, $flags = JSON_PRETTY_PRINT): string|false
    {
        return json_encode($data, $flags | JSON_THROW_ON_ERROR);    // always throw on error
    }

    /**
     * Decode
     *
     * @param $data
     * @return bool|array|null
     * @throws \JsonException
     */
    public static function decode($data): null|bool|array
    {
        return json_decode($data, null, 512, JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
    }

    /**
     * Validate
     *
     * @param $data
     * @return bool
     */
    public static function validate($data): bool
    {
        return json_validate($data);
    }
}
