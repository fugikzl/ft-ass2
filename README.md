# Synthetic Payment Retry API

This educational PHP project simulates payment failures and retries. Slim serves the API, Doctrine stores payments and attempt logs in MySQL, and a Redis backed Symfony Messenger worker processes each requested attempt.

## Start from a fresh checkout

You need Docker with Compose and `make`. Port `8080` must be available. Local PHP and Composer are not required.

From the project directory, run:

```sh
make up
```

`make up` creates `.env` from `.env.example` if needed. Docker Compose reads that file and passes its settings to the containers. It then builds the PHP image and starts MySQL, Redis, PHP-FPM, the worker, and Nginx. PHP-FPM runs the database migration before it starts serving requests. The worker and Nginx wait for PHP-FPM to become healthy. The API is available at `http://localhost:8080`.

To rerun the idempotent migration manually:

```sh
make migrate
```

## Run the examples

Open `http://localhost:8080/scenarios.html` to send the 15 payments one at a time, inspect their attempts, and request retries. The **Clear all** button deletes payment records, attempt logs, and pending payment jobs so you can run the scenarios again.

```sh
make scenarios
```

`make scenarios` submits the assignment's 15 payments, requests retries after failed attempts, and prints the final statuses and attempt logs. Repeated runs reuse matching payment IDs.
## Call the API

Submit a payment, then retrieve its status. If the latest attempt failed, request a retry. The worker processes one attempt per queued job; at most two retries are accepted.

```sh
curl -X POST http://localhost:8080/api/payments \
  -H 'Content-Type: application/json' \
  -d '{"id":"DEMO001","amount":1200,"failureType":"Network"}'

curl http://localhost:8080/api/payments/DEMO001

curl -X POST http://localhost:8080/api/payments/DEMO001/retry
```

Supported failure types are `None`, `Network`, `Timeout`, and `Database`. The generated API specification is in `openapi.yaml`; regenerate it with `make openapi`.

## Useful commands

| Command | Purpose |
| --- | --- |
| `make mysql-shell` | Open the MySQL database shell |
| `make php-shell` | Open a shell in the PHP-FPM container |
| `make redis-shell` | Open Redis CLI |
| `make reset-db` | Delete payments and attempt logs, clear the payment queue, and recreate the schema |
| `make down` | Stop the services while keeping MySQL data |
