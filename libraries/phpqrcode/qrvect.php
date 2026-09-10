<?php
/*
 * PHP QR Code encoder
 * Vector output of code (SVG, EPS)
 */

class QRvect {
    public static function eps($frame, $filename = false, $pixelPerPoint = 4, $outerFrame = 4, $saveandprint = false, $back_color = 0xFFFFFF, $fore_color = 0x000000, $cmyk = false) {
        return false;
    }

    public static function svg($frame, $filename = false, $pixelPerPoint = 4, $outerFrame = 4, $saveandprint = false, $back_color = 0xFFFFFF, $fore_color = 0x000000) {
        $h = count($frame);
        $w = strlen($frame[0]);
        $imgW = $w + 2 * $outerFrame;
        $imgH = $h + 2 * $outerFrame;
        
        $output = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        $output .= '<svg version="1.1" baseProfile="full" width="' . ($imgW * $pixelPerPoint) . '" height="' . ($imgH * $pixelPerPoint) . '" viewBox="0 0 ' . $imgW . ' ' . $imgH . '" xmlns="http://www.w3.org/2000/svg">' . "\n";
        $output .= '<rect width="100%" height="100%" fill="#' . sprintf('%06X', $back_color) . '"/>' . "\n";
        
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ($frame[$y][$x] == '1') {
                    $output .= '<rect x="' . ($x + $outerFrame) . '" y="' . ($y + $outerFrame) . '" width="1" height="1" fill="#' . sprintf('%06X', $fore_color) . '"/>' . "\n";
                }
            }
        }
        $output .= '</svg>';
        
        if ($filename === false) {
            header("Content-Type: image/svg+xml");
            echo $output;
        } else {
            file_put_contents($filename, $output);
        }
        return true;
    }
}
