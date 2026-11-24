<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Photographer\StorePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use App\Models\User;
use App\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function __construct(private readonly SlugService $slugs) {}

    /**
     * One photographer's packages, listed exactly as their own dashboard
     * lists them — same ordering, same shape.
     */
    public function index(Request $request, User $photographer): JsonResponse
    {
        $this->guard($photographer);

        $packages = $photographer->packages()->orderBy('sort_order')->get();

        return response()->json(['data' => PackageResource::collection($packages)]);
    }

    /**
     * Create a package on the photographer's behalf. The slug is generated
     * under *their* id, so it can never take a name another photographer's
     * package already owns.
     */
    public function store(StorePackageRequest $request, User $photographer): JsonResponse
    {
        $this->guard($photographer);
        $this->authorize('create', Package::class);

        $data = $request->validated();
        $data['slug'] = $this->slugs->forPackage($photographer->id, $data['name']);

        $package = $photographer->packages()->create($data);

        return response()->json([
            'message' => 'تم إنشاء الباقة.',
            'data' => new PackageResource($package),
        ], 201);
    }

    public function update(StorePackageRequest $request, User $photographer, Package $package): JsonResponse
    {
        $this->guard($photographer, $package);
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

    public function destroy(User $photographer, Package $package): JsonResponse
    {
        $this->guard($photographer, $package);
        $this->authorize('delete', $package);

        $package->delete();

        return response()->json(['message' => 'تم حذف الباقة.']);
    }

    /**
     * The path names a photographer, so anything else — an admin or a
     * customer account — has to look like a missing resource rather than an
     * empty list. When a package id is in play it must belong to that same
     * photographer, otherwise the URL would describe one thing and touch
     * another. (The policy still has to allow the action on top of this.)
     */
    private function guard(User $photographer, ?Package $package = null): void
    {
        abort_unless($photographer->isPhotographer(), 404);

        if ($package !== null) {
            abort_unless($package->photographer_id === $photographer->id, 404);
        }
    }
}
