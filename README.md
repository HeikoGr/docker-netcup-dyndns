# Netcup DNS API DynDNS Docker Client

[![Container Build](https://github.com/heikogr/docker-netcup-dyndns/workflows/container/badge.svg?branch=main&event=push)](https://github.com/heikogr/docker-netcup-dyndns/actions/workflows/container.yml)
[![GHCR Image](https://img.shields.io/badge/image-ghcr.io%2Fheikogr%2Fdocker--netcup--dyndns-blue)](https://github.com/heikogr/docker-netcup-dyndns/pkgs/container/docker-netcup-dyndns)

## Credits
based on business logic from:
- https://github.com/fernwerker/ownDynDNS
- https://github.com/stecklars/dynamic-dns-netcup-api
- https://github.com/b2un0/docker-netcup-dyndns

This container uses the official [PHP image](https://hub.docker.com/_/php/) as a base image and tracks the `php:8.4-cli-alpine3.23` branch so it receives PHP and Alpine patch updates without a digest pin.

Domain parsing uses the Public Suffix List via `jeremykendall/php-domain-parser`, but the effective DNS zone is still verified against the netcup API before records are updated.

## pre requirements
* Create each host record in your netcup CCP before using the script. The script does not create any missing records!

## run as docker container

via `docker-compose.yml` on your NAS
````yaml
services:
    netcup-dyndns:
        image: ghcr.io/heikogr/docker-netcup-dyndns:latest
        restart: unless-stopped
        container_name: netcup-dyndns
        network_mode: host # usually required for IPv6 unless Docker itself is configured for dual-stack
        environment:
            SCHEDULE: "*/10 * * * *" # https://crontab.guru/
            DOMAIN: "nas.domain.tld"
            MODE: "both" # can be "@", "*" or "both"
            IPV4: "yes"
            IPV6: "yes"
            TTL: "300" # 0 or remove if zone ttl should not change
            CUSTOMER_ID: "<customerId>"
            API_KEY: "<apiKey>"
            API_PASSWORD: "<apiPassword>"
````

The GitHub Actions workflow publishes the container image to GHCR on pushes to `main`, tags, and supports pull request builds without pushing.

Pushes to the `dev` branch publish `ghcr.io/heikogr/docker-netcup-dyndns:dev`.

IPv6 can be enabled, but only if the host really has stable outbound IPv6 connectivity. On residential connections with rotating privacy addresses or changing delegated prefixes, enabling `IPV6=yes` can cause AAAA churn and intermittent reachability problems.

## run without docker

via `wrapper.php` (or some other script name)

Install dependencies once in `rootfs/app` before running it directly:

```bash
composer install --no-dev
```

```
<?php

$_ENV['DOMAIN'] = 'nas.domain.tld';
$_ENV['MODE'] = 'both';  # can be "@", "*" or "both"

$_ENV['CUSTOMER_ID'] = '<customerId>';
$_ENV['API_KEY'] = '<apiKey>';
$_ENV['API_PASSWORD'] = '<apiPassword>';

$_ENV['TTL'] = 300; # 0 or remove if zone ttl should not change

$_ENV['IPV4'] = 'yes';
$_ENV['IPV6'] = 'no';

$_ENV['FORCE'] = 'no';

require 'updater.php';
```

## References
* DNS API Documentation: https://ccp.netcup.net/run/webservice/servers/endpoint.php

## License
Published under GNU General Public License v3.0  

