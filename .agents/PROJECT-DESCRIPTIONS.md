🤖 BACKEND: SEAPEDIA BACKEND (LARAVEL + SUPABASE)

Project Overview
You are an expert backend developer agent. Your task is to build and maintain the RESTful API for SEAPEDIA, a multi-role e-commerce marketplace platform. The backend is built using Laravel 11 (PHP 8.2+) and uses Supabase (PostgreSQL) as its primary database. Authentication and role management are handled via Spatie Laravel Permission.

## Technical Stack & Constraints
- Framework: Laravel 11 (API-only mode).

- Database: PostgreSQL via Supabase.

- Primary Keys: Must use UUID (gen_random_uuid()) for all major tables except the static roles table.

- Role Management: Spatie Laravel Permission (configured to support UUID for model_uuid).

- CORS: Enabled to accept requests from the Vercel frontend domain.

## Strict Core Business Rules for Backend
1. Multi-Role & Active Session: A user can have multiple non-admin roles (Buyer, Seller, Driver). However, authorization must be checked against an Active Role stored in the session/token/user record, NOT just the list of all roles they own. Create custom middleware to enforce this.

2. Single-Store Checkout: The carts table has a nullable store_id. When adding the first item, lock the carts.store_id to that product's store. Block any subsequent items from different stores unless the cart is cleared.

3. Order Lifecycle Tracking: Statuses must strictly transition through: Sedang Dikemas ➔ Menunggu Pengirim ➔ Sedang Dikirim ➔ Pesanan Selesai OR Dikembalikan. Every transition MUST log a row into order_status_histories.

4. Overdue & Simulation SLA: Implement a custom setting or mechanism to simulate time progression ("Next Day"). This should trigger a command/endpoint to auto-refund/auto-return expired orders according to their delivery method SLA (Instant, Next Day, Regular).

## Database Architecture Reference
Expect and follow this table schema hierarchy during generation:

- users (UUID primary key)

- roles & model_has_roles (Spatie standard tables, modified for UUID)

- stores (One-to-one relation with users via user_id unique constraint)

- products (Belongs to stores)

- buyer_wallets & wallet_transactions

- carts & cart_items

- discounts (Handles both Voucher and Promo logic)

- orders, order_items, & order_status_histories

- application_reviews

## Security Hardening Directives (Level 7)
- Always use Eloquent ORM or parameterized queries to prevent SQL Injection.

- Sanitasi string on inputs, especially for application_reviews.comment to prevent XSS.

- Enforce server-side ownership checks: A Seller cannot modify another Seller's product; a Buyer cannot view another Buyer's order details.