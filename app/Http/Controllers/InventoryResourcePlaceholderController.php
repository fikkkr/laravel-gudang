<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryResourcePlaceholderController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function create(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function store(Request $request): JsonResponse
    {
        return $this->notImplemented();
    }

    public function show(string $id): JsonResponse
    {
        return $this->notImplemented();
    }

    public function edit(string $id): JsonResponse
    {
        return $this->notImplemented();
    }

    public function update(Request $request, string $id): JsonResponse
    {
        return $this->notImplemented();
    }

    public function destroy(string $id): JsonResponse
    {
        return $this->notImplemented();
    }

    private function notImplemented(): JsonResponse
    {
        return response()->json([
            'message' => 'Fitur transaksi belum tersedia.',
        ], 501);
    }
}
