<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Notification Service API',
    description: 'Bulk SMS and Email notifications API.'
)]
#[OA\Server(url: 'http://localhost:8081/api', description: 'Local')]
#[OA\Tag(name: 'Notifications')]
#[OA\Tag(name: 'Health')]
#[OA\Tag(name: 'Metrics')]
class ApiDocumentation {}
