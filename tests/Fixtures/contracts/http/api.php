<?php

declare(strict_types=1);

use Fixture\api\PingReq;
use Fixture\api\PingResp;

use function Lumnd\PlatoApiContract\Dsl\get;
use function Lumnd\PlatoApiContract\Dsl\post;
use function Lumnd\PlatoApiContract\Dsl\rules;

/**
 * The contract the HTTP feature test generates into the fixture application.
 *
 * The contract mixes public and authenticated actions so real requests prove the router metadata
 * controls authentication rather than a generated controller guard, and it describes one operation
 * with rules() and the rest with DTO classes so both forms are driven by a real request.
 */
return [
    'syntax' => 'v1',
    'description' => 'Ping API',
    'services' => [
        'ping' => [
            get(
                '/ping/index',
                PingReq::class,
                PingResp::class,
                handler: 'index',
                auth: 'none',
                summary: 'Return a ping message',
            ),
            get(
                '/ping/whoami',
                PingReq::class,
                PingResp::class,
                handler: 'whoami',
                auth: 'required',
                summary: 'Echo the authenticated identity',
            ),
            get(
                '/ping/admin',
                PingReq::class,
                PingResp::class,
                handler: 'admin',
                auth: 'required',
                summary: 'Echo for an authenticated caller',
            ),
            get(
                '/ping/echo_message',
                rules([
                    'message' => ['required', 'string', 'min:1', 'max:100'],
                    // Neither carries a rule the validator can run, which is exactly the shape that
                    // used to go missing between the validator and Logic.
                    'loud' => ['boolean', 'default:false'],
                    'note' => ['nullable', 'string'],
                ]),
                rules([
                    'message' => ['string'],
                    'note' => ['string', 'nullable'],
                ]),
                handler: 'echo_message',
                auth: 'none',
                summary: 'Echo a message described by rules rather than a DTO',
            ),
            post(
                '/ping/save_items',
                rules([
                    'items' => ['required', 'array'],
                    // A constraint that only reaches inside an array element, which the validator
                    // has no name for and the generated controller has to name for it.
                    'items.*.sku' => ['required', 'string', 'max:8'],
                    'items.*.qty' => ['integer', 'default:1'],
                    'items.*.note' => ['nullable', 'string'],
                    // Every property optional, so nothing below the object is demanded of the
                    // caller and the object itself is the only thing left to ask for.
                    'buyer.nick' => ['nullable', 'string'],
                ]),
                rules([
                    'count' => ['integer'],
                    'first_sku' => ['string'],
                    'total' => ['integer'],
                    'buyer_nick' => ['string', 'nullable'],
                ]),
                handler: 'save_items',
                auth: 'none',
                summary: 'Save the lines of a basket described by rules',
            ),
        ],
    ],
];
