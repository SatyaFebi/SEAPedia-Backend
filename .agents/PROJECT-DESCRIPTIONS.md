# ⚙️ SEAPEDIA - Backend (Laravel REST API)

## 📖 Overview
The backend of SEAPEDIA is a stateless RESTful API built with **Laravel 11**. It serves as the brain of the marketplace, strictly enforcing complex business logic, Role-Based Access Control (RBAC), transactional integrity, and automated background tasks for service level agreements (SLA).

## 🛠 Tech Stack
*   **Framework**: Laravel (REST API architecture)
*   **Authentication**: Laravel Sanctum (Stateful cookie-based auth for SPA)
*   **Database**: MySQL / PostgreSQL
*   **Validation**: Laravel Form Requests
*   **Security**: Eloquent ORM (SQL Injection prevention), API Resources

## 🏗️ Core Architecture & Business Logic

### 1. Dynamic RBAC & Active Role Enforcement
*   **Role Mapping**: A user can have multiple roles. The API strictly validates requests based on the user's *currently active role session*, not just their database capabilities.
*   **Policies & Gates**: Extensive use of Laravel Policies. For example, `ProductPolicy` ensures a Seller can only update/delete products belonging to their own `store_id`.

### 2. Transactional Integrity & Checkout Logic
*   **Single-Store Validation**: The checkout endpoint validates that all `product_ids` in the payload belong to the same `store_id`.
*   **Financial Engine**: Backend calculates Subtotal, applies Voucher/Promo rules (validating expiry and limits), adds Delivery Fees, and accurately calculates the **12% PPN**.
*   **DB Transactions**: Checkout uses `DB::transaction()` to ensure stock reduction, wallet deduction, and order creation happen atomically. If any step fails, the entire transaction rolls back.

### 3. Automated Overdue Handling (SLA)
*   **Time-based Commands**: Utilizes Laravel Task Scheduling / Console Commands to sweep for overdue orders (Instant, Next Day, Regular).
*   **Auto-Refund Mechanism**: When an order is overdue, the system safely reverses the transaction: restores the Buyer's wallet balance, increments Seller's product stock, and updates the order status to `Dikembalikan` (Returned) while preventing double-refunds.

## 🚀 Backend API Milestones (Level 1-7)

*   **Level 1: Auth & Role Infrastructure**
    *   `POST /api/login`, `POST /api/register`, `POST /api/role/active`.
    *   Protected route groups utilizing Sanctum middleware.
    *   Public endpoint for storing and fetching Application Reviews.
*   **Level 2: Seller & Product APIs**
    *   Store uniqueness validation in Form Requests.
    *   CRUD endpoints for Products under `api/seller/products`, strictly scoped to the authenticated Seller's store.
*   **Level 3: Wallet, Cart & Checkout APIs**
    *   Wallet balance endpoints and dummy top-up logic.
    *   `POST /api/checkout`: Complex transaction logic handling Single-Store constraint, balance checking, and initial status generation (`Sedang Dikemas`).
*   **Level 4: Discount Validation & Order State Machine**
    *   Voucher/Promo models with usage tracking.
    *   `PATCH /api/seller/orders/{id}/process`: Advances order status to `Menunggu Pengirim`.
    *   Transaction history and reporting queries.
*   **Level 5: Delivery & Driver APIs**
    *   `GET /api/driver/jobs`: Fetches only orders with status `Menunggu Pengirim`.
    *   Job claiming logic utilizing database locks (pessimistic locking) to prevent two Drivers from claiming the same order.
*   **Level 6: Admin & Overdue Automation**
    *   Admin statistical endpoints.
    *   `php artisan seapedia:process-overdue` command (or API trigger for demo purposes) to simulate Next Day and execute auto-refunds.
*   **Level 7: Security & Finalization**
    *   SQL Injection prevention via strict Eloquent usage.
    *   Input sanitization and comprehensive Form Requests.
    *   Swagger/OpenAPI documentation and complete database seeders for all user roles and dummy data.