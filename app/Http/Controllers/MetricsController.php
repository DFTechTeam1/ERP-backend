<?php

namespace App\Http\Controllers;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;

class MetricsController extends Controller
{
    public function __construct(private readonly CollectorRegistry $registry) {}

    /**
     * Render the collected metrics in Prometheus text exposition format.
     */
    public function __invoke(): Response
    {
        $renderer = new RenderTextFormat;

        return response(
            $renderer->render($this->registry->getMetricFamilySamples()),
            Response::HTTP_OK,
            ['Content-Type' => RenderTextFormat::MIME_TYPE],
        );
    }
}
