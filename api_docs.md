# Rolinse Stores API Documentation (v1)

This document details the API endpoints available in version 1 of the Rolinse Stores backend.

## Base URL

The base URL for the API is typically `http://localhost:8000/api/v1` (or your deployed API URL).

## Authentication

All routes within the `auth` group require authentication via Sanctum tokens, except for registration and login endpoints.

### Auth Routes (`/v1/user`)

| Method | URI                | Controller & Method                 | Middleware | Description                                               |
| ------ | ------------------ | ----------------------------------- | ---------- | --------------------------------------------------------- |
| POST   | `/register`        | `AuthController@createAccount`      | `Optional` | Creates a new user account.                               |
| POST   | `/login`           | `AuthController@login`              | `Optional` | Logs in a user and returns an authentication token.       |
| POST   | `/social-auth`     | `AuthController@socialAuth`         | `Optional` | Handles authentication via social providers.              |
| POST   | `/forgot-password` | `AuthController@sendOtp`            | `Optional` | Sends an OTP to the user's email for password reset.      |
| POST   | `/resend-otp`      | `AuthController@sendOTP`            | `Optional` | Resends the OTP to the user's email.                      |
| POST   | `/verify-otp`      | `AuthController@verifyOtp`          | `Optional` | Verifies the OTP and logs in the user or resets password. |
| POST   | `/verify-id`       | `AuthController@verifyId`           | `Optional` | Verifies user ID.                                         |
| POST   | `/verify-username` | `AuthController@verifyUsername`     | `Optional` | Checks if a username is available.                        |
| POST   | `/verify-email`    | `AuthController@verifyEmail`        | `Optional` | Checks if an email is available.                          |
| POST   | `/verify-refcode`  | `AuthController@verifyReferralCode` | `Optional` | Verifies if a referral code exists.                       |

### Authenticated User Routes (`/v1/user`)

These routes require a valid Sanctum token.

| Method | URI                                | Controller & Method                       | Middleware     | Description                                   |
| ------ | ---------------------------------- | ----------------------------------------- | -------------- | --------------------------------------------- |
| POST   | `/logout`                          | `AuthController@logout`                   | `auth:sanctum` | Logs out the authenticated user.              |
| POST   | `/change-password`                 | `AuthController@changePassword`           | `auth:sanctum` | Allows the user to change their password.     |
| POST   | `/update-password`                 | `AuthController@updatePassword`           | `auth:sanctum` | Updates the user's password.                  |
| DELETE | `/close-account`                   | `AuthController@deleteAccount`            | `auth:sanctum` | Deletes the authenticated user's account.     |
| GET    | `/`                                | `UserController@getUser`                  | `auth:sanctum` | Retrieves the authenticated user's profile.   |
| POST   | `/edit-profile`                    | `UserController@editProfile`              | `auth:sanctum` | Updates the authenticated user's profile.     |
| GET    | `/get-notification`                | `UserController@getNotification`          | `auth:sanctum` | Retrieves user notifications.                 |
| GET    | `/change-notification-status/{id}` | `UserController@changeNotificationStatus` | `auth:sanctum` | Changes the status of a notification.         |
| POST   | `/shipping-address`                | `UserController@address`                  | `auth:sanctum` | Manages user shipping addresses.              |
| GET    | `/check-discount-promo`            | `UserController@checkDiscountCode`        | `auth:sanctum` | Checks the validity of a discount promo code. |

### User Dashboard Routes (`/v1/user`)

| Method | URI                            | Controller & Method                       | Middleware     | Description                                 |
| ------ | ------------------------------ | ----------------------------------------- | -------------- | ------------------------------------------- |
| GET    | `/get-receiver/{levelId}`      | `PaymentController@getReceiverAcct`       | `auth:sanctum` | Gets receiver account details for payments. |
| GET    | `/initiate`                    | `PaymentController@initiateTransaction`   | `auth:sanctum` | Initiates a financial transaction.          |
| GET    | `/confirm-payment`             | `PaymentController@confirmPayment`        | `auth:sanctum` | Confirms a payment.                         |
| GET    | `/get-payment-history/{type?}` | `PaymentController@getTransactionHistory` | `auth:sanctum` | Retrieves the user's payment history.       |

## Product Routes (`/v1/user/products`)

These routes are accessible with the `Optional` middleware, meaning they can be accessed by both authenticated and unauthenticated users.

| Method | URI                   | Controller & Method                      | Middleware | Description                                                            |
| ------ | --------------------- | ---------------------------------------- | ---------- | ---------------------------------------------------------------------- |
| GET    | `/`                   | `ProductController@getAllProducts`       | `Optional` | Retrieves all products with optional filters.                          |
| GET    | `/get-products/{id?}` | `ProductController@getProduct`           | `Optional` | Retrieves a specific product by ID or all products if ID is omitted.   |
| GET    | `/get-types`          | `ProductController@getTypes`             | `Optional` | Retrieves available product types (brands, categories, sizes, colors). |
| POST   | `/confirm-price`      | `ProductController@confirmPrice`         | `Optional` | Confirms the price of selected products, including delivery costs.     |
| GET    | `/filter`             | `ProductController@index`                | `Optional` | Filters products based on various criteria.                            |
| GET    | `/category-shop`      | `ProductController@getProductByCategory` | `Optional` | Retrieves products filtered by category.                               |
| GET    | `/best-seller`        | `ProductController@bestSeller`           | `Optional` | Retrieves the list of best-selling products.                           |
| GET    | `handleImages`        | `DealsController@handleImages`           | `Optional` | Handles product images for deals.                                      |

## Order Routes (`/v1/user/orders`)

| Method | URI        | Controller & Method               | Middleware | Description                                             |
| ------ | ---------- | --------------------------------- | ---------- | ------------------------------------------------------- |
| POST   | `/`        | `OrderController@placeOrder`      | `Optional` | Places a new order.                                     |
| GET    | `/history` | `OrderController@getOrderHistory` | `Optional` | Retrieves the order history for the authenticated user. |

## Payment Routes (`/v1/user/payment`)

### Stripe (`/v1/user/payment/stripe`)

| Method | URI        | Controller & Method                | Middleware | Description                      |
| ------ | ---------- | ---------------------------------- | ---------- | -------------------------------- |
| POST   | `/pay`     | `PaymentController@pay`            | `Optional` | Initiates a Stripe payment.      |
| GET    | `/confirm` | `PaymentController@confirmPayment` | `Optional` | Confirms a Stripe payment.       |
| POST   | `/webhook` | `PaymentController@webhook`        | `Optional` | Handles Stripe payment webhooks. |

### PayPal (`/v1/user/payment/paypal`)

| Method | URI         | Controller & Method                       | Middleware | Description                 |
| ------ | ----------- | ----------------------------------------- | ---------- | --------------------------- |
| POST   | `/initiate` | `PaymentController@initiatePaypalPayment` | `Optional` | Initiates a PayPal payment. |
| POST   | `/verify`   | `PaymentController@verifyPaypalPayment`   | `Optional` | Verifies a PayPal payment.  |

### Paystack (`/v1/user/payment/paystack`)

| Method | URI         | Controller & Method                 | Middleware | Description                   |
| ------ | ----------- | ----------------------------------- | ---------- | ----------------------------- |
| POST   | `/initiate` | `PaymentController@initiatePayment` | `Optional` | Initiates a Paystack payment. |
| GET    | `/verify`   | `PaymentController@verifyPayment`   | `Optional` | Verifies a Paystack payment.  |

### Flutterwave (`/v1/user/payment/flutterwave`)

| Method | URI         | Controller & Method              | Middleware | Description                           |
| ------ | ----------- | -------------------------------- | ---------- | ------------------------------------- |
| POST   | `/initiate` | `PaymentController@startPayment` | `Optional` | Initiates a Flutterwave payment.      |
| GET    | `/verify`   | `PaymentController@checkPayment` | `Optional` | Verifies a Flutterwave payment.       |
| POST   | `/webhook`  | `PaymentController@webhook`      | `Optional` | Handles Flutterwave payment webhooks. |

## Deals Routes (`/v1/user/deals`)

| Method | URI                         | Controller & Method                      | Middleware | Description                                                |
| ------ | --------------------------- | ---------------------------------------- | ---------- | ---------------------------------------------------------- |
| GET    | `/types`                    | `DealsController@getDealTypes`           | `Optional` | Retrieves available deal types.                            |
| POST   | `/add-product`              | `DealsController@addProductDeal`         | `Optional` | Adds a product to a deal.                                  |
| DELETE | `/clear-product`            | `DealsController@clearProductDeal`       | `Optional` | Removes a product from a deal.                             |
| GET    | `/get-offers/{dealType?}`   | `DealsController@getSpecialDeals`        | `Optional` | Retrieves special deals, optionally filtered by deal type. |
| POST   | `/create-deal-type/{slug?}` | `DealsController@createOrUpdateDealType` | `Optional` | Creates or updates a deal type.                            |
| DELETE | `/delete-deal-type/{id?}`   | `DealsController@deleteDealTypes`        | `Optional` | Deletes a deal type.                                       |

## Admin Routes (`/v1/admin`)

These routes are protected by the `Admin` middleware, requiring administrator privileges.

### Product Management (`/v1/admin/products`)

| Method | URI             | Controller & Method                        | Middleware | Description                                               |
| ------ | --------------- | ------------------------------------------ | ---------- | --------------------------------------------------------- |
| POST   | `/`             | `ProductController@store`                  | `Admin`    | Creates a new product.                                    |
| PUT    | `/{id}`         | `ProductController@update`                 | `Admin`    | Updates an existing product.                              |
| DELETE | `/{id}`         | `ProductController@destroy`                | `Admin`    | Deletes a product.                                        |
| GET    | `/distribution` | `ProductController@getProductDistribution` | `Admin`    | Retrieves product distribution data by category or brand. |

### Order Management (`/v1/admin/orders`)

| Method | URI                        | Controller & Method                    | Middleware | Description                                                   |
| ------ | -------------------------- | -------------------------------------- | ---------- | ------------------------------------------------------------- |
| GET    | `/distribution`            | `OrderController@getOrderDistribution` | `Admin`    | Retrieves order distribution data (e.g., by category, brand). |
| GET    | `/list`                    | `OrderController@getAllOrders`         | `Admin`    | Retrieves a list of all orders with filtering options.        |
| PUT    | `/update-status/{orderId}` | `OrderController@updateOrderStatus`    | `Admin`    | Updates the status of a specific order.                       |

### Customer Management (`/v1/admin/customer`)

| Method | URI                         | Controller & Method                       | Middleware | Description                                           |
| ------ | --------------------------- | ----------------------------------------- | ---------- | ----------------------------------------------------- |
| GET    | `/activities/{id?}`         | `AdminController@getUserActivities`       | `Admin`    | Retrieves user activity logs.                         |
| GET    | `/metadata`                 | `AdminController@getUserMetadata`         | `Admin`    | Retrieves user metadata.                              |
| GET    | `/{id?}`                    | `AdminController@getUsers`                | `Admin`    | Retrieves a list of users, optionally filtered by ID. |
| DELETE | `/delete/{id}`              | `AdminController@deleteUser`              | `Admin`    | Deletes a user account.                               |
| POST   | `/restore/{id}`             | `AdminController@restoreUser`             | `Admin`    | Restores a deleted user account.                      |
| POST   | `/suspend/{id}`             | `AdminController@suspendUser`             | `Admin`    | Suspends a user account.                              |
| GET    | `/analytics-month-chart`    | `AdminController@revenueAnalyticsChart`   | `Admin`    | Retrieves monthly revenue analytics.                  |
| GET    | `/payment/get-transactions` | `AdminController@getTransactions`         | `Admin`    | Retrieves payment transactions for admin.             |
| GET    | `/payment/change-status`    | `AdminController@changeTransactionStatus` | `Admin`    | Changes the status of a transaction.                  |

### Promo Management (`/v1/admin/promo`)

| Method | URI      | Controller & Method                | Middleware | Description                                    |
| ------ | -------- | ---------------------------------- | ---------- | ---------------------------------------------- |
| POST   | `/`      | `AdminController@createAdminPromo` | `Admin`    | Creates a new admin promo code.                |
| POST   | `/{id}`  | `AdminController@updateAdminPromo` | `Admin`    | Updates an existing admin promo code.          |
| GET    | `/{id?}` | `AdminController@getAdminPromo`    | `Admin`    | Retrieves admin promo codes, optionally by ID. |
| DELETE | `/{id}`  | `AdminController@deleteAdminPromo` | `Admin`    | Deletes an admin promo code.                   |

## Delivery Routes (`/v1/delivery`)

These routes interact with the delivery service.

| Method | URI                       | Controller & Method                        | Middleware | Description                                     |
| ------ | ------------------------- | ------------------------------------------ | ---------- | ----------------------------------------------- |
| POST   | `/authenticate`           | `DeliveryController@authenticate`          | -          | Authenticates with the delivery service.        |
| POST   | `/calculate-fee`          | `DeliveryController@calculateDeliveryFee`  | -          | Calculates delivery fees.                       |
| POST   | `/create-order`           | `DeliveryController@createDeliveryOrder`   | -          | Creates a delivery order.                       |
| GET    | `/order-status/{orderId}` | `DeliveryController@getOrderStatus`        | -          | Retrieves the status of a delivery order.       |
| POST   | `/calculate-cost`         | `DeliveryController@calculateDeliveryCost` | -          | Calculates delivery costs.                      |
| GET    | `/order/{order_id}`       | `DeliveryController@getDeliveryOrder`      | -          | Retrieves details of a specific delivery order. |
| POST   | `/search`                 | `DeliveryController@searchOrders`          | -          | Searches for delivery orders.                   |
| GET    | `/track/{orderNumber}`    | `DeliveryController@trackOrder`            | -          | Tracks a delivery order.                        |
| PUT    | `/update-order`           | `DeliveryController@updateDeliveryOrder`   | -          | Updates a delivery order.                       |
| POST   | `/calculate-time`         | `DeliveryController@calculateDeliveryTime` | -          | Calculates estimated delivery time.             |
| GET    | `/export-locations`       | `DeliveryController@getExportLocations`    | -          | Retrieves available export locations.           |
| POST   | `/export-cost`            | `DeliveryController@calculateExportCost`   | -          | Calculates export delivery costs.               |
| POST   | `/create-export-order`    | `DeliveryController@createExportOrder`     | -          | Creates an export delivery order.               |
| POST   | `/webhook`                | `DeliveryController@handleWebhook`         | -          | Handles webhooks from the delivery service.     |

## Finance Routes (`/v1/user/finance`)

| Method | URI             | Controller & Method                | Middleware | Description                                   |
| ------ | --------------- | ---------------------------------- | ---------- | --------------------------------------------- |
| GET    | `/all-currency` | `FinanceController@getAllCurrency` | `Optional` | Retrieves a list of all supported currencies. |
