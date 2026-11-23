# PHP to Spring Boot routes

All PHP operations were inventoried from `public/openapi.json` and the 15 API files. New routes use the same HTTP methods and JSON field names except the required checkout `submission_key` and `expected_total`. All mutations require CSRF, including anonymous authentication operations.

| PHP API | Method | Spring Boot |
|---|---|---|
| `/register_api.php` | POST | `/api/auth/register` |
| `/login_api.php` | POST | `/api/auth/login` |
| `/logout_api.php` | POST | `/api/auth/logout` |
| `/forgot_password_api.php` | POST | `/api/auth/forgot-password` |
| `/reset_password_api.php` | POST | `/api/auth/reset-password` |
| `/profile_api.php` | GET | `/api/account` |
| `/profile_api.php` | PATCH | `/api/account` |
| `/update_profile_api.php` | POST | `/api/account` |
| `/products_api.php` | GET | `/api/products` |
| `/cards_api.php` | GET | `/api/cards` |
| `/addresses_api.php` | GET | `/api/addresses` |
| `/cart_api.php` | GET | `/api/cart` |
| `/cart_api.php` | POST | `/api/cart` |
| `/cart_api.php` | PATCH | `/api/cart` |
| `/cart_api.php` | DELETE | `/api/cart` |
| `/checkout_api.php` | POST | `/api/checkout` |
| `/orders_api.php` | GET | `/api/orders` |
| `/orders_api.php` | PATCH | `/api/orders` |
| `/orders_api.php` | DELETE | `/api/orders` |
| `/order_tracking_api.php` | GET | `/api/tracking` |
| `/track_order_api.php` | POST | `/api/tracking` |

| PHP page | Thymeleaf route |
|---|---|
| `/public/profile.php` | `/shop` |
| `/public/cart.php` | `/cart` |
| `/public/checkout.php` | `/checkout` |
| `/public/orders.php` | `/orders` |
| `/public/update_profile.php` | `/account` |
| `/public/login.php` | `/login` |
| `/public/index.php` | `/register` |
| `/public/signed_out.php` | `/signed-out` |
| `/public/product.php` | `/products/detail` |
| `/public/forgot_password.php` | `/forgot-password` |
| `/public/reset_password.php` | `/reset-password` |

Public canonical product URLs use `/products/{id}`. `/products/detail?id={id}` is used by the retained catalog interaction code. Both render the same public Thymeleaf page. Product sharing exposes only the product identifier.
All 21 original operations retain distinct HTTP method/path mappings; profile updates use PATCH and POST `/api/account`. Two new GET operations provide CSRF and public product JSON. POST `/api/auth/logout` is handled by Spring Security.
