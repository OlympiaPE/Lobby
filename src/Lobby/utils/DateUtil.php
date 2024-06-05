<?php

namespace Lobby\utils;

use DateTime;

class DateUtil
{
    /**
     * @param int $time
     * @param bool $short
     * @param string $separator
     * @return string
     */
    public static function toString(int $time, bool $short = false, string $separator = ", "): string
    {
        $remainingTime = $time - time();
        $year = intval(abs($remainingTime / 31536000));
        $remainingTime = $remainingTime - ($year * 31536000);
        $month = intval(abs($remainingTime / 2635200));
        $remainingTime = $remainingTime - ($month * 2635200);
        $weeks = intval(abs($remainingTime / 604800));
        $remainingTime = $remainingTime - ($weeks * 604800);
        $days = intval(abs($remainingTime / 86400));
        $remainingTime = $remainingTime - ($days * 86400);
        $hours = intval(abs($remainingTime / 3600));
        $remainingTime = $remainingTime - ($hours * 3600);
        $minutes = intval(abs($remainingTime / 60));
        $seconds = intval(abs($remainingTime % 60));

        $data = [
            "year" => ["time" => $year, "format" => !$short ? " année(s)" : "A"],
            "month" => ["time" => $month, "format" => !$short ? " mois" : "M"],
            "weeks" => ["time" => $weeks, "format" => !$short ? " semaine(s)" : "S"],
            "days" => ["time" => $days, "format" => !$short ? " jour(s)" : "j"],
            "hours" => ["time" => $hours, "format" => !$short ? " heure(s)" : "h"],
            "minutes" => ["time" => $minutes, "format" => !$short ? " minute(s)" : "m"],
            "seconds" => ["time" => $seconds, "format" => !$short ? " seconde(s)" : "s"],
        ];

        $content = [];
        foreach ($data as $key => $datum) {
            $time = $datum["time"];
            $format = $datum["format"];
            if ($time !== 0) $content[] = $time . $format;
        }

        return implode($separator, $content);
    }

    /**
     * @param string $content
     * @return int
     */
    public static function toInt(string $content): int
    {
        return match (substr($content, -1)) {
            "a" => ((intval($content) * 31536000)),
            "M" => ((intval($content) * 2635200)),
            "S" => ((intval($content) * 604800)),
            "j" => ((intval($content) * 86400)),
            "h" => ((intval($content) * 3600)),
            "m" => ((intval($content) * 60)),
            default => (intval($content)),
        };
    }

    /**
     * @return string
     */
    public static function currentDate(): string
    {
        return (new DateTime())->format('Y-m-d H:i:s');
    }
}
