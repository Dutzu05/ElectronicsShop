# ElectronicShop

## Overview

ElectronicShop is a PHP + SQL Server web application for a small electronics store. It supports:

- user registration and login
- product browsing and search
- shopping cart management
- checkout with PIN confirmation
- order history for customers
- admin management for products, categories, stock, and orders
- database inspection through an admin-only overview page

The project uses `PDO` with the `sqlsrv` driver and stores its data in the `ElectronicsShop` SQL Server database on `localhost\SQLEXPRESS`.

---

## Business Structure

### Business idea

The application models a small online electronics retailer. Customers create accounts, browse products, add items to the cart, and place orders. An administrator manages the catalog, stock, and order approval flow.

### Main business entities

- `Customers`
  Stores the personal data of customers such as name, email, phone, and city.
- `Users`
  Stores login credentials, application role (`user` or `admin`), and the hashed payment PIN.
- `Categories`
  Groups products into business areas such as laptops, phones, monitors, and accessories.
- `Products`
  Stores the sellable items, their category, price, and available stock.
- `Orders`
  Represents a customer purchase request, with status such as `pending`, `accepted`, or `refused`.
- `OrderItems`
  Stores the individual products and quantities that belong to one order.
- `AdminMessages`
  Stores activity information visible to the administrator, for example when a user places an order.

### Business flow

1. A visitor creates an account in `register.php`.
2. The user logs in through `login.php`.
3. The user browses products in `shop.php` and can search by product or category.
4. The user adds products to the cart in `cart.php`.
5. The user reviews the order in `checkout.php`.
6. The order is confirmed in `place_order.php` with a 4-digit payment PIN.
7. The order becomes `pending`.
8. The admin checks the dashboard in `admin.php`.
9. The admin accepts or refuses the order through `admin_action.php`.
10. The user sees the result in `messages.php`.

### Business rules implemented

- a user cannot buy more items than the available stock
- stock decreases only after a successful order placement
- an order must be confirmed with the user payment PIN
- a category cannot be deleted if it still has products
- a product cannot be deleted if it already exists in past orders
- product stock cannot go below zero
- only admins can access admin pages

---

## Project Structure

### Core backend files

- [db.php](/C:/Projects/ElectronicShop/db.php)
  Creates the shared PDO connection to SQL Server.
- [auth.php](/C:/Projects/ElectronicShop/auth.php)
  Starts sessions, escapes output with `h()`, loads the current user, and protects routes with `require_login()` and `require_admin()`.
- [product_assets.php](/C:/Projects/ElectronicShop/product_assets.php)
  Maps product names and categories to local SVG images.

### Authentication and account files

- [login.php](/C:/Projects/ElectronicShop/login.php)
  Authenticates existing users and redirects them to the correct area.
- [register.php](/C:/Projects/ElectronicShop/register.php)
  Creates a new customer account and the linked user credentials.
- [logout.php](/C:/Projects/ElectronicShop/logout.php)
  Destroys the session and returns the user to the login page.

### Customer area files

- [shop.php](/C:/Projects/ElectronicShop/shop.php)
  Displays the catalog, supports search, shows alternative products, and adds products to the cart.
- [cart.php](/C:/Projects/ElectronicShop/cart.php)
  Displays cart items, total price, category average comparisons, and alternative products.
- [checkout.php](/C:/Projects/ElectronicShop/checkout.php)
  Shows the final order summary and asks for the payment PIN.
- [place_order.php](/C:/Projects/ElectronicShop/place_order.php)
  Creates the order and order items, updates stock, and writes an admin message.
- [messages.php](/C:/Projects/ElectronicShop/messages.php)
  Shows the customer order history, totals, statuses, and ordered items.

### Admin area files

- [admin.php](/C:/Projects/ElectronicShop/admin.php)
  Main dashboard for order review, statistics, top 3 items, stock management, category management, and product search.
- [admin_action.php](/C:/Projects/ElectronicShop/admin_action.php)
  Handles admin form actions such as adding or deleting categories/products, stock changes, and accepting or refusing orders.
- [database_overview.php](/C:/Projects/ElectronicShop/database_overview.php)
  Shows the content of the main database tables for inspection.

### Setup and utility files

- [setup_data.php](/C:/Projects/ElectronicShop/setup_data.php)
  Inserts the initial demo data if the database is empty.
- [test_sqlserver.php](/C:/Projects/ElectronicShop/test_sqlserver.php)
  Simple connectivity test for SQL Server.

### Assets

- [assets/style.css](/C:/Projects/ElectronicShop/assets/style.css)
  Contains all page styling.
- [assets/products](/C:/Projects/ElectronicShop/assets/products)
  Contains local SVG product illustrations and category fallback images.

### Driver and support files

- `pdo_sqlsrv_5_13_1/`, `sqlsrv_5_13_1/`
  SQL Server PHP driver files.
- `php_pdo_sqlsrv-...zip`, `php_sqlsrv-...zip`
  Archived driver packages.
- `cookies.txt`, `user-cookies.txt`, `admin-cookies.txt`
  Cookie support/test artifacts.
- `.idea/`
  IDE configuration files.

---

## How the PHP Files Work Together

### Entry and authentication

- The app starts from `login.php`.
- `login.php` and `register.php` both include `auth.php`.
- `auth.php` uses `db.php` to load users from the database and manage the session.

### Customer flow

- `shop.php` lets a customer search and add items to the session cart.
- `cart.php` reads the session cart and displays pricing analysis.
- `checkout.php` loads the selected product rows again from the database for confirmation.
- `place_order.php` writes the real order data into `Orders` and `OrderItems`.
- `messages.php` reads the user order history and displays statuses and totals.

### Admin flow

- `admin.php` loads reporting queries and management lists.
- Admin forms submit to `admin_action.php`.
- `admin_action.php` performs the insert, update, and delete logic, then redirects back to `admin.php`.
- `database_overview.php` is a direct table browser for admins.

---

## SQL Query Catalog

This section lists the queries used in the project and explains the purpose of each one.

### 1. Authentication queries

#### Current user lookup

```sql
SELECT UserID, CustomerID, Email, PasswordHash, Role, PaymentPinHash
FROM Users
WHERE UserID = ?
```

Used in `auth.php`.

Purpose:
- loads the logged-in user from the session
- checks if the session still points to a valid account
- decides whether the user is admin or normal user

#### Login by email

```sql
SELECT UserID, CustomerID, Email, PasswordHash, Role, PaymentPinHash
FROM Users
WHERE Email = ?
```

Used in `login.php`.

Purpose:
- finds the user account during login
- loads the password hash and role for authentication and redirection

---

### 2. Registration queries

#### Check if email already exists

```sql
SELECT COUNT(*) AS total
FROM Users
WHERE Email = ?
```

Used in `register.php`.

Purpose:
- prevents duplicate accounts for the same email

#### Insert customer profile

```sql
INSERT INTO Customers (FirstName, LastName, Email, Phone, City)
VALUES (?, ?, ?, ?, ?)
```

Used in `register.php` and `setup_data.php`.

Purpose:
- creates the customer identity record

#### Insert user credentials

```sql
INSERT INTO Users (CustomerID, Email, PasswordHash, Role, PaymentPinHash)
VALUES (?, ?, ?, 'user', ?)
```

Used in `register.php`.

Purpose:
- creates the login account linked to the customer profile

---

### 3. Shop queries

#### Check one product before adding to cart

```sql
SELECT *
FROM Products
WHERE ProductID = ?
```

Used in `shop.php`.

Purpose:
- checks if the selected product exists
- verifies available stock before increasing the session cart quantity

#### Product catalog with search and self join

```sql
SELECT
    p.ProductID,
    p.ProductName,
    p.Price,
    p.StockQuantity,
    c.CategoryName,
    COALESCE(MIN(alt.ProductName), 'No similar product in this category') AS SimilarProductName
FROM Products p
JOIN Categories c ON p.CategoryID = c.CategoryID
LEFT JOIN Products alt
    ON alt.CategoryID = p.CategoryID
   AND alt.ProductID <> p.ProductID
WHERE p.ProductName LIKE ?
   OR c.CategoryName LIKE ?
GROUP BY
    p.ProductID,
    p.ProductName,
    p.Price,
    p.StockQuantity,
    c.CategoryName
ORDER BY c.CategoryName, p.ProductName
```

Used in `shop.php` when search is active. Without search, the `WHERE` part is omitted.

Purpose:
- shows all products with category names
- supports catalog search
- uses `SELF JOIN` on `Products` to find an alternative product from the same category
- uses `COALESCE` to handle missing alternatives
- uses `GROUP BY` and `MIN` to reduce alternative choices to one display value

---

### 4. Cart and checkout queries

#### Load products currently in cart

```sql
SELECT p.*, c.CategoryName
FROM Products p
JOIN Categories c ON c.CategoryID = p.CategoryID
WHERE p.ProductID IN (...)
```

Used in `cart.php`.

Purpose:
- loads the products that exist in the session cart
- provides category information for display and image fallback logic

#### Compare cart products against category average

```sql
SELECT
    p.ProductID,
    p.ProductName,
    c.CategoryName,
    p.Price,
    (
        SELECT AVG(p2.Price)
        FROM Products p2
        WHERE p2.CategoryID = p.CategoryID
    ) AS CategoryAveragePrice,
    COALESCE(MIN(alt.ProductName), 'No alternative in category') AS AlternativeProduct
FROM Products p
JOIN Categories c ON c.CategoryID = p.CategoryID
LEFT JOIN Products alt
    ON alt.CategoryID = p.CategoryID
   AND alt.ProductID <> p.ProductID
WHERE p.ProductID IN (...)
GROUP BY p.ProductID, p.ProductName, c.CategoryName, p.Price, p.CategoryID
ORDER BY c.CategoryName, p.ProductName
```

Used in `cart.php`.

Purpose:
- compares the selected cart items with the average price of their category
- demonstrates `SELECT within SELECT` with `AVG`
- demonstrates `SELF JOIN`
- demonstrates `COALESCE`, `GROUP BY`, and `ORDER BY`

#### Load checkout summary products

```sql
SELECT *
FROM Products
WHERE ProductID IN (...)
```

Used in `checkout.php`.

Purpose:
- loads the products that the user is about to confirm in the final order

---

### 5. Order placement queries

#### Create the order header

```sql
INSERT INTO Orders (CustomerID, Status)
VALUES (?, 'pending')
```

Used in `place_order.php`.

Purpose:
- creates the parent order record before inserting line items

#### Lock a product row before stock update

```sql
SELECT *
FROM Products WITH (UPDLOCK, ROWLOCK)
WHERE ProductID = ?
```

Used in `place_order.php`.

Purpose:
- locks the selected product row during checkout
- reduces the risk of stock conflicts when multiple orders are placed

#### Insert order items

```sql
INSERT INTO OrderItems (OrderID, ProductID, Quantity, UnitPrice)
VALUES (?, ?, ?, ?)
```

Used in `place_order.php`.

Purpose:
- creates the detailed rows for each ordered product

#### Decrease stock after purchase

```sql
UPDATE Products
SET StockQuantity = StockQuantity - ?
WHERE ProductID = ?
```

Used in `place_order.php`.

Purpose:
- reduces stock according to the ordered quantity

#### Write admin activity message

```sql
INSERT INTO AdminMessages (UserID, MessageText)
VALUES (?, ?)
```

Used in `place_order.php`.

Purpose:
- creates an admin-visible message whenever a user places an order

---

### 6. User order history queries

#### Load user orders with subqueries

```sql
SELECT
    o.*,
    (
        SELECT COUNT(*)
        FROM OrderItems oi
        WHERE oi.OrderID = o.OrderID
    ) AS ItemLines,
    (
        SELECT SUM(oi.Quantity * oi.UnitPrice)
        FROM OrderItems oi
        WHERE oi.OrderID = o.OrderID
    ) AS OrderTotal
FROM Orders o
WHERE o.CustomerID = ?
ORDER BY OrderDate DESC
```

Used in `messages.php`.

Purpose:
- loads all orders of the logged-in user
- counts how many product lines each order has
- calculates the order total
- demonstrates `SELECT within SELECT`, `COUNT`, `SUM`, and `ORDER BY`

#### Load the products of a specific order

```sql
SELECT
    oi.OrderID,
    p.ProductName,
    c.CategoryName,
    oi.Quantity,
    oi.UnitPrice
FROM OrderItems oi
JOIN Products p ON p.ProductID = oi.ProductID
JOIN Categories c ON c.CategoryID = p.CategoryID
WHERE oi.OrderID = ?
ORDER BY p.ProductName
```

Used in `messages.php`.

Purpose:
- loads the detailed products belonging to a user order

---

### 7. Admin dashboard queries

#### Activity messages with user email

```sql
SELECT m.*, u.Email
FROM AdminMessages m
JOIN Users u ON m.UserID = u.UserID
ORDER BY m.CreatedAt DESC
```

Used in `admin.php`.

Purpose:
- shows the activity stream for the administrator

#### Orders with customer identity

```sql
SELECT o.*, c.FirstName, c.LastName, c.Email
FROM Orders o
JOIN Customers c ON o.CustomerID = c.CustomerID
ORDER BY o.OrderDate DESC
```

Used in `admin.php`.

Purpose:
- loads orders together with the customer who placed them

#### Customer spending summary

```sql
SELECT
    c.CustomerID,
    c.FirstName,
    c.LastName,
    c.Email,
    COUNT(DISTINCT o.OrderID) AS TotalOrders,
    COALESCE(SUM(oi.Quantity * oi.UnitPrice), 0) AS TotalSpent,
    COALESCE(AVG(oi.UnitPrice), 0) AS AverageItemPrice
FROM Customers c
LEFT JOIN Orders o ON o.CustomerID = c.CustomerID
LEFT JOIN OrderItems oi ON oi.OrderID = o.OrderID
GROUP BY c.CustomerID, c.FirstName, c.LastName, c.Email
ORDER BY TotalSpent DESC, c.LastName, c.FirstName
```

Used in `admin.php`.

Purpose:
- calculates customer business value
- demonstrates `LEFT JOIN`, `COUNT`, `SUM`, `AVG`, `COALESCE`, `GROUP BY`, and `ORDER BY`

#### Top 3 most bought items

```sql
SELECT TOP 3
    p.ProductName,
    c.CategoryName,
    SUM(oi.Quantity) AS TotalUnitsSold,
    COUNT(DISTINCT oi.OrderID) AS TimesOrdered
FROM OrderItems oi
JOIN Products p ON p.ProductID = oi.ProductID
JOIN Categories c ON c.CategoryID = p.CategoryID
GROUP BY p.ProductName, c.CategoryName
ORDER BY TotalUnitsSold DESC, TimesOrdered DESC, p.ProductName
```

Used in `admin.php`.

Purpose:
- lists the three best-selling items
- demonstrates the `TOP` requirement

#### Category summary

```sql
SELECT
    c.CategoryID,
    c.CategoryName,
    c.Description,
    COUNT(p.ProductID) AS ProductCount
FROM Categories c
LEFT JOIN Products p ON p.CategoryID = c.CategoryID
GROUP BY c.CategoryID, c.CategoryName, c.Description
ORDER BY c.CategoryName
```

Used in `admin.php`.

Purpose:
- shows how many products belong to each category

#### Admin product search

```sql
SELECT
    p.ProductID,
    p.ProductName,
    p.Price,
    p.StockQuantity,
    c.CategoryName
FROM Products p
JOIN Categories c ON c.CategoryID = p.CategoryID
WHERE p.ProductName LIKE ?
   OR c.CategoryName LIKE ?
ORDER BY c.CategoryName, p.ProductName
```

Used in `admin.php` when the search is active. Without search, the `WHERE` part is omitted.

Purpose:
- filters products so the admin can find items to edit

#### NULL analysis query

```sql
SELECT
    u.Email,
    u.Role,
    COALESCE(c.FirstName + ' ' + c.LastName, 'No linked customer') AS CustomerName,
    COALESCE(c.Phone, 'Phone not provided') AS PhoneNumber
FROM Users u
LEFT JOIN Customers c ON c.CustomerID = u.CustomerID
WHERE u.CustomerID IS NULL OR c.Phone IS NULL
ORDER BY u.Role DESC, u.Email
```

Used in `admin.php`.

Purpose:
- highlights rows that contain missing optional data
- demonstrates the `NULL` requirement with `IS NULL` and `COALESCE`

#### Load item details for one admin order

```sql
SELECT
    oi.OrderID,
    p.ProductName,
    c.CategoryName,
    oi.Quantity,
    oi.UnitPrice,
    oi.Quantity * oi.UnitPrice AS LineTotal
FROM OrderItems oi
JOIN Products p ON p.ProductID = oi.ProductID
JOIN Categories c ON c.CategoryID = p.CategoryID
WHERE oi.OrderID = ?
ORDER BY p.ProductName
```

Used in `admin.php`.

Purpose:
- shows the products that belong to each admin-visible order

---

### 8. Admin action queries

#### Insert category

```sql
INSERT INTO Categories (CategoryName, Description)
VALUES (?, ?)
```

Used in `admin_action.php`.

Purpose:
- creates a new product category

#### Check if a category still contains products

```sql
SELECT COUNT(*) AS total
FROM Products
WHERE CategoryID = ?
```

Used in `admin_action.php`.

Purpose:
- blocks category deletion while dependent products still exist

#### Delete category

```sql
DELETE FROM Categories
WHERE CategoryID = ?
```

Used in `admin_action.php`.

Purpose:
- removes an empty category

#### Insert product

```sql
INSERT INTO Products (ProductName, CategoryID, Price, StockQuantity)
VALUES (?, ?, ?, ?)
```

Used in `admin_action.php` and `setup_data.php`.

Purpose:
- creates a new sellable product

#### Check if a product is used in past orders

```sql
SELECT COUNT(*) AS total
FROM OrderItems
WHERE ProductID = ?
```

Used in `admin_action.php`.

Purpose:
- blocks deletion of historical products

#### Delete product

```sql
DELETE FROM Products
WHERE ProductID = ?
```

Used in `admin_action.php`.

Purpose:
- removes products that are not referenced by order history

#### Read current stock

```sql
SELECT StockQuantity
FROM Products
WHERE ProductID = ?
```

Used in `admin_action.php`.

Purpose:
- fetches current stock before adding or removing units

#### Update stock

```sql
UPDATE Products
SET StockQuantity = ?
WHERE ProductID = ?
```

Used in `admin_action.php`.

Purpose:
- changes stock based on admin add/remove operations

#### Load one pending order before approval

```sql
SELECT o.*, u.UserID, u.Email
FROM Orders o
JOIN Customers c ON o.CustomerID = c.CustomerID
JOIN Users u ON u.CustomerID = c.CustomerID
WHERE o.OrderID = ?
```

Used in `admin_action.php`.

Purpose:
- validates the selected order before accepting or refusing it

#### Accept order

```sql
UPDATE Orders
SET Status = 'accepted',
    EstimatedDeliveryDate = ?,
    AdminMessage = ?
WHERE OrderID = ?
```

Used in `admin_action.php`.

Purpose:
- marks the order as accepted and sets a delivery estimate

#### Refuse order

```sql
UPDATE Orders
SET Status = 'refused',
    AdminMessage = ?
WHERE OrderID = ?
```

Used in `admin_action.php`.

Purpose:
- marks the order as refused and stores the refusal message

---

### 9. Database overview and setup queries

#### Generic table inspection query

```sql
SELECT * FROM {tableName}
```

Used in `database_overview.php`.

Purpose:
- displays the full content of each main table for admin inspection

#### Check if demo users already exist

```sql
SELECT COUNT(*) AS total
FROM Users
```

Used in `setup_data.php`.

Purpose:
- prevents demo data from being inserted multiple times

#### Insert demo categories and products

```sql
INSERT INTO Categories (CategoryName, Description) VALUES (...)
INSERT INTO Products (ProductName, CategoryID, Price, StockQuantity) VALUES (...)
```

Used in `setup_data.php`.

Purpose:
- seeds the application with initial business data

---

## SQL Features Covered

The project currently uses the following required SQL features:

- `JOIN`
  Used in catalog, cart, admin, and order detail queries.
- `aggregate functions`
  `COUNT`, `SUM`, `AVG`, `MIN`.
- `SELECT within SELECT`
  Used in cart and messages queries.
- `NULL`
  Used with `IS NULL` and `COALESCE`.
- `Self JOIN`
  Used on `Products` to find alternatives in the same category.
- `INSERT`, `UPDATE`, `DELETE`
  Used in registration, admin actions, and order processing.
- `ORDER BY`
  Used throughout listing and reporting queries.
- `TOP`
  Used in the admin top 3 best-selling products query.
- `GROUP BY`
  Used in reporting and self-join reduction queries.

Note:
- the requirement `Vederi (cel putin 4)` is not implemented yet in the current codebase because there is no `CREATE VIEW` script yet.

---

## Demo Accounts

Created by `setup_data.php`:

- user account
  `user@shop.com` / `user123`
- admin account
  `admin@shop.com` / `admin123`
- demo payment PIN for the seeded normal user
  `1234`

---

## Recommended Next Documentation Step

The project is now documented at application level, but a complete academic submission would usually also include:

- the SQL `CREATE TABLE` script
- primary and foreign key definitions
- at least 4 SQL Server `VIEW` definitions
- a short ER diagram

Those are the main pieces still missing if the project must be delivered as a full database assignment.
