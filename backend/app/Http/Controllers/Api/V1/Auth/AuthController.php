<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeEmailRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Services\SlugService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * The longest token lifetime that may be issued.
     *
     * One year keeps the token inside the MySQL DATETIME range and is a sane
     * upper bound for a long lived session.
     */
    private const MAX_TOKEN_LIFETIME_MINUTES = 525_600;

    public function __construct(private readonly SlugService $slugs) {}

    /**
     * Calculates when an issued token should expire.
     *
     * `config('sanctum.expiration')` is expressed in MINUTES, exactly like the
     * value Sanctum uses when it validates a token.
     */
    private function tokenExpiry(): CarbonImmutable
    {
        $minutes = (int) config('sanctum.expiration', 10080);
        $minutes = max(5, min($minutes, self::MAX_TOKEN_LIFETIME_MINUTES));

        return CarbonImmutable::now()->addMinutes($minutes);
    }

    /**
     * Register a new photographer account. The account stays pending until an
     * administrator approves it and assigns a subscription plan.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'phone' => $request->input('phone'),
            'role' => UserRole::Photographer,
            'status' => UserStatus::Pending,
        ]);

        PhotographerProfile::query()->create([
            'user_id' => $user->id,
            'slug' => $this->slugs->forPhotographer($user->name, $user->id),
            'country' => 'SA',
        ]);

        $token = $user->createToken('spa', ['*'], $this->tokenExpiry());

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح، وهو الآن قيد المراجعة.',
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $throttleKey = 'login:'.$request->string('email')->toString().'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "محاولات دخول كثيرة، يرجى المحاولة بعد {$seconds} ثانية.",
            ]);
        }

        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'بيانات الدخول غير صحيحة.',
            ]);
        }

        if ($user->status === UserStatus::Suspended) {
            throw ValidationException::withMessages([
                'email' => 'تم إيقاف هذا الحساب، يرجى التواصل مع الدعم.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user->tokens()->where('name', 'spa')->delete();

        $token = $user->createToken('spa', ['*'], $this->tokenExpiry());

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح.',
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => new UserResource($user),
            'profile' => $user->profile,
            'subscription' => $user->activeSubscription?->load('plan'),
        ]);
    }

    /**
     * Replace the address the account signs in with.
     *
     * The current password is mandatory (see ChangeEmailRequest): a stolen
     * token on its own must not be enough to point the account somewhere else.
     * The session already held stays valid — tokens belong to the user id, not
     * to the address, so nobody is signed out by this change.
     */
    public function changeEmail(ChangeEmailRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->email = $request->string('email')->toString();
        $user->save();

        return response()->json([
            'message' => 'تم تحديث البريد الإلكتروني.',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Rotate the password. The plain value is hashed by the model's `hashed`
     * cast, and the session in use is deliberately left untouched so the user
     * is not thrown out of the dashboard they are standing in.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->password = $request->string('password')->toString();
        $user->save();

        return response()->json(['message' => 'تم تغيير كلمة المرور بنجاح.']);
    }
}
