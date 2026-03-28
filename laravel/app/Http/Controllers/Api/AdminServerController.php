<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServerRequest;
use App\Http\Requests\Admin\UpdateServerRequest;
use App\Http\Resources\AdminServerResource;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminServerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AdminServerResource::collection(
            Server::query()->orderBy('name')->get()
        );
    }

    public function store(StoreServerRequest $request): JsonResponse
    {
        $server = Server::query()->create($request->validated());

        return (new AdminServerResource($server))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateServerRequest $request, Server $server): AdminServerResource
    {
        $server->update($request->validated());

        return new AdminServerResource($server->fresh());
    }
}
