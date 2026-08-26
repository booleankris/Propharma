<?php

namespace App\Services;

class DotMatrixPrinter
{
    public const ESC = "\x1B";

    public const CRLF = "\r\n";

    public const FF = "\x0C";

    public const WIDTH = 80;

    /**
     * Reset printer + set compact 1/8" line spacing.
     *
     * @param  bool  $draft  true = high-speed draft (lebih cepat), false = LQ quality.
     */
    public static function init(bool $draft = true): string
    {
        $pageLength = (int) env('DOTMATRIX_PAGE_LENGTH', 44);

        return self::ESC . '@'
            . self::ESC . '0'                          // 1/8" line spacing
            . self::ESC . 'C' . chr($pageLength)       // Set page length (44 lines * 1/8 = 5.5 inches)
            . self::ESC . 'x' . ($draft ? "\x00" : "\x01"); // draft / LQ
    }

    /**
     * Wrap text in bold (ESC E ... ESC F).
     */
    public static function bold(string $text): string
    {
        return self::ESC . 'E' . $text . self::ESC . 'F';
    }

    /**
     * Clean + convert UTF-8 text to a single-byte charset the printer understands.
     */
    public static function encode(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', (string) $text);

        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E]/', ' ', $text) ?? '';
        }

        return $converted;
    }

    /**
     * Send raw ESC/P bytes to a network printer on port 9100 (JetDirect).
     */
    public static function send(string $data, string $ip, int $port = 9100, int $timeout = 5): void
    {
        $errno = 0;
        $errstr = '';

        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            throw new \RuntimeException("Tidak bisa terhubung ke printer {$ip}:{$port} ({$errstr})");
        }

        stream_set_timeout($socket, $timeout);
        fwrite($socket, $data);
        fclose($socket);
    }
}
