<?php

declare(strict_types=1);

namespace Fixture\logic;

use Lumnd\PlatoApiContract\Runtime\ApiContext;

/**
 * A hand-written Logic implementation for a body whose array holds structures.
 *
 * It reads the elements straight, with no check of its own: whatever arrives here has already been
 * refused or accepted against the rules the contract declared for `items.*`.
 */
final class ping_save_items
{
    /**
     * @param array{
     *     items: list<array{sku: string, qty: int, note: string|null}>,
     *     buyer: array{nick: string|null}|null,
     * } $request
     * @return array{count: int, first_sku: string, total: int, buyer_nick: string|null}
     */
    public static function handle(array $request, ApiContext $context): array
    {
        $total = 0;
        foreach ($request['items'] as $item) {
            $total += $item['qty'];
        }

        return [
            'count' => count($request['items']),
            'first_sku' => $request['items'][0]['sku'],
            'total' => $total,
            'buyer_nick' => $request['buyer']['nick'] ?? null,
        ];
    }
}
