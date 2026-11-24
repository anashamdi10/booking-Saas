<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Photographer\UpdateBookingRequest;
use App\Http\Requests\Public\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\PhotographerProfile;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    /**
     * Public booking request coming from the photographer's public page.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $profile = PhotographerProfile::query()
            ->with('user')
            ->where('slug', $request->string('photographer')->toString())
            ->where('is_public', true)
            ->firstOrFail();

        if ($profile->user->status !== UserStatus::Active) {
            return response()->json(['message' => 'هذا المصور غير متاح حالياً للحجز.'], 422);
        }

        try {
            $booking = $this->bookings->createForPhotographer($profile->user, [
                'start_at' => CarbonImmutable::parse($request->string('start_at')->toString()),
                'package_id' => $request->input('package_id'),
                'customer_name' => $request->string('customer_name')->toString(),
                'customer_email' => $request->string('customer_email')->toString(),
                'customer_phone' => $request->input('customer_phone'),
                'notes' => $request->input('notes'),
                'source' => $request->input('source', BookingSource::Website->value),
            ]);
        } catch (ModelNotFoundException $exception) {
            // ModelNotFoundException extends RuntimeException, so the generic
            // branch below would swallow it and answer 422. An unknown, foreign
            // or deactivated package is a missing resource: let the framework
            // render it as 404.
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تم استلام طلب الحجز بنجاح، سيتواصل معك المصور لتأكيد الموعد.',
            'data' => new BookingResource($booking),
        ], 201);
    }

    /**
     * Bookings of the authenticated photographer.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_column(BookingStatus::cases(), 'value'))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $query = Booking::query()
            ->with('package:id,name,duration_minutes')
            ->where('photographer_id', $request->user()->id)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('start_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('start_at', '<=', $to))
            ->orderBy('start_at');

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 20));

        return response()->json([
            'data' => BookingResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'counts' => $this->statusCounts($request->user()->id),
        ]);
    }

    /**
     * Update the status of a booking (confirm / reject / complete / cancel).
     */
    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        $status = BookingStatus::from($request->string('status')->toString());

        DB::transaction(function () use ($booking, $status, $request): void {
            $booking->status = $status;

            if ($status === BookingStatus::Confirmed) {
                $booking->confirmed_at = now();
            }

            if (in_array($status, [BookingStatus::Cancelled, BookingStatus::Rejected], true)) {
                $booking->cancelled_at = now();
                $booking->cancellation_reason = $request->input('cancellation_reason');
            }

            $booking->save();
        });

        return response()->json([
            'message' => 'تم تحديث حالة الحجز.',
            'data' => new BookingResource($booking->refresh()->load('package:id,name,duration_minutes')),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(int $photographerId): array
    {
        $counts = Booking::query()
            ->where('photographer_id', $photographerId)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = [];

        foreach (BookingStatus::cases() as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }
}
