# Rolinse Stores Backend Architecture

This document outlines the architecture of the Rolinse Stores backend.

## 1. Overview

The backend is built using the Laravel framework, following a Model-View-Controller (MVC) pattern. It exposes a RESTful API for the frontend application to interact with. The API is versioned, with the current version being v1.

## 2. Core Components

### 2.1. Controllers

Controllers handle incoming HTTP requests, interact with models and services to perform business logic, and return responses. They are organized by feature within the `app/Http/Controllers/API/V1` directory.

### 2.2. Models

Models represent the application's data structures and interact with the database. They are defined in the `app/Models` directory. Key models include:

-   `User`: Represents users of the system, including authentication details, roles, and personal information.
-   `Product`: Represents products available for sale.
-   `Order`: Represents customer orders.
-   `OrderItem`: Represents individual items within an order.
-   `Delivery`: Stores delivery-related information.
-   `Transaction`: Records financial transactions.
-   `Brand`, `Category`, `Attribute`: Represent product-related entities.
-   `AdminPromo`: Manages promotional codes.

### 2.3. Services

Services encapsulate business logic that is shared across multiple controllers or complex operations. They are located in the `app/Services` directory. Examples include:

-   `GeneralService`: Handles general utilities like currency conversion and media uploads.
-   `NotificationService`: Manages sending notifications to users.
-   `DeliveryService`: Integrates with external delivery services.
-   `ActivityLogger`: Records user actions.
-   `StripeService`: Handles Stripe payment gateway interactions.

### 2.4. Middleware

Middleware is used to filter HTTP requests. Key middleware includes:

-   `auth:sanctum`: For API authentication.
-   `Admin`: Restricts access to admin-only routes.
-   `Optional`: Allows certain routes to be accessed without authentication.

### 2.5. Routes

API routes are defined in `routes/api.php`, organized into versioned groups and further categorized by functionality (e.g., auth, user, admin, delivery).

## 3. Key Features and Integrations

### 3.1. Authentication

-   User registration, login, social authentication, OTP verification, password management.
-   JWT (Sanctum) for API authentication.
-   Referral system for user acquisition.

### 3.2. Product Management

-   CRUD operations for products, including images, attributes, categories, and brands.
-   Features for best-sellers, special deals, and product filtering.

### 3.3. Order Processing

-   Placing orders, including product validation, price calculation, and delivery details.
-   Order history retrieval for users.
-   Admin functionalities for viewing and managing all orders.

### 3.4. Delivery Logistics

-   Integration with external delivery services (e.g., Fez Delivery) for creating, tracking, and managing delivery orders.
-   Calculation of delivery costs and times for both local and international shipments.
-   Webhook handling for delivery status updates.

### 3.5. Payment Gateway Integrations

-   Supports multiple payment gateways: Stripe, PayPal, Paystack, Flutterwave.
-   Handles payment initiation, confirmation, webhooks, and transaction history.

### 3.6. Admin Functionalities

-   User management (viewing, deleting, suspending, restoring users).
-   Activity log viewing.
-   Promo code management.
-   Analytics for revenue and user activity.

### 3.7. Notifications

-   User notifications for various events (orders, referrals, status updates).
-   Email and potentially push notifications.

## 4. Database Schema (High-Level)

The database schema is defined by migrations in the `database/migrations` directory. Key tables include:

-   `users`
-   `products`
-   `categories`
-   `brands`
-   `attributes`
-   `orders`
-   `order_items`
-   `transactions`
-   `deliveries`
-   `admin_promos`
-   `user_roles`
-   `activity_logs`
-   `shipping_addresses`

## 5. External Services

-   **Payment Gateways:** Stripe, PayPal, Paystack, Flutterwave.
-   **Delivery Services:** Fez Delivery (and potentially others through `DeliveryService`).
-   **Cloudinary:** For media storage and management.
