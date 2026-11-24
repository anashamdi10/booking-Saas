<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Photographer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Photographer\StorePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use App\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function __construct(private readonly SlugService $slugs) {}

    public function index(Request $request): JsonResponse
    {
        $packages = $request->user()->packages()->orderBy('sort_order')->get();

        return response()->json(['data' => PackageResource::collection($packages)]);
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $this->authorize('create', Package::class);

        $data = $request->validated();
        $data['slug'] = $this->slugs->forPackage($request->user()->id, $data['name']);

        $package = $request->user()->packages()->create($data);

        return response()->json([
            'message' => 'تم إنشاء الباقة.',
            'data' => new PackageResource($package),
        ], 201);
    }

    public function update(StorePackageRequest $request, Package $package): JsonResponse
    {
        $this->authorize('update', $package);

        $data = $request->validated();

        if (isset($data['name']) && $data['name'] !== $package->name) {
            $data['slug'] = $this->slugs->forPackage($package->photographer_id, $data['name']);
        }

        $package->fill($data)->save();

        return response()->json([
            'message' => 'تم تحديث الباقة.',
            'data' => new PackageResource($package->refresh()),
        ]);
    }

    public function destroy(Package $package): JsonResponse
    {
        $this->authorize('delete', $package);

        $package->delete();

        return response()->json(['message' => 'تم حذف الباقة.']);
    }
}
