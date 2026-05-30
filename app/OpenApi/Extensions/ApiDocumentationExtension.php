<?php

namespace App\OpenApi\Extensions;

use App\Http\Resources\ArtistResource;
use App\Http\Resources\ReviewResource;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\ObjectType as InferObjectType;

class ApiDocumentationExtension extends OperationExtension
{
    /**
     * @var array<string, array{class-string, string}>
     */
    private const PAGINATED_OPERATIONS = [
        'GET:api/v1/artists' => [ArtistResource::class, 'Artists retrieved successfully'],
        'GET:api/v1/favorites' => [ArtistResource::class, 'Favorite artists retrieved successfully'],
        'GET:api/v1/artists/{artistId}/reviews' => [ReviewResource::class, 'Reviews retrieved successfully'],
    ];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $this->fixPaginatedResponse($operation);
        $this->addNotFoundResponse($operation);
        $this->addForbiddenResponseForAdmin($operation);
    }

    private function fixPaginatedResponse(Operation $operation): void
    {
        $key = strtoupper($operation->method).':'.$operation->path;

        if (! isset(self::PAGINATED_OPERATIONS[$key])) {
            return;
        }

        [$resourceClass, $message] = self::PAGINATED_OPERATIONS[$key];

        foreach ($operation->responses as $index => $response) {
            if (! $response instanceof Response || (int) $response->code !== 200) {
                continue;
            }

            $operation->responses[$index] = Response::make(200)
                ->setContent(
                    'application/json',
                    Schema::fromType($this->buildPaginatedEnvelope($resourceClass, $message)),
                );
        }
    }

    /**
     * @param  class-string  $resourceClass
     */
    private function buildPaginatedEnvelope(string $resourceClass, string $message): ObjectType
    {
        $itemType = $this->openApiTransformer->transform(new InferObjectType($resourceClass));

        $data = (new ArrayType)->setItems($itemType instanceof Type ? $itemType : new StringType);

        $links = (new ObjectType)
            ->addProperty('first', (new StringType)->nullable(true))
            ->addProperty('last', (new StringType)->nullable(true))
            ->addProperty('prev', (new StringType)->nullable(true))
            ->addProperty('next', (new StringType)->nullable(true));

        $meta = (new ObjectType)
            ->addProperty('current_page', new IntegerType)
            ->addProperty('from', (new IntegerType)->nullable(true))
            ->addProperty('last_page', new IntegerType)
            ->addProperty('path', new StringType)
            ->addProperty('per_page', new IntegerType)
            ->addProperty('to', (new IntegerType)->nullable(true))
            ->addProperty('total', new IntegerType);

        return (new ObjectType)
            ->addProperty('data', $data)
            ->addProperty('links', $links)
            ->addProperty('meta', $meta)
            ->addProperty('message', (new StringType)->const($message))
            ->setRequired(['data', 'links', 'meta', 'message']);
    }

    private function addNotFoundResponse(Operation $operation): void
    {
        if (! str_contains($operation->path, '{') || $this->hasResponseCode($operation, 404)) {
            return;
        }

        $operation->addResponse(
            Response::make(404)
                ->setDescription('Resource not found')
                ->setContent(
                    'application/json',
                    Schema::fromType(
                        (new ObjectType)
                            ->addProperty('message', (new StringType)->const('Resource not found'))
                            ->setRequired(['message'])
                    ),
                )
        );
    }

    private function addForbiddenResponseForAdmin(Operation $operation): void
    {
        if (! str_contains($operation->path, '/admin/') || $this->hasResponseCode($operation, 403)) {
            return;
        }

        $operation->addResponse(
            Response::make(403)
                ->setDescription('Forbidden')
                ->setContent(
                    'application/json',
                    Schema::fromType(
                        (new ObjectType)
                            ->addProperty('message', (new StringType)->const('Forbidden'))
                            ->setRequired(['message'])
                    ),
                )
        );
    }

    private function hasResponseCode(Operation $operation, int $code): bool
    {
        foreach ($operation->responses ?? [] as $response) {
            if ($response instanceof Response && (int) $response->code === $code) {
                return true;
            }
        }

        return false;
    }
}
