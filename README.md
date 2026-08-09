# Simple Swiss Public Transport UI

## Purpose

Switzerland's important public transport websites do a lot of things, selling tickets and much more. If all you need to do is to look up a connection and have an easy way to share it with somebody you're mostly on your own. trnsprt.ch solves this issue, leveraging the https://transport.opendata.ch API.

## Installation

PHP 8.4 or newer is required.

* `git clone https://github.com/fabian/trnsprt.ch.git`
* `cd trnsprt.ch; composer install`
* Create a VirtualHost serving up `./trnsprt.ch/web`
* Done.

## Run tests

```
docker compose run --rm web vendor/bin/phpunit
```
