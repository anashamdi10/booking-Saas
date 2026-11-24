<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Http\Resources\PhotographerResource;
use App\Models\PhotographerProfile;
use App\Services\WhatsAppService;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotographerController extends Controller
{
    public function __construct(private readonly WhatsAppService $whatsapp) {}

    /**
     * Public directory of photographers.
     */
    public function index(Request $request): JsonResponse
    {
        // Query strings carry booleans as text, which the `boolean` rule rejects.
        if ($request->has('featured')) {
            $request->merge([
                'featured' => filter_var($request->query('featured'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:100'],
            'featured' => ['nullable', 'boolean'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'in:newest,price_low,price_high,rating'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $query = PhotographerProfile::query()
            ->with(['user:id,name', 'user.packages:id,photographer_id,is_active'])
            ->where('is_public', true)
            ->whereHas('user', fn ($q) => $q->where('status', UserStatus::Active->value));

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('headline', 'like', $term)
                    ->orWhere('bio', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $term));
            });
        }

        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if (! empty($filters['featured'])) {
            $query->where('is_featured', true);
        }

        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            $query->whereHas('user.packages', function ($packages) use ($filters): void {
                $packages->where('is_active', true);

                if (isset($filters['min_price'])) {
                    $packages->where('price', '>=', $filters['min_price']);
                }

                if (isset($filters['max_price'])) {
                    $packages->where('price', '<=', $filters['max_price']);
                }
            });
        }

        match ($filters['sort'] ?? 'newest') {
            'price_low' => $query->orderBy('rating_avg', 'desc'),
            'price_high' => $query->orderBy('rating_avg', 'desc'),
            'rating' => $query->orderByDesc('rating_avg')->orderByDesc('rating_count'),
            default => $query->latest('published_at')->latest('id'),
        };

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 12));

        return response()->json([
            'data' => PhotographerResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Public profile page of a single photographer.
     */
    public function show(string $slug): JsonResponse
    {
        $profile = PhotographerProfile::query()
            ->with(['user:id,name', 'user.portfolioImages' => fn ($q) => $q->where('is_visible', true)])
            ->where('slug', $slug)
            ->where('is_public', true)
            ->whereHas('user', fn ($q) => $q->where('status', UserStatus::Active->value))
            ->firstOrFail();

        $packages = $profile->user->packages()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => [
                'profile' => new PhotographerResource($profile),
                'packages' => PackageResource::collection($packages),
                'working_hours' => $profile->user->workingHours()
                    ->where('is_active', true)
                    ->orderBy('weekday')
                    ->get()
                    ->map(fn ($hour) => [
                        'weekday' => $hour->weekday,
                        'start_time' => substr((string) $hour->start_time, 0, 5),
                        'end_time' => substr((string) $hour->end_time, 0, 5),
                    ]),
                'portfolio' => $profile->user->portfolioImages
                    ->map(fn ($image) => [
                        'id' => $image->id,
                        'url' => MediaUrl::make($image->path),
                        'thumbnail_url' => MediaUrl::make($image->thumbnail_path),
                        'width' => $image->width,
                        'height' => $image->height,
                    ]),
                'whatsapp_url' => $this->whatsapp->linkForProfile($profile),
                'bookings_count' => $profile->user->bookings()->where('status', BookingStatus::Completed->value)->count(),
            ],
        ]);
    }
}
