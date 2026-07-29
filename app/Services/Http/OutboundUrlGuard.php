<?php

namespace App\Services\Http;

use App\Exceptions\Http\BlockedOutboundUrlException;

class OutboundUrlGuard
{
    /**
     * Reserved ranges a workspace-authored URL must never reach. Workflows run with the
     * application's own network position, so an unguarded URL would let a workspace read
     * cloud metadata endpoints or other internal services.
     *
     * @var array<int, string>
     */
    private const BLOCKED_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];

    /**
     * @var array<int, string>
     */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * Throw unless the URL is safe for the application to request on a workspace's behalf.
     *
     * @throws BlockedOutboundUrlException
     */
    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new BlockedOutboundUrlException("Request to [{$url}] was blocked: the URL is malformed.");
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new BlockedOutboundUrlException(
                "Request to [{$url}] was blocked: only http and https URLs are allowed.",
            );
        }

        $host = trim($parts['host'], '[]');

        foreach ($this->resolveAddresses($host) as $address) {
            if ($this->isBlocked($address)) {
                throw new BlockedOutboundUrlException(
                    "Request to [{$url}] was blocked: [{$host}] resolves to the private address [{$address}].",
                );
            }
        }
    }

    /**
     * Every address the host resolves to — a hostname is checked against all of its A
     * records, so a name that points at a private address cannot slip past the range check.
     *
     * @return array<int, string>
     */
    private function resolveAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @gethostbynamel($host);

        if ($records === false) {
            // An unresolvable host cannot be reached anyway; let the HTTP client report it.
            return [];
        }

        return $records;
    }

    private function isBlocked(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $this->isBlockedIpv6($address);
        }

        foreach (self::BLOCKED_RANGES as $range) {
            if ($this->inRange($address, $range)) {
                return true;
            }
        }

        return false;
    }

    private function isBlockedIpv6(string $address): bool
    {
        $packed = @inet_pton($address);

        if ($packed === false) {
            return true;
        }

        // ::1 (loopback), fc00::/7 (unique local), and fe80::/10 (link local).
        return $packed === inet_pton('::1')
            || (ord($packed[0]) & 0xFE) === 0xFC
            || (ord($packed[0]) === 0xFE && (ord($packed[1]) & 0xC0) === 0x80);
    }

    private function inRange(string $address, string $range): bool
    {
        [$subnet, $bits] = explode('/', $range);

        $addressLong = ip2long($address);
        $subnetLong = ip2long($subnet);

        if ($addressLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return ($addressLong & $mask) === ($subnetLong & $mask);
    }
}
