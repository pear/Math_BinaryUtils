<?php

// {{{ license

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Markus Nix <mnix@docuverse.de>                              |
// +----------------------------------------------------------------------+
//

// }}}


// {{{ defines
define('MATH_BINARYUTILS_BIG_ENDIAN',    0x0000);
define('MATH_BINARYUTILS_LITTLE_ENDIAN', 0x0001);
// }}}


/**
 * Class with static helpers for easy handling of bit and byte stuff.
 *
 * @author  Markus Nix <mnix@docuverse.de>
 * @access  public
 * @package Math
 * @version $Id: BinaryUtils.php,v 1.2 2004/09/08 11:56:11 docuverse_de Exp $
 * @static
 */

class Math_BinaryUtils
{
    // {{{ add
    /**
     * Binary add.
     *
     * @param  int    $num1
     * @param  int    $num2
     * @access public
     * @static
     */
    function add($num1, $num2)
    {
        $carry = $num1 & $num2;

        do {
            $carry = $carry << 1;
            $num1  = $num1 ^ $num2;
            $sum   = $num1 ^ $carry;
            $num2  = $carry;
            $carry = $num1 & $num2;
        } while ($carry != 0);

        return $sum;
    }
    // }}}

    // {{{ subtract
    /**
     * Binary subtract.
     *
     * @param  int    $num1
     * @param  int    $num2
     * @access public
     * @static
     */
    function subtract($num1, $num2)
    {
        // compute two's compliment
        $num2 =~ $num2;
        $num2 =  Math_BinaryUtils::add($num2, 1);
        $diff =  Math_BinaryUtils::add($num1, $num2);

        return $diff;
    }
    // }}}

    // {{{ binToDec
    /**
     * Bin to dec conversion.
     *
     * @param  string  $binstring
     * @return int
     * @access public
     * @static
     */
    function binToDec($binstring)
    {
        $decvalue = 0;

        for ($i = 0; $i < strlen($binstring); $i++) {
            $decvalue += ((int)substr($binstring, strlen($binstring) - $i - 1, 1)) * pow(2, $i);
        }

        return Math_BinaryUtils::_castAsInt($decvalue);
    }
    // }}}

    // {{{ decToBin
    /**
     * Dec to bin conversion.
     *
     * @param  int    $number
     * @access public
     * @static
     */
    function decToBin($number)
    {
        while ( $number >= 256 ) {
            $bytes[] = (($number / 256) - (floor($number / 256))) * 256;
            $number  = floor($number / 256);
        }

        $bytes[]   = $number;
        $binstring = '';

        for ($i = 0; $i < count( $bytes ); $i++) {
            $binstring = (($i == count($bytes) - 1)? decbin($bytes["$i"]) : str_pad(decbin($bytes["$i"]), 8, '0', STR_PAD_LEFT)) . $binstring;
        }

        return $binstring;
    }
    // }}}

    // {{{ floatToBin
    /**
     * Converts a single-precision floating point number
     * to a 6 byte binary string.
     *
     * @param  float  $num  the float to convert
     * @return string       the binary string representing the float
     * @access public
     * @static
     */
    function floatToBin($num)
    {
        // Save the sign bit.
        $sign = ($num < 0)? 0x8000 : 0x0000;

        // Now treat the number as positive...
        if ($num < 0)
            $num = -$num;

        // Get the exponent and limit to 15 bits.
        $exponent = (1 + (int)floor(log10($num))) & 0x7FFF;

        // Convert the number into a fraction.
        $num /= pow(10, $exponent);

        // Now convert the fraction to a 31bit int.
        // We don't use the full 32bits, because the -ve numbers
        // stuff us up -- this results in better than single
        // precision floats, but not as good as double precision.
        $fraction = (int)floor($num * 0x7FFFFFFF);

        // Pack the number into a 6 byte binary string
        return Math_BinaryUtils::_decToBin_bytes($sign | $exponent, 2) . Math_BinaryUtils::_decToBin_bytes($fraction, 4);
    }
    // }}}

    // {{{ binToFloat
    /**
     * Converts a 6 byte binary string to a single-precision
     * floating point number.
     *
     * @param  string  $data  the binary string to convert
     * @return the floating point number
     * @access public
     * @static
     */
    function binToFloat(&$data)
    {
        // Extract the sign bit and exponent.
        $exponent  = Math_BinaryUtils::_binToDec_length(substr($data, 0, 2), 2);
        $sign      = (($exponent & 0x8000) == 0)? 1 : -1;
        $exponent &= 0x7FFF;

        // Extract the fractional part.
        $fraction = Math_BinaryUtils::_binToDec_length(substr($data, 2, 4), 4);

        // Return the reconstructed float.
        return $sign * pow(10, $exponent) * $fraction / 0x7FFFFFFF;
    }
    // }}}

    // {{{ binToString
    /**
     * Bin to string conversion. For example,
     * return 'hi' for input of '0110100001101001'.
     *
     * @param  string  $binstring
     * @return string
     * @access public
     * @static
     */
    function binToString($binstring)
    {
        $string = '';
        $binstringreversed = strrev($binstring);

        for ($i = 0; $i < strlen($binstringreversed); $i += 8) {
            $string = chr(Math_BinaryUtils::binToDec(strrev(substr($binstringreversed, $i, 8)))) . $string;
        }

        return $string;
    }
    // }}}

    // {{{ decbin_pad
    /**
     * Converts Decimal -> Binary, and left-pads it with $padvalue 0's.
     *
     * @param  int    $inputdec
     * @param  int    $padvalue
     * @return string
     * @access public
     * @static
     */
    function decbin_pad($inputdec, $padvalue)
    {
        return str_pad(decbin($inputdec), $padvalue, "0", STR_PAD_LEFT);
    }
    // }}}

    // {{{ binToDec_fraction
    /**
     * Bin to dec conversion with fractions.
     *
     * @return int
     * @access public
     * @static
     */
    function binToDec_fraction($inputfraction)
    {
        $binRep = Math_BinaryUtils::decbin_pad($inputfraction, 8);
        $old    = 0;

        for ($i = 8; $i--; $i > 0) {
            $old = ($old + $binRep[$i]) / 2;
        }

        return $old;
    }
    // }}}

    // {{{ getNativeOrder
    /**
     * Retrieve native byte order of this OS.
     * Little Endian: Intel's 80x86 processors and their clones,
     * Big Endian: SPARC, Motorola's 68K, and the PowerPC families.
     *
     * @access  public
     * @return  int
     * @static
     */
    function getNativeOrder()
    {
        switch (pack('d', 1)) {
        case "\77\360\0\0\0\0\0\0":
            return MATH_BINARYUTILS_BIG_ENDIAN;

        case "\0\0\0\0\0\0\360\77":
            return MATH_BINARYUTILS_LITTLE_ENDIAN;
        }
    }
    // }}}

    // {{{ littleEndianToString
    /**
     * Little Endian to String conversion.
     *
     * @param  int    $number
     * @param  int    $minbytes
     * @param  bool   $synchsafe
     * @return string
     * @access public
     * @static
     */
    function littleEndianToString($number, $minbytes = 1, $synchsafe = false)
    {
        while ($number > 0) {
            if ($synchsafe) {
                $intstring = $intstring . chr($number & 127);
                $number >>= 7;
            } else {
                $intstring = $intstring . chr($number & 255);
                $number >>= 8;
            }
        }

        return $intstring;
    }
    // }}}

    // {{{ bigEndianToString
    /**
     * Big Endian to String conversion.
     *
     * @param  int    $number
     * @param  int    $minbytes
     * @param  bool   $synchsafe
     * @param  bool   $signed
     * @return string
     * @access public
     * @static
     */
    function bigEndianToString($number, $minbytes = 1, $synchsafe = false, $signed = false)
    {
        if ( $number < 0 ) {
            return false;
        }

        $maskbyte  = (($synchsafe || $signed)? 0x7F : 0xFF);
        $intstring = '';

        if ($signed) {
            if ( $minbytes > 4 ) {
                return false;
            }

            $number = $number & (0x80 << (8 * ($minbytes - 1)));
        }

        while ($number != 0) {
            $quotient  = ($number / ($maskbyte + 1));
            $intstring = chr(ceil( ($quotient - floor($quotient)) * $maskbyte)) . $intstring;
            $number    = floor($quotient);
        }

        return str_pad($intstring, $minbytes, chr(0), STR_PAD_LEFT);
    }
    // }}}

    // {{{ performPack
    /**
     * Perform pack.
     *
     * @param  int    $val
     * @param  int    $bytes
     * @return string
     * @access public
     * @static
     */
    function performPack($val, $bytes = 2)
    {
        for ($ret = '', $i = 0; $i < $bytes; $i++, $val = floor($val / 256)) {
            $ret .= chr($val % 256);
        }

        return $ret;
    }
    // }}}

    // {{{ performUnpack
    /**
     * Perform unpack.
     *
     * @param  string $val
     * @return int
     * @access public
     * @static
     */
    function performUnpack($val)
    {
        for ($len = strlen($val), $ret = 0, $i = 0; $i < $len; $i++) {
            $ret += (int)ord(substr($val, $i, 1)) * pow(2, 8 * $i);
        }

        return $ret;
    }
    // }}}

    // {{{ bytestringToGUID
    /**
     * Transform bytestring to GUID.
     *
     * @param  string $byte_str
     * @return string GUID string
     * @access public
     * @static
     */
    function bytestringToGUID($byte_str)
    {
        $guid_str  = strtoupper(str_pad(dechex(ord($byte_str{3} )), 2, '0', STR_PAD_LEFT));
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{2} )), 2, '0', STR_PAD_LEFT));
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{1} )), 2, '0', STR_PAD_LEFT));
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{0} )), 2, '0', STR_PAD_LEFT));
        $guid_str .= '-';
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{5} )), 2, '0', STR_PAD_LEFT));
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{4} )), 2, '0', STR_PAD_LEFT));
        $guid_str .= '-';
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{7} )), 2, '0', STR_PAD_LEFT));
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{6} )), 2, '0', STR_PAD_LEFT));
        $guid_str .= '-';
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{8} )), 2, '0', STR_PAD_LEFT));
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{9} )), 2, '0', STR_PAD_LEFT));
        $guid_str .= '-';
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{10})), 2, '0', STR_PAD_LEFT));
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{11})), 2, '0', STR_PAD_LEFT));
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{12})), 2, '0', STR_PAD_LEFT));
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{13})), 2, '0', STR_PAD_LEFT));
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{14})), 2, '0', STR_PAD_LEFT));
        $guid_str .= strtoupper(str_pad(dechex(ord($byte_str{15})), 2, '0', STR_PAD_LEFT));

        return $guid_str;
    }
    // }}}

    // {{{ GUIDToBytestring
    /**
     * Transform GUID to bytestring.
     *
     * @param  string $guid_str
     * @return string byte string
     * @access public
     * @static
     */
    function GUIDToBytestring($guid_str)
    {
        // Microsoft defines these 16-byte (128-bit) GUIDs in the strangest way:
        // first 4 bytes are in little-endian order
        // next 2 bytes are appended in little-endian order
        // next 2 bytes are appended in little-endian order
        // next 2 bytes are appended in big-endian order
        // next 6 bytes are appended in big-endian order

        // AaBbCcDd-EeFf-GgHh-IiJj-KkLlMmNnOoPp is stored as this 16-byte string:
        // $Dd $Cc $Bb $Aa $Ff $Ee $Hh $Gg $Ii $Jj $Kk $Ll $Mm $Nn $Oo $Pp

        $hexbytechar_str  = chr(hexdec(substr($guid_str,  6, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str,  4, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str,  2, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str,  0, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str, 11, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str,  9, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str, 16, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str, 14, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str, 19, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str, 21, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str, 24, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str, 26, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str, 28, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str, 30, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str, 32, 2)));
        $hexbytechar_str .= chr(hexdec(substr($guid_str, 34, 2)));

        return $hexbytechar_str;
    }
    // }}}

    // {{{ littleEndianToInt
    /**
     * Little Endian to int conversion.
     *
     * @param  string $byteword
     * @param  bool   $signed
     * @return int
     * @access public
     * @static
     */
    function littleEndianToInt($byteword, $signed = false)
    {
        return Math_BinaryUtils::bigEndianToInt(strrev($byteword), false, $signed);
    }
    // }}}

    // {{{ bigEndianToInt
    /**
     * Big Endian to int conversion.
     *
     * @param  string $byteword
     * @param  bool   $synchsafe
     * @param  bool   $signed
     * @return int
     * @access public
     * @static
     */
    function bigEndianToInt($byteword, $synchsafe = false, $signed = false)
    {
        $intvalue = 0;
        $bytewordlen = strlen($byteword);

        for ($i = 0; $i < $bytewordlen; $i++) {
            // disregard MSB, effectively 7-bit bytes
            if ($synchsafe) {
                $intvalue = $intvalue | (ord($byteword{$i}) & 0x7F) << (($bytewordlen - 1 - $i) * 7);
            } else {
                $intvalue += ord($byteword{$i}) * pow(256, ($bytewordlen - 1 - $i));
            }
        }

        if ($signed && !$synchsafe) {
            // synchsafe ints are not allowed to be signed
            switch ($bytewordlen) {
            case 1:

            case 2:

            case 3:

            case 4:
                $signmaskbit = 0x80 << (8 * ($bytewordlen - 1));

                if ($intvalue & $signmaskbit) {
                    $intvalue = 0 - ($intvalue & ($signmaskbit - 1));
                }

                break;

            default:
                break;
            }
        }

        return Math_BinaryUtils::_castAsInt($intvalue);
    }
    // }}}

    // {{{ littleEndianToBin
    /**
     * Little Endian to bin conversion.
     *
     * @param  string $byteword
     * @return string
     * @access public
     * @static
     * @note   untested
     */
    function littleEndianToBin($byteword)
    {
        return Math_BinaryUtils::bigEndianToBin(strrev($byteword));
    }
    // }}}

    // {{{ bigEndianToBin
    /**
     * Big Endian to bin conversion.
     *
     * @param  string $byteword
     * @return string
     * @access public
     * @static
     */
    function bigEndianToBin($byteword)
    {
        $binvalue    = '';
        $bytewordlen = strlen($byteword);

        for ($i = 0; $i < $bytewordlen; $i++) {
            $binvalue .= str_pad(decbin(ord($byteword{$i})), 8, '0', STR_PAD_LEFT);
        }

        return $binvalue;
    }
    // }}}

    // {{{ littleEndianToFloat
    /**
     * Little Endian to float conversion.
     *
     * @param  string $byteword
     * @return mixed  Either float or false on error
     * @access public
     * @static
     */
    function littleEndianToFloat($byteword)
    {
        return Math_BinaryUtils::bigEndianToFloat(strrev($byteword));
    }
    // }}}

    // {{{ bigEndianToFloat
    /**
     * Big Endian to float conversion.
     *
     * @param  string $byteword
     * @return mixed  Either float or false on error
     * @access public
     * @static
     */
    function bigEndianToFloat($byteword)
    {
        // ANSI/IEEE Standard 754-1985, Standard for Binary Floating Point Arithmetic
        // http://www.psc.edu/general/software/packages/ieee/ieee.html
        // http://www.scri.fsu.edu/~jac/MAD3401/Backgrnd/ieee.html

        $bitword = Math_BinaryUtils::bigEndianToBin($byteword);
        $signbit = $bitword{0};

        if (strlen($byteword) == 4) { // 32-bit DWORD
            $exponentbits = 8;
            $fractionbits = 23;
        } else if (strlen($byteword) == 8) { // 64-bit QWORD
            $exponentbits = 11;
            $fractionbits = 52;
        } else {
            return false;
        }

        $exponentstring = substr($bitword, 1, $exponentbits);
        $fractionstring = substr($bitword, 9, $fractionbits);

        $exponent = Math_BinaryUtils::binToDec($exponentstring);
        $fraction = Math_BinaryUtils::binToDec($fractionstring);

        if (($exponent == (pow(2, $exponentbits) - 1)) && ($fraction != 0)) {
            // Not a number
            $float_val = false;
        } else if (($exponent == (pow(2, $exponentbits) - 1)) && ($fraction == 0)) {
            if ($signbit == '1') {
                $float_val = '-infinity';
            } else {
                $float_val = '+infinity';
            }
        } else if (($exponent == 0) && ($fraction == 0)) {
            if ($signbit == '1') {
                $float_val = -0;
            } else {
                $float_val = 0;
            }

            $float_val = ($signbit? 0 : -0);
        } else if (($exponent == 0) && ($fraction != 0)) {
            // These are 'unnormalized' values
            $float_val = pow(2, (-1 * (pow(2, $exponentbits - 1) - 2))) * Math_BinaryUtils::decimalBinaryToFloat($fractionstring);

            if ($signbit == '1') {
                $float_val *= -1;
            }
        } else if ($exponent != 0) {
            $float_val = pow(2, ($exponent - (pow(2, $exponentbits - 1) - 1))) * (1 + Math_BinaryUtils::decimalBinaryToFloat($fractionstring));

            if ($signbit == '1') {
                $float_val *= -1;
            }
        }

        return (float)$float_val;
    }
    // }}}

    // {{{ floatToBinaryDecimal
    /**
     * Transform float value to binary decimal.
     *
     * @param  float  $float_val
     * @access public
     * @static
     */
    function floatToBinaryDecimal($float_val)
    {
        $maxbits        = 128; // to how many bits of precision should the calculations be taken?
        $intpart        = Math_BinaryUtils::_truncate($float_val);
        $floatpart      = abs($float_val - $intpart);
        $pointbitstring = '';

        while (($floatpart != 0) && (strlen($pointbitstring) < $maxbits)) {
            $floatpart      *= 2;
            $pointbitstring .= (string)Math_BinaryUtils::_truncate($floatpart);
            $floatpart      -= Math_BinaryUtils::_truncate($floatpart);
        }

        $binarypointnumber = decbin($intpart) . '.' . $pointbitstring;
        return $binarypointnumber;
    }
    // }}}

    // {{{ normalizeBinaryPoint
    /**
     * Normalize binary points.
     *
     * @param  string $binarypointnumber
     * @param  int    $maxbits
     * @return array
     * @access public
     * @static
     */
    function normalizeBinaryPoint($binarypointnumber, $maxbits = 52)
    {
        if (strpos($binarypointnumber, '.') === false) {
            $binarypointnumber = '0.' . $binarypointnumber;
        } else if ($binarypointnumber{0} == '.') {
            $binarypointnumber = '0' . $binarypointnumber;
        }

        $exponent = 0;

        while (($binarypointnumber{0} != '1') || (substr($binarypointnumber, 1, 1) != '.')) {
            if (substr($binarypointnumber, 1, 1) == '.') {
                $exponent--;
                $binarypointnumber = substr($binarypointnumber, 2, 1) . '.' . substr($binarypointnumber, 3);
            } else {
                $pointpos  = strpos($binarypointnumber, '.');
                $exponent += ($pointpos - 1);

                $binarypointnumber = str_replace('.', '', $binarypointnumber);
                $binarypointnumber = $binarypointnumber{0} . '.' . substr($binarypointnumber, 1);
            }
        }

        $binarypointnumber = str_pad(substr($binarypointnumber, 0, $maxbits + 2), $maxbits + 2, '0', STR_PAD_RIGHT);

        return array(
            'normalized' => $binarypointnumber,
            'exponent'   => (int)$exponent
        );
    }
    // }}}

    // {{{ decimalBinaryToFloat
    /**
     * Transform decimal binary to float.
     *
     * @param  string $binarynumerator
     * @return float
     * @access public
     * @static
     */
    function decimalBinaryToFloat($binarynumerator)
    {
        $numerator   = Math_BinaryUtils::binToDec($binarynumerator);
        $denominator = Math_BinaryUtils::binToDec(str_repeat('1', strlen($binarynumerator)));

        return ($numerator / $denominator);
    }
    // }}}

    // {{{ getHexBytes
    /**
     * Get hex bytes.
     *
     * @param  string $string
     * @return string
     * @access public
     * @static
     */
    function getHexBytes($string)
    {
        $ret_str = '';

        for ($i = 0; $i < strlen($string); $i++) {
            $ret_str .= str_pad(dechex(ord(substr($string, $i, 1))), 2, '0', STR_PAD_LEFT) . ' ';
        }

        return $ret_str;
    }
    // }}}

    // {{{ getTextBytes
    /**
     * Get text bytes.
     *
     * @param  string $string
     * @return string
     * @access public
     * @static
     */
    function getTextBytes($string)
    {
        $ret_str = '';

        for ($i = 0; $i < strlen($string); $i++ ) {
            if (ord(substr($string, $i, 1)) <= 31 ) {
                $ret_str .= '   ';
            } else {
                $ret_str .= ' ' . substr($string, $i, 1) . ' ';
            }
        }

        return $ret_str;
    }
    // }}}


    // private methods

    // {{{ _truncate
    /**
     * Tuncates a floating-point number at the decimal point
     * returns int (if possible, otherwise double)
     *
     * @param  float   $float_val
     * @return int
     * @access private
     * @static
     */
    function _truncate($float_val)
    {
        if ($float_val >= 1) {
            $truncatednumber = floor($float_val);
        } else if ($float_val <= -1) {
            $truncatednumber = ceil($float_val);
        } else {
            $truncatednumber = 0;
        }

        if ($truncatednumber <= pow(2, 30)) {
            $truncatednumber = (int)$truncatednumber;
        }

        return $truncatednumber;
    }
    // }}}

    // {{{ _castAsInt
    /**
     * Convert a float to type int, only if possible.
     *
     * @param  float   $float_val
     * @return int
     * @access private
     * @static
     */
    function _castAsInt($float_val)
    {
        if (Math_BinaryUtils::_truncate($float_val) == $float_val) {
            // it's not floating point
            if ($float_val <= pow(2, 30)) {
                // it's within int range
                $float_val = (int)$float_val;
            }
        }

        return $float_val;
    }
    // }}}

    // {{{ _decToBin_bytes
    /**
     * Converts an int to a binary string, low byte first.
     *
     * @param  int     $num    number to convert
     * @param  int     $bytes  minimum number of bytes to covert to
     * @return string  the binary string form of the number
     * @access private
     * @static
     */
    function _decToBin_bytes($num, $bytes)
    {
        $result = "";

        for ($i = 0; $i < $bytes; ++$i) {
            $result .= chr($num & 0xFF);
            $num = $num >> 8;
        }

        return $result;
    }
    // }}}

    // {{{ _binToDec_length
    /**
     * Converts a binary string to an int, low byte first.
     *
     * @param  string  $str   binary string to convert
     * @param  int     $len   length of the binary string to convert
     * @return int     the int version of the binary string
     * @access private
     * @static
     */
    function _binToDec_length(&$str, $len)
    {
        $shift  = 0;
        $result = 0;

        for ($i = 0; $i < $len; ++$i) {
            $result |= (@ord($str[$i]) << $shift);
            $shift  += 8;
        }

        return $result;
    }
    // }}}
}

?>
