<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Controller;

use Psr\Container\{ContainerExceptionInterface, ContainerInterface};
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Throwable;
use UniMethod\Frame\Config\ActionType;
use UniMethod\Frame\Exception\FrameConfigurationException;
use UniMethod\Frame\Integration\{ApiService, Error, Errors};

readonly class ApiController
{
    /**
     * @param ContainerInterface $apiServices ServiceLocator: API name → ApiService
     */
    public function __construct(
        private ContainerInterface $apiServices,
        private LoggerInterface $logger,
        #[Autowire(param: "kernel.debug")]
        private bool $debug = false,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $model = $request->attributes->getString("_model");
        $actionType = $request->attributes->getString("_actionType");
        $apiName = $request->attributes->getString("_api_name");
        $id = $request->attributes->has("id")
            ? $request->attributes->getString("id")
            : null;

        try {
            /** @var ApiService $apiService */
            $apiService = $this->apiServices->get($apiName);
            $result = $apiService->execute(
                $model,
                ActionType::from($actionType),
                $request->query->all(),
                $request->getContent(),
                $id,
            );
            return new JsonResponse(
                $result->data,
                $result->statusCode,
                $result->headers,
                $result->encoded,
            );
        } catch (FrameConfigurationException | ContainerExceptionInterface $throwable) {
            // The config, impl, application implementations and the container assembly are not
            // consistent with each other. Symfony's own DI errors do not carry the marker,
            // so they are caught by the PSR interface.
            //
            // Конфиг, impl, прикладные реализации и сборка контейнера не
            // согласованы между собой. Ошибки DI самого Symfony маркер не
            // несут, поэтому ловятся по PSR-интерфейсу.
            $this->logger->critical("API configuration error", [
                "exception" => $throwable,
                "api_name" => $apiName,
                "model" => $model,
                "action" => $actionType,
            ]);

            return $this->internalError($throwable, $apiName, $model, $actionType);
        } catch (Throwable $throwable) {
            // Only NON-domain exceptions reach here: domain ones (4xx) are already
            // handled inside ApiService::execute and came back as ApiResult.
            // Here is the application boundary: log the trace and return 500.
            //
            // Сюда долетают только НЕ-доменные исключения: доменные (4xx) уже
            // обработаны внутри ApiService::execute и вернулись как ApiResult.
            // Здесь — граница приложения: логируем трейс и отдаём 500.
            $this->logger->error("API request failed", [
                "exception" => $throwable,
                "api_name" => $apiName,
                "model" => $model,
                "action" => $actionType,
            ]);

            return $this->internalError($throwable, $apiName, $model, $actionType);
        }
    }

    /**
     * In debug mode (kernel.debug) the class and message of the exception go into detail,
     * otherwise — a generic message without internal details.
     *
     * В debug-режиме (kernel.debug) в detail уходит класс и текст исключения,
     * иначе — обобщённое сообщение без внутренних подробностей.
     */
    private function internalError(
        Throwable $throwable,
        string $apiName,
        string $model,
        string $actionType,
    ): JsonResponse {
        $error = new Error(
            status: 500,
            detail: $this->debug
                ? sprintf("%s: %s", $throwable::class, $throwable->getMessage())
                : "Internal Server Error",
            title: "Internal Server Error",
            source: [
                "api_name" => $apiName,
                "model" => $model,
                "action" => $actionType,
            ],
        );

        $errors = new Errors($error);

        return new JsonResponse($errors->toArray(), $errors->getStatus());
    }
}
