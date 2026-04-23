<?php

declare(strict_types=1);

namespace netcup\DNS\API;

use Pdp\Domain;
use Pdp\Rules;

final class Config
{
    private static ?Rules $rules = null;

    private string $apiKey;

    private string $apiPassword;

    private int $customerId;

    private string $domain;

    private string $mode;

    private int $ttl;

    private bool $force;

    public function __construct(string $domain, string $mode, int $customerId, string $apiKey, string $apiPassword, int $ttl, bool $force = false)
    {
        if ($domain === '') {
            throw new \InvalidArgumentException('Domain must not be empty');
        }

        if (substr_count($domain, '.') < 1) {
            throw new \InvalidArgumentException('DOMAIN must contain at least one dot');
        }

        if (!in_array($mode, ['@', '*', 'both'], true)) {
            throw new \InvalidArgumentException('MODE must be one of: @, *, both');
        }

        if ($ttl < 0) {
            throw new \InvalidArgumentException('TTL must be zero or positive');
        }

        $this->domain = $domain;
        $this->mode = $mode;
        $this->customerId = $customerId;
        $this->apiKey = $apiKey;
        $this->apiPassword = $apiPassword;
        $this->ttl = $ttl;
        $this->force = $force;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getApiPassword(): string
    {
        return $this->apiPassword;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getMatcher(): array
    {
        return match ($this->mode) {
            'both' => ['@', '*'],
            '*' => ['*'],
            default => ['@'],
        };
    }

    public function getZoneCandidates(): array
    {
        $candidates = [];

        $registrableDomain = $this->getRegistrableDomainCandidate();
        if ($registrableDomain !== null) {
            $candidates[] = $registrableDomain;
        }

        $labels = explode('.', $this->domain);

        for ($index = 0, $max = count($labels) - 2; $index <= $max; $index++) {
            $candidate = implode('.', array_slice($labels, $index));
            if (!in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    public function getTargetHostnames(string $zoneName): array
    {
        if ($this->domain === $zoneName) {
            return $this->getMatcher();
        }

        $zoneSuffix = '.' . $zoneName;
        if (!str_ends_with($this->domain, $zoneSuffix)) {
            throw new \InvalidArgumentException(sprintf('Domain %s is not part of managed zone %s', $this->domain, $zoneName));
        }

        $relativeHostname = substr($this->domain, 0, -strlen($zoneSuffix));
        if ($relativeHostname === false || $relativeHostname === '') {
            throw new \InvalidArgumentException(sprintf('Failed to determine relative hostname for %s', $this->domain));
        }

        return [$relativeHostname];
    }

    public function formatHostnameForZone(string $hostname, string $zoneName): string
    {
        return match ($hostname) {
            '@' => $zoneName,
            '*' => '*.' . $zoneName,
            default => $hostname . '.' . $zoneName,
        };
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function isForce(): bool
    {
        return $this->force;
    }

    private function getRegistrableDomainCandidate(): ?string
    {
        try {
            $resolvedDomain = self::getRules()->resolve(Domain::fromIDNA2008($this->domain));
            $registrableDomain = trim($resolvedDomain->registrableDomain()->toString(), '.');

            return $registrableDomain !== '' ? strtolower($registrableDomain) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function getRules(): Rules
    {
        if (self::$rules instanceof Rules) {
            return self::$rules;
        }

        self::$rules = Rules::fromPath(dirname(__DIR__) . '/resources/public_suffix_list.dat');

        return self::$rules;
    }
}