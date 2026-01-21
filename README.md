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
git clone <repo>    
cd wallet-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan passport:install
php artisan queue:work
php artisan schedule:run --interval=1
php artisan serve 
