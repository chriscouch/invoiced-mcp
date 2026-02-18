<?php

namespace App\EntryPoint\Controller;

use App\Core\Statsd\StatsdClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class OpsController extends AbstractController
{
    #[Route(path: '/_opcache_stats', name: 'opcache_stats', host: '%app.domain%', methods: ['GET'])]
    public function opcacheStats(Request $request, StatsdClient $statsd): Response
    {
        $stats = (array) opcache_get_status(false);

        // log to statsd
        foreach ($stats as $key => $value) {
            $this->logStat('opcache.'.$key, $value, $statsd);
        }

        if ('127.0.0.1' != $request->getClientIp()) {
            throw new NotFoundHttpException();
        }

        $result = json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return new JsonResponse($result, 200, [], true);
    }

    private function logStat(string $key, mixed $value, StatsdClient $statsd): void
    {
        if (is_array($value)) {
            foreach ($value as $subKey => $subValue) {
                $this->logStat($key.'.'.$subKey, $subValue, $statsd);
            }
        } else {
            if (!is_float($value)) {
                $value = (int) $value;
            }
            $statsd->gauge($key, $value);
        }
    }
}
