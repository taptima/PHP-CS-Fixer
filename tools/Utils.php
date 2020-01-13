<?php

declare(strict_types=1);

final class Utils
{
    public static function arrayToString(array $array = null)
    {
        if ($array === null) {
            return;
        }

        $string = '[ ';

        if (array_values($array) === $array) {
            $string .= implode(', ', array_map([self::class, 'valueToString'], $array));
        } else {
            $string .= implode(', ', array_map(function ($value, $key) {
                return '\'' . $key . '\' => ' . self::valueToString($value);
            }, $array, array_keys($array)));
        }

        $string .= ' ]';

        return $string;
    }

    private static function valueToString($value = null)
    {
        if (is_string($value)) {
            return sprintf('\'%s\'', $value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return self::arrayToString($value);
        }

        if ($value === null) {
            return '~';
        }

        return $value;
    }
}
