# wallet-api

Fintech wallet backend. Handles wallets, transfers, bill payments, crypto deposits, and withdrawals.

## Project overview

This is a simplified wallet system that lets users:
1. Create wallets in different currencies (NGN, BTC, USDT, ETH)
2. Transfer money between wallets
3. Pay bills (electricity, airtime, etc)
4. Receive crypto deposits via webhook
5. Withdraw to bank accounts


## Architecture

**Tech stack**: Laravel 11, PostgreSQL, Redis

**Why postgres**: it's better because of the decimal precision than mysql.It's important when dealing with crypto amounts that can have 8 decimal places.

**Why redis**: The reason i used Redis is beacause of the job queue for async processing, plus because of caching for rate limits and distributed locks and also for session management and also some repeated fetch queries.

### How transfers work

1. Acquire redis lock on sender wallet (prevents parallel requests)
2. Start db transaction
3. Lock both wallets with SELECT FOR UPDATE (in sorted order to avoid deadlock)
4. Verify balance
5. Create transaction record
6. Create ledger entries (debit sender, credit recipient) 
7. Update cached balances
8. Commit and release locks

If anything fails, the whole thing rolls back.

### Background jobs

Bill payments and withdrawals don't happen instantly - they go to a queue and process async. The wallet gets debited immediately (so user can't double-spend), then the job handles the external call and updates the final status but this happens after the transaction record is created and the ledger entries are created, but it happens very fast.

Jobs retry 3 times with backoff. If they fail completely, withdrawals get refunded.

## Setup instructions

```bash
git clone https://github.com/OdugbesanOpeoluwa/wallet-api.git  
cd wallet-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate 
php artisan migrate --seed
php artisan passport:install
php artisan queue:work rabbitmq --tries=3 --timeout=30
php artisan schedule:run --interval=1
php artisan serve 
```

## Idempotency
Two layers:

Client-side: Send an X-Idempotency-Key header with a unique value. If the system has seen that key before, the system will return the original response instead of processing again.

Server-side: Every transaction gets a unique reference stored with a unique constraint. Even without the header, the system won't create duplicate records for the same logical operation.

This handles the case where the client's connection drops after the system process but before they get the response - they can retry safely.

### Trade-offs and assumptions
1. Cached balance vs computed: I store balance on the wallet table for fast reads, rather than computing from ledger entries every time. Trade-off is you need periodic reconciliation to catch drift. For this demo I went with cached.

2. Sync vs async: Webhook deposits process synchronously. At high volume you'd want to queue them, but for this scale it's fine and simpler to debug.

3. Single currency transfers: You can only transfer between wallets of the same currency. Cross-currency would need exchange rates and that's a whole other system.

4. Simulated providers: Bill payment and withdrawal processing is mocked. Just sleeps and returns success 90% of the time. Real integration would need provider SDKs, callbacks, etc.

5. No KYC: In reality you'd need identity verification before allowing certain transaction sizes. Skipped for scope.

6. NGN only for bills/withdrawals: Made sense to keep it simple since those are typically domestic.

### Production improvements

1. Read replicas: Separate read/write for high traffic.

2. Notifications: Email/SMS when transactions complete. Right now users have to poll.

3. Admin panel: Customer support needs visibility into user transactions, ability to reverse things, etc.

5. ML fraud detection: Current risk checks are rule-based. Real systems use ML models that learn patterns.
