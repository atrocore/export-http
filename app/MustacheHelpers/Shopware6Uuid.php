<?php
/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore UG (haftungsbeschränkt).
 *
 * This Software is the property of AtroCore UG (haftungsbeschränkt) and is
 * protected by copyright law - it is NOT Freeware and can be used only in one
 * project under a proprietary license, which is delivered along with this program.
 * If not, see <https://atropim.com/eula> or <https://atrodam.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
 */

declare(strict_types=1);

namespace ExportHttp\MustacheHelpers;

use Espo\Core\Injectable;

class Shopware6Uuid extends Injectable
{
    public const VALID_PATTERN = '^[0-9a-f]{32}$';

    public function __invoke(string $id, \Mustache_LambdaHelper $helper = null)
    {
        if (!empty($helper)) {
            $id = $helper->render($id);
        }

        $hex = self::fromStringToHex($id);
        $timeHi = self::applyVersion(mb_substr($hex, 12, 4), 4);
        $clockSeqHi = self::applyVariant(hexdec(mb_substr($hex, 16, 2)));

        return sprintf(
            '%08s%04s%04s%02s%02s%012s',
            // time low
            mb_substr($hex, 0, 8),
            // time mid
            mb_substr($hex, 8, 4),
            // time high and version
            str_pad(dechex($timeHi), 4, '0', \STR_PAD_LEFT),
            // clk_seq_hi_res
            str_pad(dechex($clockSeqHi), 2, '0', \STR_PAD_LEFT),
            // clock_seq_low
            mb_substr($hex, 18, 2),
            // node
            mb_substr($hex, 20, 12)
        );
    }

    public static function randomHex(): string
    {
        $hex = bin2hex(random_bytes(16));
        $timeHi = self::applyVersion(mb_substr($hex, 12, 4), 4);
        $clockSeqHi = self::applyVariant(hexdec(mb_substr($hex, 16, 2)));

        return sprintf(
            '%08s%04s%04s%02s%02s%012s',
            // time low
            mb_substr($hex, 0, 8),
            // time mid
            mb_substr($hex, 8, 4),
            // time high and version
            str_pad(dechex($timeHi), 4, '0', \STR_PAD_LEFT),
            // clk_seq_hi_res
            str_pad(dechex($clockSeqHi), 2, '0', \STR_PAD_LEFT),
            // clock_seq_low
            mb_substr($hex, 18, 2),
            // node
            mb_substr($hex, 20, 12)
        );
    }

    public static function randomBytes(): string
    {
        return hex2bin(self::randomHex());
    }

    public static function fromBytesToHex(string $bytes): string
    {
        if (mb_strlen($bytes, '8bit') !== 16) {
            throw new \Exception(sprintf('UUID has a invalid length. 16 bytes expected, %s given. Hexadecimal reprensentation: %s', mb_strlen($bytes, '8bit'), bin2hex($bytes)));
        }
        $uuid = bin2hex($bytes);

        if (!self::isValid($uuid)) {
            throw new \Exception("Value is not a valid UUID: $uuid");
        }

        return $uuid;
    }

    public static function fromBytesToHexList(array $bytesList): array
    {
        $converted = [];
        foreach ($bytesList as $key => $bytes) {
            $converted[$key] = self::fromBytesToHex($bytes);
        }

        return $converted;
    }

    public static function fromHexToBytesList(array $uuids): array
    {
        $converted = [];
        foreach ($uuids as $key => $uuid) {
            $converted[$key] = self::fromHexToBytes($uuid);
        }

        return $converted;
    }

    public static function fromHexToBytes(string $uuid): string
    {
        if ($bin = @hex2bin($uuid)) {
            return $bin;
        }

        throw new \Exception("Value is not a valid UUID: $uuid");
    }

    /**
     * Generates a md5 binary, to hash the string and returns a UUID in hex
     */
    public static function fromStringToHex(string $string): string
    {
        return self::fromBytesToHex(md5($string, true));
    }

    public static function isValid(string $id): bool
    {
        if (!preg_match('/' . self::VALID_PATTERN . '/', $id)) {
            return false;
        }

        return true;
    }

    private static function applyVersion(string $timeHi, int $version): int
    {
        $timeHi = hexdec($timeHi) & 0x0fff;
        $timeHi &= ~0xf000;
        $timeHi |= $version << 12;

        return $timeHi;
    }

    private static function applyVariant(int $clockSeqHi): int
    {
        // Set the variant to RFC 4122
        $clockSeqHi &= 0x3f;
        $clockSeqHi &= ~0xc0;
        $clockSeqHi |= 0x80;

        return $clockSeqHi;
    }
}
