<?php

use App\Services\RequestHasher;
use Illuminate\Http\Request;

function makeJsonRequest(string $body, string $uri = '/api/orders'): Request
{
    return Request::create($uri, 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
}

it('дает одинаковый хеш при разном порядке ключей объекта', function () {
    $hasher = new RequestHasher;

    $a = makeJsonRequest('{"a":1,"b":{"y":2,"x":1}}');
    $b = makeJsonRequest('{"b":{"x":1,"y":2},"a":1}');

    expect($hasher->make($a))->toBe($hasher->make($b));
});

it('дает разный хеш при разном порядке элементов списка', function () {
    $hasher = new RequestHasher;

    $a = makeJsonRequest('{"route":["Moscow","Kazan"]}');
    $b = makeJsonRequest('{"route":["Kazan","Moscow"]}');

    expect($hasher->make($a))->not->toBe($hasher->make($b));
});

it('дает разный хеш для разных путей с одинаковым телом', function () {
    $hasher = new RequestHasher;

    $a = makeJsonRequest('{"a":1}', '/api/orders');
    $b = makeJsonRequest('{"a":1}', '/api/payments');

    expect($hasher->make($a))->not->toBe($hasher->make($b));
});
