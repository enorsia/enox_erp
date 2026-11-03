<?php

namespace App\Http\Controllers;

use App\Http\Clients\EnoxApiClient;
use App\Services\EcommerceListingScopeCtrResolver;
use App\Services\EcommerceRankingListingContextService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EcommerceRankingProductsController extends Controller
{
    private const PER_PAGE = 20;

    /** @var list<string> */
    private const ALLOWED_LISTING_SCOPES = [
        'global',
        'department',
        'category',
        'micro-category',
        'new-in',
        'special-department',
    ];

    public function index(
        Request $request,
        EcommerceListingScopeCtrResolver $ctrResolver,
        EcommerceRankingListingContextService $listingContextService,
    ): View {
        Gate::authorize('ecommerce.ranking_products.index');

        $validated = Validator::make($request->all(), [
            'listing_scope' => 'sometimes|string|max:32',
            'listing_scope_id' => 'sometimes|integer|min:0',
            'context' => 'sometimes|string|max:64',
            'page' => 'sometimes|integer|min:1',
            'search' => 'sometimes|string|max:255',
        ])->validate();

        [$contextKey, $listingScope, $listingScopeId] = $this->resolveRankingContext($validated, $request);
        $search = trim((string) ($validated['search'] ?? $request->query('search', '')));
        $page = max(1, (int) ($validated['page'] ?? $request->integer('page', 1)));
        $paginationQuery = $this->paginationQuery($contextKey, $search);
        $listingContexts = $listingContextService->contexts();
        $labelsByContextKey = collect($listingContexts)->mapWithKeys(
            fn (array $ctx): array => [(string) $ctx['key'] => (string) $ctx['label']],
        )->all();

        $apiError = null;
        $sortingSalesDays = 30;
        $paginator = new LengthAwarePaginator(
            [],
            0,
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $paginationQuery],
        );

        if ($listingScope !== null && ! in_array($listingScope, self::ALLOWED_LISTING_SCOPES, true)) {
            $apiError = 'Invalid listing scope.';
        } else {
            [$apiPayload, $apiError] = $this->fetchRankingProductsPage(
                $contextKey !== '' ? $contextKey : null,
                $listingScope,
                $listingScopeId,
                $page,
                self::PER_PAGE,
                $search,
            );

            if ($apiError === null && is_array($apiPayload)) {
                $sortingSalesDays = max(1, (int) ($apiPayload['sorting_sales_days'] ?? 30));
                $apiRows = collect($apiPayload['data'] ?? []);
                $rows = $this->transformRankingApiRows($apiRows, $labelsByContextKey, $ctrResolver);

                $pagination = is_array($apiPayload['pagination'] ?? null) ? $apiPayload['pagination'] : [];
                $paginator = new LengthAwarePaginator(
                    $rows->all(),
                    (int) ($pagination['total'] ?? $rows->count()),
                    (int) ($pagination['per_page'] ?? self::PER_PAGE),
                    (int) ($pagination['current_page'] ?? $page),
                    ['path' => $request->url(), 'query' => $paginationQuery],
                );
            }
        }

        $listingScopeLabel = $listingContextService->labelForKey(
            $contextKey,
            $listingContexts,
            $listingScope,
            $listingScopeId,
        );

        return view('ecommerce.ranking_products.index', [
            'rows' => $paginator,
            'contextKey' => $contextKey,
            'listingContexts' => $listingContexts,
            'listingScope' => $listingScope,
            'listingScopeId' => $listingScopeId,
            'listingScopeLabel' => $listingScopeLabel,
            'search' => $search,
            'apiError' => $apiError,
            'contextsLoaded' => $listingContexts !== [],
            'resetUrl' => route('admin.ecommerce.ranking-products.index'),
            'filterFormAction' => route('admin.ecommerce.ranking-products.index'),
            'paginationQuery' => $paginationQuery,
            'sortingSalesDays' => $sortingSalesDays,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: ?string, 2: ?int}
     */
    private function resolveRankingContext(array $validated, Request $request): array
    {
        $contextInRequest = array_key_exists('context', $validated) || $request->query->has('context');

        if ($contextInRequest) {
            $contextKey = trim((string) ($validated['context'] ?? $request->query('context', '')));
            if ($contextKey === '') {
                return ['', null, null];
            }

            if (str_contains($contextKey, ':')) {
                [$scopeFromKey, $scopeIdFromKey] = explode(':', $contextKey, 2);

                return [
                    $contextKey,
                    (string) $scopeFromKey,
                    max(0, (int) $scopeIdFromKey),
                ];
            }
        }

        [$listingScope, $listingScopeId] = $this->resolveRankingListingScope($validated, $request);

        if ($listingScope !== null && $listingScope !== '') {
            $scopeId = $listingScopeId ?? 0;

            return [$listingScope.':'.$scopeId, $listingScope, $scopeId];
        }

        return ['', null, null];
    }

    /**
     * @return array<string, scalar|null>
     */
    private function paginationQuery(string $contextKey, string $search): array
    {
        $query = [];

        if ($contextKey !== '') {
            $query['context'] = $contextKey;
        }

        if ($search !== '') {
            $query['search'] = $search;
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveRankingListingScope(array $validated, Request $request): array
    {
        $scopeProvided = array_key_exists('listing_scope', $validated)
            && ($validated['listing_scope'] ?? '') !== '';
        $scopeIdProvided = array_key_exists('listing_scope_id', $validated)
            && $validated['listing_scope_id'] !== null;

        if (array_key_exists('context', $validated) || $request->query->has('context')) {
            return [null, null];
        }

        $context = trim((string) ($validated['context'] ?? $request->query('context', '')));
        if (! $scopeProvided && ! $scopeIdProvided && $context !== '' && str_contains($context, ':')) {
            [$scope, $scopeId] = explode(':', $context, 2);

            return [(string) $scope, max(0, (int) $scopeId)];
        }

        if ($scopeProvided) {
            $scope = (string) $validated['listing_scope'];

            return [
                $scope,
                $scopeIdProvided ? max(0, (int) $validated['listing_scope_id']) : null,
            ];
        }

        if ($scopeIdProvided) {
            return [null, max(0, (int) $validated['listing_scope_id'])];
        }

        if ($request->query->has('listing_scope') || $request->query->has('listing_scope_id')) {
            $scope = trim((string) $request->query('listing_scope', ''));
            if ($scope === '') {
                return [null, null];
            }

            return [
                $scope,
                $request->query->has('listing_scope_id')
                    ? max(0, (int) $request->query('listing_scope_id'))
                    : null,
            ];
        }

        return [null, null];
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function fetchRankingProductsPage(
        ?string $contextKey,
        ?string $listingScope,
        ?int $listingScopeId,
        int $page,
        int $perPage,
        string $search = '',
    ): array {
        if (rtrim((string) config('enox.base_url'), '/') === '') {
            return [null, 'ENOX_API_BASE_URL is not configured.'];
        }

        $endpoint = (string) config('enox.endpoints.tracking_ranking_products', 'tracking/ranking-products');

        $params = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        if ($contextKey !== null && $contextKey !== '') {
            $params['context'] = $contextKey;
        } else {
            if ($listingScope !== null && $listingScope !== '') {
                $params['listing_scope'] = $listingScope;
            }

            if ($listingScopeId !== null) {
                $params['listing_scope_id'] = $listingScopeId;
            }
        }

        if ($search !== '') {
            $params['search'] = $search;
        }

        try {
            $client = app(EnoxApiClient::class);
            $response = $client->get($endpoint, $params);
        } catch (\Throwable $exception) {
            Log::error('Ranking products: tracking/ranking-products request failed.', [
                'listing_scope' => $listingScope,
                'listing_scope_id' => $listingScopeId,
                'error' => $exception->getMessage(),
            ]);

            return [null, 'Could not load ranking products from Enorsia.'];
        }

        if (! $response->successful()) {
            Log::warning('Ranking products: tracking/ranking-products HTTP error.', [
                'listing_scope' => $listingScope,
                'listing_scope_id' => $listingScopeId,
                'http_status' => $response->status(),
                'body' => mb_substr(trim((string) $response->body()), 0, 500),
            ]);

            return [null, 'Enorsia returned HTTP '.$response->status().' for ranking products.'];
        }

        $payload = $response->json();
        if (! is_array($payload) || ! ($payload['status'] ?? false)) {
            $message = is_array($payload) ? (string) ($payload['message'] ?? 'Ranking products request failed.') : 'Invalid response.';

            return [null, $message];
        }

        return [$payload, null];
    }

    /**
     * @param  array<string, string>  $labelsByContextKey
     * @return Collection<int, array<string, mixed>>
     */
    private function transformRankingApiRows(
        Collection $apiRows,
        array $labelsByContextKey,
        EcommerceListingScopeCtrResolver $ctrResolver,
    ): Collection {
        $ctrByContextCode = [];

        foreach ($apiRows->groupBy(fn (array $item): string => ($item['listing_scope'] ?? '').':'.($item['listing_scope_id'] ?? 0)) as $rowContextKey => $group) {
            [$scope, $scopeId] = array_pad(explode(':', (string) $rowContextKey, 2), 2, '0');
            $codes = $group
                ->map(fn (array $item): string => trim((string) ($item['product_code'] ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($codes === []) {
                continue;
            }

            $metrics = $ctrResolver->metricsByProductCode((string) $scope, (int) $scopeId, $codes);
            foreach ($metrics as $code => $metric) {
                $ctrByContextCode[$rowContextKey][$code] = $metric;
            }
        }

        return $apiRows->map(function (array $item) use ($ctrByContextCode, $labelsByContextKey): array {
            $code = trim((string) ($item['product_code'] ?? ''));
            $scope = (string) ($item['listing_scope'] ?? '');
            $scopeId = (int) ($item['listing_scope_id'] ?? 0);
            $rowContextKey = $scope.':'.$scopeId;
            $settings = is_array($item['product_ranking_settings'] ?? null)
                ? $item['product_ranking_settings']
                : [];
            $pin = is_array($item['pin'] ?? null) ? $item['pin'] : [];
            $ctr = $ctrByContextCode[$rowContextKey][$code] ?? null;
            $pinSerial = isset($pin['pin_serial']) ? (int) $pin['pin_serial'] : 0;

            return [
                'context_key' => $rowContextKey,
                'context_label' => $labelsByContextKey[$rowContextKey] ?? $rowContextKey,
                'listing_scope' => $scope,
                'listing_scope_id' => $scopeId,
                'serial_number' => (int) ($item['serial_number'] ?? 0),
                'product_code' => $code,
                'total_sold' => (int) ($settings['total_sold'] ?? 0),
                'total_sold_rolling' => (int) ($settings['total_sold_rolling'] ?? 0),
                'is_stock_out' => (int) ($settings['is_stock_out'] ?? 0),
                'is_new_in' => (int) ($settings['is_new_in'] ?? 0),
                'is_pinned' => (int) ($pin['is_pinned'] ?? ($pinSerial > 0 ? 1 : 0)),
                'pin_serial' => $pinSerial > 0 ? $pinSerial : null,
                'is_selling_fast' => (int) ($settings['is_selling_fast'] ?? 0),
                'click' => (int) ($ctr['total_click'] ?? 0),
                'ctr' => (float) ($ctr['ctr'] ?? 0),
                'ctr_average' => (float) ($ctr['ctr_average'] ?? 0),
            ];
        });
    }
}
