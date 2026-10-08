<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Metrics\BusinessMetricsCollector;
use App\Services\Metrics\MetricsRegistry;
use Illuminate\Http\Response;
use Prometheus\RenderTextFormat;
use Throwable;

class MetricsController extends Controller
{
    public function __construct(
        private MetricsRegistry $registry,
        private BusinessMetricsCollector $businessMetrics,
    ) {}


    public function __invoke(): Response
    {
        if (! config('prometheus.enabled', true)) {
            return response('metrics disabled', 404);
        }

        $this->businessMetrics->collect();

        try {
            $renderer = new RenderTextFormat();
            $metrics = $renderer->render($this->registry->registry()->getMetricFamilySamples());
        } catch (Throwable $e) {


            report($e);

            return response('metrics unavailable', 503);
        }

        return response($metrics, 200, [
            'Content-Type' => RenderTextFormat::MIME_TYPE,
        ]);
    }
}
