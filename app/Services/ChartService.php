<?php

namespace App\Services;

class ChartService {
    public function buildBarSeries(string $name, array $data): array
    {
        return [
            [ 'name' => 'Length of Service', 'data' => $data ]
        ];
    }

    public function buildBarOptions(array $xaxisCategories): array
    {
        return [
            'chart' => [
                'height' => 100,
                'width' => '100%',
                'type' => 'bar',
                'toolbar' => [
                    'show' => false
                ],
            ],
            'plotOptions' => [
                'bar' => [
                    'borderRadius' => 8,
                    'dataLabels' => [
                        'position' => 'top',
                    ],
                ],
            ],
            'dataLabels' => [
                'enabled' => true,
            ],
            'xaxis' => [
                'categories' => $xaxisCategories,
                'position' => "bottom",
                "axisBorder" => [
                    "show" => false,
                ],
                "axisTicks" => [
                    "show" => false,
                ],
                "crosshairs" => [
                    "fill" => [
                        'type' => "gradient",
                        "gradient" => [
                            "colorFrom" => "D8E3F0",
                            "colorTo" => "#BED1E6",
                            "stops" => [0,100],
                            "opacityFrom" => 0.4,
                            'opacityTo' => 0.5,
                        ],
                    ],
                ],
                "labels" => [
                    "show" => true,
                    "style" => ["#fff", "#fff", "#fff", "#fff"],
                ],
            ],
            "tooltip" => [
                "enabled" => true,
                "style" => [
                    "backgroundColor" => "#000",
                    "fontSize" => "12px",
                ],
                "theme" => "dark"
            ],
        ];
    }
}
