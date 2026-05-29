# Documentatie Tehnica - Mini-Proiect Baza de Date

## 1. Descriere proiect (tema)

Proiectul implementeaza un magazin online de electronice, cu functionalitati complete de autentificare, catalog produse, cos de cumparaturi, plasare comenzi si administrare.

Domeniul este **comert electronic** si acopera ciclul principal al unei comenzi:
1. creare cont client,
2. autentificare,
3. selectare produse,
4. confirmare comanda cu PIN,
5. validare comanda de catre admin.

Aplicatia foloseste:
- PHP (backend),
- Microsoft SQL Server,
- PDO cu driverul `sqlsrv`.

Baza folosita in cod: `ElectronicsShop`.

---

## 2. Schema bazei de date

### 2.1 Nucleul din diagrama (5 tabele)

Conform diagramei atasate, schema de baza este:

1. **Customers**
   - `CustomerID` (PK)
   - `FirstName`
   - `LastName`
   - `Email` (UNIQUE)
   - `Phone` (NULL)
   - `City`

2. **Orders**
   - `OrderID` (PK)
   - `CustomerID` (FK -> Customers.CustomerID)
   - `OrderDate`
   - `Status`

3. **OrderItems**
   - `OrderID` (FK -> Orders.OrderID)
   - `ProductID` (FK -> Products.ProductID)
   - `Quantity`
   - `UnitPrice`
   - (de regula PK compus: `OrderID`, `ProductID`)

4. **Categories**
   - `CategoryID` (PK)
   - `CategoryName`
   - `Description` (NULL)

5. **Products**
   - `ProductID` (PK)
   - `ProductName`
   - `CategoryID` (FK -> Categories.CategoryID)
   - `Price`
   - `StockQuantity`

### 2.2 Tabele suplimentare existente in cod

Pe langa cele 5 tabele din schema minima, proiectul foloseste inca 2 tabele:

6. **Users**
   - stocheaza credentiale, rol (`user`/`admin`) si PIN hash
   - leaga autentificarea de entitatea client

7. **AdminMessages**
   - jurnal de activitate (de exemplu cand un user plaseaza o comanda)

Aceste tabele extind schema astfel incat aplicatia web sa poata implementa autentificare si audit operational.

---

## 3. Relatii din schema (explicatie tehnica)

### 3.1 Relatii 1-N

1. **Customers (1) -> Orders (N)**
   - Un client poate avea mai multe comenzi.
   - O comanda apartine unui singur client.

2. **Orders (1) -> OrderItems (N)**
   - O comanda contine mai multe linii de produs.
   - Fiecare linie apartine unei singure comenzi.

3. **Categories (1) -> Products (N)**
   - O categorie poate contine mai multe produse.
   - Un produs apartine unei singure categorii.

4. **Products (1) -> OrderItems (N)**
   - Un produs poate aparea in mai multe comenzi (linii diferite).
   - O linie din `OrderItems` refera un singur produs.

### 3.2 Relatia M-N (implementata prin tabela de legatura)

**Orders (M) <-> (N) Products**, implementata prin **OrderItems**.

Interpretare:
- o comanda poate contine mai multe produse,
- acelasi produs poate aparea in mai multe comenzi.

### 3.3 Relatie recursiva (SELF JOIN)

Proiectul foloseste o relatie recursiva la nivel de interogare pe `Products`:
- `Products` este alaturat cu el insusi (`LEFT JOIN Products alt`) pe aceeasi `CategoryID`,
- scop: afisarea unui produs alternativ din aceeasi categorie.

Nu exista FK recursiv in schema fizica, dar exista folosire de **self join logic** in interogari.

### 3.4 Relatii suplimentare (schema extinsa din cod)

1. **Customers (0..1) <-> Users (1)**
   - utilizatorii normali au `CustomerID`,
   - contul admin poate avea `CustomerID = NULL`.

2. **Users (1) -> AdminMessages (N)**
   - un utilizator poate genera mai multe mesaje administrative.

---

## 4. Constrangeri observabile in implementare

### 4.1 Integritate de entitate
- PK pe tabelele principale (`CustomerID`, `OrderID`, `ProductID`, `CategoryID`).
- PK compus recomandat pentru `OrderItems(OrderID, ProductID)`.

### 4.2 Integritate referentiala
- FK folosite in join-uri si fluxuri:
  - `Orders.CustomerID` -> `Customers.CustomerID`
  - `Products.CategoryID` -> `Categories.CategoryID`
  - `OrderItems.OrderID` -> `Orders.OrderID`
  - `OrderItems.ProductID` -> `Products.ProductID`

### 4.3 Constrangeri de domeniu / business
- Email unic verificat in cod la inregistrare (`Users.Email`).
- `PaymentPin` impus in format 4 cifre.
- Stocul nu poate deveni negativ.
- Nu se poate sterge categorie daca are produse.
- Nu se poate sterge produs daca exista in istoric comenzi.
- Comanda se confirma doar daca PIN-ul este valid.

### 4.4 NULL handling
- campuri optionale: `Customers.Phone`, `Categories.Description`, anumite legaturi `Users.CustomerID`.
- tratament explicit cu `IS NULL` si `COALESCE(...)`.

---

## 5. Operatii SQL obligatorii - acoperire

Cerinta | Status in proiect | Observatii
---|---|---
JOIN | Implementat | multiple query-uri in shop/cart/messages/admin
Functii agregate | Implementat | `COUNT`, `SUM`, `AVG`, `MIN`
SELECT in SELECT | Implementat | in `cart.php`, `messages.php`
NULL | Implementat | `IS NULL`, `COALESCE`
SELF JOIN | Implementat | pe `Products` pentru alternative
INSERT / UPDATE / DELETE | Implementat | in register, place_order, admin_action
ORDER BY | Implementat | folosit in listing-uri si rapoarte
TOP | Implementat | `SELECT TOP 3` in admin
GROUP BY | Implementat | in rapoarte si self-join aggregation
Vederi (minim 4) | **Nu este implementat in codul actual** | lipsesc instructiuni `CREATE VIEW`

---

## 6. Lista completa a interogarilor SQL din proiect

Mai jos este lista interogarilor SQL folosite efectiv in fisierele PHP.

### 6.1 auth.php

```sql
SELECT UserID, CustomerID, Email, PasswordHash, Role, PaymentPinHash
FROM Users
WHERE UserID = ?
```

### 6.2 login.php

```sql
SELECT UserID, CustomerID, Email, PasswordHash, Role, PaymentPinHash
FROM Users
WHERE Email = ?
```

### 6.3 register.php

```sql
SELECT COUNT(*) AS total FROM Users WHERE Email = ?
```

```sql
INSERT INTO Customers (FirstName, LastName, Email, Phone, City)
VALUES (?, ?, ?, ?, ?)
```

```sql
INSERT INTO Users (CustomerID, Email, PasswordHash, Role, PaymentPinHash)
VALUES (?, ?, ?, 'user', ?)
```

### 6.4 shop.php

```sql
SELECT * FROM Products WHERE ProductID = ?
```

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
WHERE p.ProductName LIKE ? OR c.CategoryName LIKE ?      -- doar cand exista cautare
GROUP BY p.ProductID, p.ProductName, p.Price, p.StockQuantity, c.CategoryName
ORDER BY c.CategoryName, p.ProductName
```

### 6.5 cart.php

```sql
SELECT p.*, c.CategoryName
FROM Products p
JOIN Categories c ON c.CategoryID = p.CategoryID
WHERE p.ProductID IN (...)
```

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

### 6.6 checkout.php

```sql
SELECT * FROM Products WHERE ProductID IN (...)
```

### 6.7 place_order.php

```sql
INSERT INTO Orders (CustomerID, Status)
VALUES (?, 'pending')
```

```sql
SELECT * FROM Products WITH (UPDLOCK, ROWLOCK) WHERE ProductID = ?
```

```sql
INSERT INTO OrderItems (OrderID, ProductID, Quantity, UnitPrice)
VALUES (?, ?, ?, ?)
```

```sql
UPDATE Products
SET StockQuantity = StockQuantity - ?
WHERE ProductID = ?
```

```sql
INSERT INTO AdminMessages (UserID, MessageText) VALUES (?, ?)
```

### 6.8 messages.php

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

### 6.9 admin.php

```sql
SELECT m.*, u.Email
FROM AdminMessages m
JOIN Users u ON m.UserID = u.UserID
ORDER BY m.CreatedAt DESC
```

```sql
SELECT o.*, c.FirstName, c.LastName, c.Email
FROM Orders o
JOIN Customers c ON o.CustomerID = c.CustomerID
ORDER BY o.OrderDate DESC
```

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

```sql
SELECT
    p.ProductID,
    p.ProductName,
    p.Price,
    p.StockQuantity,
    c.CategoryName
FROM Products p
JOIN Categories c ON c.CategoryID = p.CategoryID
WHERE p.ProductName LIKE ? OR c.CategoryName LIKE ?      -- doar cand exista cautare
ORDER BY c.CategoryName, p.ProductName
```

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

### 6.10 admin_action.php

```sql
INSERT INTO Categories (CategoryName, Description)
VALUES (?, ?)
```

```sql
SELECT COUNT(*) AS total FROM Products WHERE CategoryID = ?
```

```sql
DELETE FROM Categories WHERE CategoryID = ?
```

```sql
INSERT INTO Products (ProductName, CategoryID, Price, StockQuantity)
VALUES (?, ?, ?, ?)
```

```sql
SELECT COUNT(*) AS total FROM OrderItems WHERE ProductID = ?
```

```sql
DELETE FROM Products WHERE ProductID = ?
```

```sql
SELECT StockQuantity FROM Products WHERE ProductID = ?
```

```sql
UPDATE Products SET StockQuantity = ? WHERE ProductID = ?
```

```sql
SELECT o.*, u.UserID, u.Email
FROM Orders o
JOIN Customers c ON o.CustomerID = c.CustomerID
JOIN Users u ON u.CustomerID = c.CustomerID
WHERE o.OrderID = ?
```

```sql
UPDATE Orders
SET Status = 'accepted',
    EstimatedDeliveryDate = ?,
    AdminMessage = ?
WHERE OrderID = ?
```

```sql
UPDATE Orders
SET Status = 'refused',
    AdminMessage = ?
WHERE OrderID = ?
```

### 6.11 database_overview.php

```sql
SELECT * FROM {tableName}
```

### 6.12 setup_data.php

```sql
SELECT COUNT(*) AS total FROM Users
```

```sql
INSERT INTO Customers (FirstName, LastName, Email, Phone, City)
VALUES (?, ?, ?, ?, ?)
```

```sql
INSERT INTO Users (CustomerID, Email, PasswordHash, Role, PaymentPinHash)
VALUES (?, ?, ?, ?, ?)
```

```sql
INSERT INTO Categories (CategoryName, Description) VALUES (...)
```

```sql
INSERT INTO Products (ProductName, CategoryID, Price, StockQuantity) VALUES (...)
```

---

## 7. Propunere de 4 vederi (pentru cerinta academica)

In codul actual nu exista `CREATE VIEW`, dar pentru conformitate completa se pot adauga:

```sql
CREATE VIEW vw_CustomerOrderTotals AS
SELECT
    c.CustomerID,
    c.FirstName,
    c.LastName,
    COUNT(DISTINCT o.OrderID) AS TotalOrders,
    COALESCE(SUM(oi.Quantity * oi.UnitPrice), 0) AS TotalSpent
FROM Customers c
LEFT JOIN Orders o ON o.CustomerID = c.CustomerID
LEFT JOIN OrderItems oi ON oi.OrderID = o.OrderID
GROUP BY c.CustomerID, c.FirstName, c.LastName;
```

```sql
CREATE VIEW vw_ProductSales AS
SELECT
    p.ProductID,
    p.ProductName,
    c.CategoryName,
    COALESCE(SUM(oi.Quantity), 0) AS UnitsSold,
    COALESCE(SUM(oi.Quantity * oi.UnitPrice), 0) AS Revenue
FROM Products p
JOIN Categories c ON c.CategoryID = p.CategoryID
LEFT JOIN OrderItems oi ON oi.ProductID = p.ProductID
GROUP BY p.ProductID, p.ProductName, c.CategoryName;
```

```sql
CREATE VIEW vw_PendingOrders AS
SELECT
    o.OrderID,
    o.OrderDate,
    o.Status,
    c.FirstName,
    c.LastName,
    c.Email
FROM Orders o
JOIN Customers c ON c.CustomerID = o.CustomerID
WHERE o.Status = 'pending';
```

```sql
CREATE VIEW vw_UsersWithMissingData AS
SELECT
    u.UserID,
    u.Email,
    u.Role,
    u.CustomerID,
    c.Phone
FROM Users u
LEFT JOIN Customers c ON c.CustomerID = u.CustomerID
WHERE u.CustomerID IS NULL OR c.Phone IS NULL;
```

---

## 8. Fluxuri operationale (pentru capturi de ecran)

Pentru sectiunea de prezentare practica se recomanda capturi pentru:

1. Inregistrare + login (validare email unic).
2. Cautare produse + self join (produs similar).
3. Cos + comparatie cu media categoriei (SELECT in SELECT + AVG).
4. Plasare comanda (INSERT in Orders + OrderItems + UPDATE stoc).
5. Admin: Top 3 produse (TOP + GROUP BY + SUM).
6. Admin: randuri cu NULL (IS NULL + COALESCE).
7. Admin: accept/refuz comanda (UPDATE Orders).
8. Database overview (SELECT * pe tabele).

---

## 9. Referinte bibliografice

1. Microsoft Learn - SQL Server documentation, T-SQL language reference.
2. Microsoft Learn - `CREATE VIEW (Transact-SQL)`.
3. PHP Documentation - PDO extension.
4. PHP Documentation - Password Hashing (`password_hash`, `password_verify`).
5. Database System Concepts (Silberschatz et al.) - modelare relationala si normalizare.

---

## 10. Concluzie

Proiectul respecta majoritatea cerintelor tehnice pentru mini-proiectul de baza de date:
- schema relationala corecta pentru domeniul e-commerce,
- relatii 1-N, M-N si self-join demonstrat,
- interogari avansate cu agregari, subinterogari, NULL handling,
- operatii complete de tip INSERT/UPDATE/DELETE.

Singura cerinta lipsa in implementarea curenta este definirea explicita a minimum 4 vederi SQL (`CREATE VIEW`), pentru care a fost inclusa o propunere concreta in aceasta documentatie.
