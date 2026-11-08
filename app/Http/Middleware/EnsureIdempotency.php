<?php

namespace App\Http\Middleware;

use App\Services\RequestHasher;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

readonly class EnsureIdempotency
{
    public function __construct(private RequestHasher $hasher) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws \Throwable
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return response()->json(['error' => 'Idempotency-Key header is required'], 400);
        }

        if (strlen($idempotencyKey) > 255) {
            return response()->json(['error' => 'Invalid Idempotency-Key length'], 400);
        }

        $requestHash = $this->hasher->make($request);

        DB::beginTransaction();

        try {
            $inserted = DB::table('idempotency_keys')->insertOrIgnore([
                'key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'created_at' => now(),
                'expires_at' => now()->addDay(),
            ]);

            if ($inserted === 1) {
                $response = $next($request);

                // если 500 и выше откат
                if ($response->getStatusCode() >= 500) {
                    DB::rollBack();

                    return $response;
                }

                // иначе обновляем ключ
                DB::table('idempotency_keys')
                    ->where('key', $idempotencyKey)
                    ->update([
                        'response_code' => $response->getStatusCode(),
                        'response_body' => $response->getContent(),
                        'updated_at' => now(),
                    ]);

                DB::commit();

                return $response;
            }

            DB::rollBack();

            // выбираем существующую строку из базы
            $row = DB::table('idempotency_keys')->where('key', $idempotencyKey)->first();

            // null может придти если ключ удалили очисткой ровно между insertOrIgnore и select
            if ($row === null || $row->response_code === null) {
                return response()->json(['error' => 'Request with this Idempotency-Key is still in progress'], 409);
            }

            // 422 если хеш не совпал
            if ($row->request_hash !== $requestHash) {
                return response()->json([
                    'error' => 'Idempotency Key conflict: key has been used with another request',
                ], 422);
            }

            // при совпадении выкидываем Idempotent-Replayed
            return response($row->response_body, $row->response_code)
                ->header('Content-Type', 'application/json')
                ->header('Idempotent-Replayed', 'true');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
