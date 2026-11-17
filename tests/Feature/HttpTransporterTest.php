<?php

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Voharaa\PrivatbankAutoclient\Client;
use Voharaa\PrivatbankAutoclient\Exceptions\ApiException;
use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;

describe('get()', function () {
    it('sends a GET request with the required headers', function () {
        $transporter = mockTransporter([jsonResponse(apiFixture('settings'))], $history, token: 'secret-token');

        $transporter->get('statements/settings');

        $request = $history[0]['request'];

        expect($request->getMethod())->toBe('GET')
            ->and($request->getHeaderLine('User-Agent'))->toBe('voharaa/privatbank-autoclient')
            ->and((string) $request->getUri())->toBe('https://acp.privatbank.ua/api/statements/settings')
            ->and($request->getHeaderLine('token'))->toBe('secret-token')
            ->and($request->getHeaderLine('Accept'))->toBe('application/json')
            ->and($request->getHeaderLine('Content-Type'))->toBe('application/json;charset=utf8');
    });

    it('builds the query string', function () {
        $transporter = mockTransporter([jsonResponse(apiFixture('balances'))], $history);

        $transporter->get('statements/balance', ['acc' => 'UA94', 'startDate' => '01-11-2026']);

        expect((string) $history[0]['request']->getUri())
            ->toBe('https://acp.privatbank.ua/api/statements/balance?acc=UA94&startDate=01-11-2026');
    });

    it('returns the decoded body', function () {
        $transporter = mockTransporter([jsonResponse(apiFixture('settings'))]);

        expect($transporter->get('statements/settings'))->toBe(apiFixture('settings'));
    });

    it('throws ApiException with the error details from the body', function () {
        $transporter = mockTransporter([jsonResponse([
            'status' => 'ERROR',
            'code' => '400',
            'message' => 'invalid document number',
            'requestId' => '20240223_131617_286f',
            'serviceCode' => 'PMTSRV0112',
        ], 400)]);

        try {
            $transporter->get('statements/settings');
            $this->fail('ApiException was not thrown');
        } catch (ApiException $e) {
            expect($e->getMessage())->toBe('invalid document number')
                ->and($e->getCode())->toBe(400)
                ->and($e->statusCode)->toBe(400)
                ->and($e->errorCode)->toBe('400')
                ->and($e->serviceCode)->toBe('PMTSRV0112')
                ->and($e->requestId)->toBe('20240223_131617_286f')
                ->and($e->response['status'])->toBe('ERROR');
        }
    });

    it('throws ApiException when status is not SUCCESS even with HTTP 200', function () {
        $transporter = mockTransporter([jsonResponse(['status' => 'ERROR', 'message' => 'Something went wrong'])]);

        $transporter->get('statements/settings');
    })->throws(ApiException::class, 'Something went wrong');

    it('accepts a successful response without a status field', function () {
        $transporter = mockTransporter([jsonResponse(apiFixture('currency'))]);

        expect($transporter->get('proxy/currency/'))->toBe(apiFixture('currency'));
    });

    it('throws ApiException with the HTTP status when the error body is not JSON', function (int $status) {
        $transporter = mockTransporter([new Response($status, [], '<html>Bad Gateway</html>')]);

        try {
            $transporter->get('statements/settings');
            $this->fail('ApiException was not thrown');
        } catch (ApiException $e) {
            expect($e->statusCode)->toBe($status)
                ->and($e->errorCode)->toBeNull()
                ->and($e->response)->toBe([])
                ->and($e->getMessage())->toBe("API request failed with HTTP status {$status}");
        }
    })->with([401, 403, 500, 502, 503, 504]);

    it('throws TransportException when a successful response is not JSON', function () {
        $transporter = mockTransporter([new Response(200, [], 'not json')]);

        $transporter->get('statements/settings');
    })->throws(TransportException::class, 'Response body is not valid JSON');

    it('throws TransportException when the body is not a JSON object', function () {
        $transporter = mockTransporter([new Response(200, [], '"just a string"')]);

        $transporter->get('statements/settings');
    })->throws(TransportException::class, 'Response body is not a JSON object');

    it('wraps network errors in TransportException', function () {
        $request = new Request('GET', 'https://acp.privatbank.ua/api/statements/settings');
        $transporter = mockTransporter([new ConnectException('Connection refused', $request)]);

        $transporter->get('statements/settings');
    })->throws(TransportException::class, 'Connection refused');
});

describe('paginate()', function () {
    function page(array $rows, array $extra = []): Response
    {
        return jsonResponse(['status' => 'SUCCESS', 'balances' => $rows] + $extra);
    }

    it('yields rows from every page and passes next_page_id as followId', function () {
        $transporter = mockTransporter([
            page([['n' => 1], ['n' => 2]], ['exist_next_page' => true, 'next_page_id' => 'page-2']),
            page([['n' => 3]], ['exist_next_page' => 'true', 'next_page_id' => 'page-3']),
            page([['n' => 4]], ['exist_next_page' => false]),
        ], $history);

        $rows = iterator_to_array($transporter->paginate('statements/balance', 'balances', ['limit' => 2]), false);

        expect(array_column($rows, 'n'))->toBe([1, 2, 3, 4])
            ->and(array_map(fn ($h) => $h['request']->getUri()->getQuery(), $history))->toBe([
                'limit=2',
                'limit=2&followId=page-2',
                'limit=2&followId=page-3',
            ]);
    });

    it('stops when exist_next_page is missing', function () {
        $transporter = mockTransporter([page([['n' => 1]])], $history);

        expect(iterator_to_array($transporter->paginate('statements/balance', 'balances'), false))->toHaveCount(1)
            ->and($history)->toHaveCount(1);
    });

    it('handles a page without the key', function () {
        $transporter = mockTransporter([jsonResponse(['status' => 'SUCCESS'])]);

        expect(iterator_to_array($transporter->paginate('statements/balance', 'balances'), false))->toBe([]);
    });

    it('accepts a numeric next_page_id', function () {
        $transporter = mockTransporter([
            page([['n' => 1]], ['exist_next_page' => true, 'next_page_id' => 26101050000888]),
            page([['n' => 2]]),
        ], $history);

        iterator_to_array($transporter->paginate('statements/balance', 'balances'), false);

        expect($history[1]['request']->getUri()->getQuery())->toBe('followId=26101050000888');
    });

    it('does not request the next page until it is needed', function () {
        $transporter = mockTransporter([
            page([['n' => 1]], ['exist_next_page' => true, 'next_page_id' => 'page-2']),
            page([['n' => 2]]),
        ], $history);

        foreach ($transporter->paginate('statements/balance', 'balances') as $row) {
            break;
        }

        expect($history)->toHaveCount(1);
    });

    it('fails instead of looping forever when next_page_id is missing', function (mixed $nextPageId) {
        $extra = ['exist_next_page' => true] + ($nextPageId === 'missing' ? [] : ['next_page_id' => $nextPageId]);
        $transporter = mockTransporter([page([['n' => 1]], $extra), page([['n' => 1]], $extra)]);

        iterator_to_array($transporter->paginate('statements/balance', 'balances'), false);
    })->with(['missing', null, ''])
        ->throws(TransportException::class, 'API reported a next page but returned no next_page_id');

    it('fails when the list contains something other than objects', function () {
        $transporter = mockTransporter([page(['not an object'])]);

        iterator_to_array($transporter->paginate('statements/balance', 'balances'), false);
    })->throws(TransportException::class, '"balances" must be a list of objects');

    it('fails when the list is not a list', function () {
        $transporter = mockTransporter([jsonResponse(['status' => 'SUCCESS', 'balances' => 'oops'])]);

        iterator_to_array($transporter->paginate('statements/balance', 'balances'), false);
    })->throws(TransportException::class, 'field "balances" is not an object or a list');

    it('fails instead of looping forever when next_page_id repeats', function () {
        $transporter = mockTransporter([
            page([['n' => 1]], ['exist_next_page' => true, 'next_page_id' => 'same']),
            page([['n' => 2]], ['exist_next_page' => true, 'next_page_id' => 'same']),
            page([['n' => 3]]),
        ]);

        iterator_to_array($transporter->paginate('statements/balance', 'balances'), false);
    })->throws(TransportException::class, 'API returned next_page_id "same" twice');
});

it('loads balances end to end through the HTTP transporter', function () {
    $second = apiFixture('balances');
    $second['balances'][0]['acc'] = 'UA000000000000000000000000002';

    $client = new Client(mockTransporter([
        jsonResponse(['exist_next_page' => true, 'next_page_id' => 'page-2'] + apiFixture('balances')),
        jsonResponse($second),
    ], $history));

    $balances = $client->statements()->balances(new DateTimeImmutable('2026-11-01'))->all();

    expect(array_map(fn ($b) => $b->account, $balances))->toBe([
        'UA943052990000026100050001037',
        'UA000000000000000000000000002',
    ])->and($history)->toHaveCount(2);
});
