<?php

namespace App\Services;

class ChartService {
    /**
     * This function is to create a series of the BAR chart
     *
     * @param string $name
     * @param array $data
     * @return array
     */
    public function buildBarSeries(string $name, array $data): array
    {
        return [
            [ 'name' => 'Length of Service', 'data' => $data ]
        ];
    }

    /**
     * This function is to create Options that we used in the frontend for BAR Chart
     *
     * @param array $xaxisCategories
     * @return array
     */
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

    /**
     * This function is to create Options that we used in the frontend for STACKED BAR Chart
     *
     * @return array
     */
    public function buildStackedBarOptions(): array
    {
        return [
            'chart' => [
                'type' => 'bar',
                'height' => 50,
                'stacked' => true,
                'theme' => 'dark',
                'toolbar' => [
                    'show' => false
                ]
            ],
            'plotOptions' => [
                'bar' => [
                    'horizontal' => true,
                    'dataLabels' => [
                        'total' => [
                            'enabled' => true,
                            'offsetX' => 0,
                            'style' => [
                                'fontSize' => '13px'
                            ]
                        ]
                    ]
                ]
            ],
            'stroke' => [
                'width' => 1,
                'colors' => ['#fff'],
                'dashArray' => 0
            ],
            'legend' => [
                'show' => false,
            ],
            'xaxis' => [
                'labels' => [
                    'show' => false,
                ],
                'axisborder' => [
                    'show' => false,
                ],
                'axisTicks' => [
                    'show' => false,
                ],
                'crosshairs' => [
                    'show' => false,
                ],
            ],
            'yaxis' => [
                'labels' => [
                    'show' => false,
                ],
                'axisborder' => [
                    'show' => false,
                ],
                'axisTicks' => [
                    'show' => false,
                ],
                'crosshairs' => [
                    'show' => false,
                ],
            ],
            'tooltip' => [
                'enabled' => true,
                'style' => [
                    'backgroundColor' => '#000',
                    'fontSize' => '12px'
                ],
                'theme' => 'dark',
            ],
        ];
    }
}
