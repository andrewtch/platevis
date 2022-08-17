<?php

if (!isset($_FILES['file']) || $_FILES['file']['error']) {
    print '';
    die();
}

// upscale the image to get better quality
const MULTIPLIER = 5;

$data = [];

$file = fopen($_FILES['file']['tmp_name'], 'r');

$width = $height = 0;

$filamentProfile = $slicingProfile = $printerProfile = 'unknown';

$layer = 0;

$currentObjectName = '';
$capturing = false;

$captureTarget = null;

// parse the file line by line
while ($row = fgets($file)) {
    $row = trim($row);

    if (strpos($row, '; object:{') === 0) {
        $objectData = json_decode(substr($row, 9), true);
        $data[$objectData['id']] = $objectData;
    }

    if (strpos($row, '; bed_shape = ') === 0) {
        $bedShape = substr($row, 14);
        $bedShape = explode(',', $bedShape);

        foreach($bedShape as $shape) {
            [$x, $y] = explode('x', trim($shape));
            $width = max($width, $x);
            $height = max($height, $y);
        }
    }

    if (strpos($row, '; filament_settings_id = ') === 0) {
        $filamentProfile = substr($row, 25);

        $filamentProfile = trim($filamentProfile, ' "');
    }

    if (strpos($row, '; print_settings_id = ') === 0) {
        $slicingProfile = substr($row, 22);

        $slicingProfile = trim($slicingProfile, ' "');
    }

    if (strpos($row, '; printer_settings_id = ') === 0) {
        $printerProfile = substr($row, 24);

        $printerProfile = trim($printerProfile, ' "');
    }

    if (strpos($row, ';LAYER_CHANGE') === 0) {
        $layer ++;
    }

    if ($layer > 1) {
        continue;
    }

    if (strpos($row, '; INIT printing object ') === 0) {
        $currentObjectName = substr($row, 23);
    }

    if (
        strpos($row, ';TYPE:Internal perimeter') === 0 ||
        strpos($row, ';TYPE:External perimeter') === 0 ||
        strpos($row, ';TYPE:Solid infill') === 0
    ) {
        $capturing = true;
        if ($row == ';TYPE:Internal perimeter') {
            $captureTarget = 'internal';
        }
        if ($row == ';TYPE:External perimeter') {
            $captureTarget = 'external';
        }
        if ($row == ';TYPE:Solid infill') {
            $captureTarget = 'infill';
        }
    } elseif ($captureTarget) {
        if (
            strpos($row, ';TYPE:') === 0 ||
            strpos($row, ';WIPE') === 0
        ) {
            $capturing = false;
            $captureTarget = null;
        }
    }

    if ($capturing && strpos($row, 'G1 ') === 0) {
        $x = $y = 0;
        $chunks = explode(' ', $row);
        $extrude = false;

        foreach ($chunks as $chunk) {
            if ($chunk[0] == 'X') {
                $x = floatval(substr($chunk, 1));
            }
            if ($chunk[0] == 'Y') {
                $y = floatval(substr($chunk, 1));
            }
            if ($chunk[0] == 'E' && $chunk[1] != '-') {
                $extrude = true;
            }
        }

        if ($x && $y && $extrude) {
            $data[$currentObjectName]['points'][$captureTarget][] = [$x, $y];
        }

    }
}

// render this to image
$im = new Imagick();
$im->newImage($width * MULTIPLIER, $height * MULTIPLIER, new ImagickPixel('white'));

$draw = new ImagickDraw();

// prepare and draw border
$draw->setStrokeAntialias(true);
$draw->setTextAntialias(true);

$draw->setStrokeWidth(0.3 * MULTIPLIER );
$draw->setStrokeColor(new ImagickPixel('black'));
$draw->setStrokeDashArray([1 * MULTIPLIER, 1 * MULTIPLIER]);

$draw->setFillColor(new ImagickPixel('transparent'));
$draw->rectangle(MULTIPLIER, MULTIPLIER, $width * MULTIPLIER - MULTIPLIER, $height * MULTIPLIER - MULTIPLIER);

// draw layers and objects
foreach (['boundary', 'parts', 'text_wrappers', 'labels'] as $layer) {
    foreach ($data as $id => $object) {
        switch ($layer) {
            case 'boundary':
                // if there is a stroke + fill then the bounding box has a border too so there is no need
                // to draw it separately
                $draw->setFillColor(new ImagickPixel('#00000011'));
                $draw->setStrokeColor(new ImagickPixel('#00000066'));
                $draw->setStrokeWidth(0.3 * MULTIPLIER );
                $draw->setStrokeDashArray([1 * MULTIPLIER, 1 * MULTIPLIER]);

                $draw->rectangle(
                    ($object['boundingbox_center'][0] - $object['boundingbox_size'][0] / 2) * MULTIPLIER,
                    ($height - $object['boundingbox_center'][1] - $object['boundingbox_size'][1] / 2) * MULTIPLIER,
                    ($object['boundingbox_center'][0] + $object['boundingbox_size'][0] / 2) * MULTIPLIER,
                    ($height - $object['boundingbox_center'][1] + $object['boundingbox_size'][1] / 2) * MULTIPLIER,
                );
                break;
            case 'parts':
                $draw->setStrokeDashArray([null]);
                foreach (['infill', 'internal', 'external'] as $lineType) {
                    if (isset($object['points'][$lineType]) && $object['points'][$lineType]) {
                        switch ($lineType) {
                            case 'external':
                                $draw->setStrokeColor(new ImagickPixel('#e87838'));
                                $draw->setStrokeWidth(1 * MULTIPLIER);
                                break;
                            case 'internal':
                                $draw->setStrokeColor(new ImagickPixel('#911106'));
                                $draw->setStrokeWidth(1 * MULTIPLIER);
                                break;
                            case 'infill':
                                $draw->setStrokeColor(new ImagickPixel('#47734c'));
                                $draw->setStrokeWidth(0.3 * MULTIPLIER);
                                break;
                        }

                        $points = $object['points'][$lineType];

                        for ($i = 1; $i < count($points); $i++) {
                            $draw->line(
                                ($points[$i - 1][0]) * MULTIPLIER,
                                ($height - $points[$i - 1][1]) * MULTIPLIER,
                                ($points[$i][0]) * MULTIPLIER,
                                ($height - $points[$i][1]) * MULTIPLIER
                            );
                        }
                    }
                }
                break;
            case 'text_wrappers':
            case 'labels':
                $draw->setTextAlignment(Imagick::ALIGN_CENTER);
                $draw->setFontSize(5 * MULTIPLIER);
                $draw->setFontStyle(Imagick::STYLE_OBLIQUE);
                $draw->setStrokeColor(new ImagickPixel('transparent'));

                $textCenterX = $object['boundingbox_center'][0] * MULTIPLIER;
                $textCenterY = ($height - ($object['boundingbox_center'][1] - $object['boundingbox_size'][1] / 2 + $object['boundingbox_size'][1] * 0.3)) * MULTIPLIER;

                $textDimensions = $im->queryFontMetrics(
                    $draw,
                    $object['name']
                );

                if ($layer == 'text_wrappers') {
                    $draw->setFillColor(new ImagickPixel('#555555ee'));

                    $draw->roundRectangle(
                        $textCenterX - ($textDimensions['textWidth'] / 2) - 3 * MULTIPLIER,
                        $textCenterY - ($textDimensions['textHeight'] / 2) - 3 * MULTIPLIER,
                        $textCenterX + ($textDimensions['textWidth'] / 2) + 3 * MULTIPLIER,
                        $textCenterY + ($textDimensions['textHeight'] / 2) + 3 * MULTIPLIER,
                        3 * MULTIPLIER,
                        3 * MULTIPLIER
                    );
                }

                if ($layer == 'labels') {
                    $draw->setFillColor(new ImagickPixel('white'));

                    $draw->annotation(
                        $textCenterX,
                        $textCenterY + $textDimensions['textHeight'] / 2,
                        $object['name']);
                }
                break;
        }
    }
}

// final - file info
$draw->setFillColor(new ImagickPixel('black'));
$draw->setStrokeColor(new ImagickPixel('transparent'));
$draw->setTextAlignment(Imagick::ALIGN_LEFT);
$draw->setFontSize(4 * MULTIPLIER);

$draw->annotation(
    4 * MULTIPLIER,
    6 * MULTIPLIER,
    implode('; ', [
        $_FILES['file']['name'],
        $filamentProfile,
        $slicingProfile,
        $printerProfile,
        $width.'x'.$height
    ])
);

$im->drawImage($draw);

$im->setImageFormat('jpeg');

header('Content-Type: text/plain');
echo base64_encode($im->getImage());
