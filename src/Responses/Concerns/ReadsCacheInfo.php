<?php

namespace Voharaa\PrivatbankAutoclient\Responses\Concerns;

use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;
use Voharaa\PrivatbankAutoclient\Support\Payload;

/**
 * Maps the "cache_info" object returned by the proxy/currency endpoints.
 */
trait ReadsCacheInfo
{
    /** Whether the API served the data from its cache. Null if the API did not report it. */
    public readonly ?bool $fromCache;

    /** When the data was cached, Unix time in milliseconds. Null if the data was not cached (the API sends 0). */
    public readonly ?int $cacheTime;

    /** Current server time, Unix time in milliseconds. */
    public readonly ?int $serverTime;

    /**
     * Values for [$fromCache, $cacheTime, $serverTime]. Readonly properties can only be assigned
     * in the constructor, so the using class assigns them there:
     * [$this->fromCache, $this->cacheTime, $this->serverTime] = self::readCacheInfo($data);
     *
     * @param  array<mixed>  $data
     * @return array{?bool, ?int, ?int}
     *
     * @throws TransportException
     */
    private static function readCacheInfo(array $data): array
    {
        $cacheInfo = isset($data['cache_info']) ? Payload::array($data, 'cache_info') : [];
        $cacheTime = Payload::optionalInt($cacheInfo, 'cache_time');

        return [
            array_key_exists('from_cache', $cacheInfo) ? Payload::bool($cacheInfo, 'from_cache') : null,
            $cacheTime === 0 ? null : $cacheTime,
            Payload::optionalInt($cacheInfo, 'server_time'),
        ];
    }
}
