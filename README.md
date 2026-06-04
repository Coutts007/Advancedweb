# Advancedweb

## E-Commerce Platform with Admin Dashboard

A full-featured e-commerce platform built with PHP and MySQL, featuring user authentication, product browsing, shopping cart, and **admin-only dashboard** for product management and sales monitoring.

### Features

#### Customer Features
- 🛍️ Browse products with detailed information
- 🛒 Shopping cart with quantity management
- 📦 Product availability tracking
- 💳 Checkout functionality
- 👤 User authentication and account management

#### Admin Features (Admin Dashboard)
- **Product Management**
  - ➕ Add new products with title, description, price, stock, and image URL
  - 🗑️ Delete products from the system
  - 📝 Update product stock levels in real-time
  - 📊 View product sales statistics and performance

- **Sales Monitoring**
  - 📈 Dashboard overview with key metrics (total orders, revenue, product count, low-stock items)
  - 📋 Recent orders list with customer names, totals, and dates
  - 🏆 Top-selling products analysis
  - 💰 Real-time revenue tracking

### Default Admin Credentials

For testing purposes, the following admin account is pre-configured:
- **Email**: `admin@acommerce.local`
- **Password**: `admin123`
- **Role**: Administrator

Customer test account:
- **Email**: `customer@acommerce.local`
- **Password**: `user123`
- **Role**: Regular customer

### Database Schema

#### Tables
- `users` — User accounts (with `is_admin` flag for role-based access control)
- `products` — Product catalog
- `orders` — Customer orders with totals
- `order_items` — Individual items in each order with pricing history

#### Security Features
- Bcrypt password hashing
- CSRF token protection on all forms
- Role-based access control (RBAC) for admin-only pages
- Prepared SQL statements to prevent injection
- Session fixation protection on login
- Input validation and sanitization

### Installation

1. **Setup Database**
   ```bash
   mysql -u root < schema.sql
   ```
   This will create the `acommerce` database with all tables and seed data.

2. **Verify Installation**
   - Navigate to `http://localhost/assignment/ecommerce/`
   - The application should load without errors

3. **Access Admin Dashboard**
   - Log in with admin credentials (`admin@acommerce.local` / `admin123`)
   - Click "Admin" in the navigation bar
   - You'll see the admin dashboard with statistics and management tools

### File Structure

```
ecommerce/
├── index.php                 # Main product listing page
├── login.php                 # Login page
├── login-handler.php         # Login processing
├── signup.php                # Registration page
├── signup-handler.php        # Registration processing
├── logout.php                # Logout handler
├── cart.php                  # Shopping cart page
├── admin-dashboard.php       # Admin dashboard (ADMIN ONLY)
├── admin-handler.php         # Admin action processor (ADMIN ONLY)
├── db.php                    # Database connection & helpers
├── navbar.php                # Navigation component
├── schema.sql                # Database schema
├── style.css                 # Application styling
└── README.md                 # This file
```

### API Endpoints

#### Admin Product Management
- **POST** `/admin-handler.php` with `action=add_product`
  - Parameters: `title`, `description`, `price`, `stock`, `image_url`, `csrf_token`
  - Creates a new product

- **POST** `/admin-handler.php` with `action=delete_product`
  - Parameters: `product_id`, `csrf_token`
  - Deletes a product (cascades to order history)

- **POST** `/admin-handler.php` with `action=update_stock`
  - Parameters: `product_id`, `stock`, `csrf_token`
  - Updates product stock level

### Helper Functions

#### Authentication
- `isLoggedIn()` — Check if user is logged in
- `isAdmin()` — Check if current user has admin role
- `requireLogin()` — Redirect to login if not authenticated
- `requireAdmin()` — Redirect to home if not admin

#### Session Management
- `setFlash($type, $message)` — Set one-time flash message
- `getFlash()` — Retrieve and clear flash message
- `redirect($url)` — Redirect and terminate

### Security Considerations

1. **Admin Access Control**: Only users with `is_admin = true` can access admin dashboard
2. **CSRF Protection**: All forms use CSRF tokens generated per-session
3. **SQL Injection Prevention**: All database queries use prepared statements
4. **Password Security**: User passwords are hashed with bcrypt (cost factor 10)
5. **Session Security**: Session fixation protection via `session_regenerate_id()` on login
6. **Input Validation**: All product data is validated before database insertion

### Customization

To add new admin features:
1. Add new functions to `admin-handler.php` with CSRF validation
2. Add corresponding UI to `admin-dashboard.php`
3. Add CSS styling to `style.css` using the `admin-*` class prefix

### Troubleshooting

- **Admin dashboard not loading**: Verify you're logged in as an admin user
- **Database errors**: Ensure `schema.sql` has been run and `acommerce` database exists
- **Login not working**: Check database connection in `db.php` and credentials in `schema.sql`
- **Flash messages not appearing**: Ensure JavaScript is enabled for auto-dismiss functionality

### License

Internal project for educational purposes.