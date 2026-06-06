# Product Specification — Stock Management Web Application

| Document | Value |
|----------|-------|
| **Product** | Production Stock Management System |
| **Client** | YUKIOH MYANMAR CO.,LTD |
| **Company ID** | 119751578 |
| **Version** | 1.0 (MVP) |
| **Status** | Draft for development |
| **Platform** | Web (desktop + mobile browser) |
| **Stack (target)** | PHP 8.x, MySQL/MariaDB, XAMPP-compatible |

---

## 1. Purpose

A web-based stock management system for recording stock in/out, tracking balances, managing items and customers, and controlling staff submissions through an admin approval workflow. The system supports perishable product data (manufacturing date, expiry, lot number) for Fruits, Gelato, and Icecream categories.

---

## 2. Scope

### 2.1 In scope (MVP)

- Secure login with Admin and Staff roles
- Item, Customer, Stock In, Stock Out modules (Search, Add, Edit, Delete, List)
- Balance List with summary graph
- Staff submission → Admin approval for Stock In and Stock Out
- Dashboard (summary, pending approvals, recent activity)
- Reports with date, product category, in/out type, customer, and current stock filters
- Responsive UI for phone and computer browsers
- Company name and ID on reports/print views

### 2.2 Out of scope (MVP)

- Native mobile apps
- Multi-warehouse / multi-location stock
- Unit conversion (e.g. box → pieces)
- Email/SMS notifications
- PDF/Excel export (reserved for Phase 2)
- Barcode/QR scanning
- Multi-language UI
- Customer portal or external API

---

## 3. Users & Roles

### 3.1 Role definitions

| Role | Description |
|------|-------------|
| **Admin** | Full access. Approves staff requests. Manages masters and users. |
| **Staff** | Submits Stock In/Out requests. Read-only access to masters and balances. |

> **Note:** `Staff-1` and `Staff-2` in the source module list are **example staff accounts**, not separate permission levels. MVP uses one Staff role; individual users may be labeled Staff-1, Staff-2, etc.

### 3.2 User account fields

| Field | Required | Notes |
|-------|----------|-------|
| User ID | Auto | Primary key |
| Username | Yes | Unique |
| Password | Yes | Hashed (bcrypt) |
| Display name | Yes | Shown on records (e.g. In Charge Name) |
| Role | Yes | `admin` or `staff` |
| Status | Yes | `active`, `inactive` |
| Created date & time | Auto | Audit |

### 3.3 Permission matrix

| Action | Admin | Staff |
|--------|-------|-------|
| Login / logout | ✓ | ✓ |
| Manage users (add/edit/deactivate) | ✓ | ✗ |
| Item module — Add / Edit / Delete | ✓ | ✗ |
| Item module — Search / List | ✓ | ✓ (read-only) |
| Customer module — Add / Edit / Delete | ✓ | ✗ |
| Customer module — Search / List | ✓ | ✓ (read-only) |
| Stock In — direct add (auto-approved) | ✓ | ✗ |
| Stock In — submit request (pending) | ✓ | ✓ |
| Stock Out — direct add (auto-approved) | ✓ | ✗ |
| Stock Out — submit request (pending) | ✓ | ✓ |
| Edit/delete own pending Stock In/Out | ✓ | ✓ |
| Edit/delete approved Stock In/Out | ✓ | ✗ |
| Approve / reject requests | ✓ | ✗ |
| Balance List & graph | ✓ | ✓ (read-only) |
| Reports | ✓ | ✓ (read-only, no user management) |
| Dashboard — pending approvals block | ✓ | Own pending only |

### 3.4 Authentication (minimum)

- Session-based login
- Password minimum 8 characters
- Admin creates all user accounts (no self-registration in MVP)
- Failed login shows generic error (no username enumeration)
- Session timeout after 30 minutes of inactivity

---

## 4. Modules

All data modules support: **Search**, **Add**, **Edit**, **Delete**, **List** unless noted otherwise.

---

### 4.1 Item Module (Product master)

**Purpose:** Define products. Stock quantity is **not** stored here; balance is calculated from Stock In/Out.

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Item No | Text | Yes | Unique code/SKU |
| Item Name | Text | Yes | |
| Unit | Text | Yes | e.g. pcs, kg, box |
| Unit Price | Decimal | No | Reference price for reports |
| Category | Enum | Yes | `Fruits`, `Gelato`, `Icecream` |
| Remark | Text | No | |
| Created date & time | DateTime | Auto | |
| Created by | User ref | Auto | |

**Rules**

- Item No must be unique
- Delete blocked if item is referenced in any Stock In/Out record (use inactive flag or block delete)
- List view may show **computed current balance** as read-only column (from Balance logic)

**Search/List filters:** Item No, Item Name, Category

---

### 4.2 Customer Module

**Purpose:** Customers linked to Stock Out.

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Customer Code | Text | Yes | Unique |
| Customer Name | Text | Yes | |
| Customer Address | Text | No | |
| Customer Type | Enum | Yes | `Retail`, `Whole Sale` |
| Created date & time | DateTime | Auto | |
| Created by | User ref | Auto | |

**Rules**

- Customer Code must be unique
- Delete blocked if referenced in Stock Out

**Search/List filters:** Customer Code, Customer Name, Customer Type

---

### 4.3 Stock In Module

**Purpose:** Record incoming stock. Increases balance when **approved**.

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Record ID | Auto | — | Internal ID |
| Item No | Ref → Item | Yes | Dropdown/search; auto-fills Item Name |
| Item Name | Text | Display | Read-only from Item |
| MFD Date | Date | No | Manufactured date |
| Expire Date | Date | No | Must be ≥ MFD Date if both set |
| Lot No | Text | No | Batch traceability (stored, not separate balance in MVP) |
| Qty | Decimal | Yes | Must be > 0 |
| Unit | Text | Yes | Default from Item; editable if needed |
| Worker Qty | Decimal | No | Operational note; **not** used in balance |
| In Charge Name | Text | Yes | Default to logged-in user display name |
| Status | Enum | Auto | `pending`, `approved`, `rejected` |
| Rejection reason | Text | Conditional | Required when rejected |
| Created date & time | DateTime | Auto | |
| Created by | User ref | Auto | |
| Approved by | User ref | Auto | Admin on approval |
| Approved date & time | DateTime | Auto | |

**Rules**

- Admin Add → status `approved` immediately
- Staff Add → status `pending`
- Only `approved` records affect balance
- Edit/delete allowed only while `pending` (Staff: own records only)
- Admin may reject with reason; record kept for audit

**Search/List filters:** Date range, Item No/Name, Category (via item), Status, Lot No

---

### 4.4 Stock Out Module

**Purpose:** Record outgoing stock to customers. Decreases balance when **approved**.

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Record ID | Auto | — | |
| Item No | Ref → Item | Yes | Auto-fills Item Name |
| Item Name | Text | Display | Read-only |
| Customer | Ref → Customer | Yes | Dropdown; show Customer Name |
| MFD Date | Date | No | Batch/issue reference (informational in MVP) |
| Qty | Decimal | Yes | Must be > 0 |
| Unit | Text | Yes | Default from Item |
| Reason | Enum | Yes | `Sales`, `Sample`, `Sale & Marketing`, `Other` |
| Remark | Text | No | Especially for Reason = Other |
| Status | Enum | Auto | `pending`, `approved`, `rejected` |
| Rejection reason | Text | Conditional | Required when rejected |
| Created date & time | DateTime | Auto | |
| Created by | User ref | Auto | |
| Approved by | User ref | Auto | |
| Approved date & time | DateTime | Auto | |

**Rules**

- Admin Add → `approved` immediately
- Staff Add → `pending`
- On approval, system validates: `Qty ≤ current available balance` for that item
- If insufficient stock at approval time → reject with message (or block approval)
- Negative stock is **not** allowed in MVP
- Same edit/delete rules as Stock In

**Search/List filters:** Date range, Item, Customer, Reason, Status, Category (via item)

---

### 4.5 Balance List Module

**Purpose:** Show current stock levels. **Read-only** — no Add/Edit/Delete.

| Column | Notes |
|--------|-------|
| Item No | |
| Item Name | |
| Category | Fruits / Gelato / Icecream |
| Unit | |
| Current balance | Computed |
| Last stock in date | Optional display |
| Last stock out date | Optional display |

**Balance formula (MVP — item level)**

```
Current Balance (item) = SUM(approved Stock In qty) − SUM(approved Stock Out qty)
```

Lot No is stored on Stock In for traceability but does **not** maintain separate lot-level balances in MVP.

**Graph (minimum)**

| Chart | Data |
|-------|------|
| Bar chart | Current balance grouped by category (Fruits, Gelato, Icecream) |
| Optional table sort | Sort by balance ascending to surface low stock |

**Filters:** Category, Item Name/No search, show zero-balance items (toggle)

---

## 5. Approval Workflow

```
Staff creates Stock In or Stock Out
              ↓
         [ PENDING ]
         ↙         ↘
   [ APPROVED ]   [ REJECTED ]
         ↓              ↓
  Updates balance   No balance change
  Audit retained    Reason stored
```

| State | Balance impact | Who can set |
|-------|----------------|-------------|
| Pending | None | Staff/Admin on create |
| Approved | In/Out applied | Admin only |
| Rejected | None | Admin only |

**Admin direct entry:** Skips pending; created as `approved`.

**Dashboard:** Admin sees count and list of all pending Stock In + Stock Out. Staff sees only their own pending items.

---

## 6. Dashboard (minimum)

| Widget | Admin | Staff |
|--------|-------|-------|
| Total items (active) | ✓ | ✓ |
| Total current stock (sum of balances) | ✓ | ✓ |
| Pending approvals count | ✓ (all) | ✓ (own) |
| Pending approvals list (latest 10) | ✓ | ✓ (own) |
| Recent activity (latest 10 approved in/out) | ✓ | ✓ |
| Category balance bar chart (mini) | ✓ | ✓ |

---

## 7. Reports & Filters

Single **Reports** page with report type selector and shared filters.

### 7.1 Report types

| Report | Description |
|--------|-------------|
| Stock In | All approved (and optional pending) stock in records |
| Stock Out | All approved (and optional pending) stock out records |
| Current Stock | Balance List snapshot |
| Activity Summary | Combined in/out counts and quantities by period |

### 7.2 Common filters

| Filter | Applies to |
|--------|------------|
| Date from / Date to | Stock In, Stock Out, Activity |
| Product category | All except Customer-only |
| Item No / Name | All |
| Stock type | Stock In only / Stock Out only / Both |
| Customer | Stock Out, Activity |
| Reason | Stock Out |
| Status | pending / approved / rejected |
| Customer type | Retail / Whole Sale (via customer) |

### 7.3 Report output

- On-screen table with pagination
- Print-friendly view with company header:
  - **YUKIOH MYANMAR CO.,LTD**
  - **Company ID: 119751578**
  - Report title, filter summary, generated date/time, generated by

---

## 8. Data Integrity & Audit

| Rule | Implementation |
|------|----------------|
| Unique codes | Item No, Customer Code, Username |
| Referential integrity | Stock records must reference valid Item; Stock Out must reference valid Customer |
| Immutable history | Approved records are not hard-deleted; admin may void via future phase |
| Audit fields | created_by, created_at, approved_by, approved_at on transactions |
| Concurrency | Validate balance at approval time, not only at submission |

---

## 9. Non-Functional Requirements

| Area | Minimum requirement |
|------|---------------------|
| **Responsive UI** | Usable on phone (≥320px width); tables scroll or stack as cards |
| **Browsers** | Latest Chrome, Firefox, Edge, Safari (mobile + desktop) |
| **Performance** | List pages load within 3 seconds for up to 5,000 transaction rows (paginated) |
| **Security** | HTTPS in production; password hashing; prepared SQL; CSRF on forms; role checks server-side |
| **Availability** | Single-server deployment (XAMPP / LAMP); no HA required for MVP |
| **Backup** | Manual MySQL backup procedure (documentation only for MVP) |

---

## 10. Screen Inventory

| # | Screen | Primary actions |
|---|--------|-----------------|
| 1 | Login | Authenticate |
| 2 | Dashboard | View summary |
| 3 | Items — List | Search, Add, Edit, Delete |
| 4 | Customers — List | Search, Add, Edit, Delete |
| 5 | Stock In — List | Search, Add, Edit, Delete, Approve/Reject (admin) |
| 6 | Stock Out — List | Search, Add, Edit, Delete, Approve/Reject (admin) |
| 7 | Balance List | List, graph, filter |
| 8 | Reports | Filter, view, print |
| 9 | Users (admin) | Add, Edit, deactivate staff |
| 10 | Pending Approvals (admin) | Bulk view; shortcut from dashboard |

---

## 11. Default Data (seed)

| Data | Values |
|------|--------|
| Product categories | Fruits, Gelato, Icecream |
| Customer types | Retail, Whole Sale |
| Stock Out reasons | Sales, Sample, Sale & Marketing, Other |
| Request statuses | Pending, Approved, Rejected |
| Initial admin account | Created on first setup (installer or manual seed) |

---

## 12. Gap Resolution Log

Decisions made in this document where the source module list was silent or ambiguous:

| Topic | MVP decision |
|-------|--------------|
| Item `Qty` field on master | Removed as stored field; balance computed on Balance List only |
| Staff-1 vs Staff-2 | Same Staff role; names are user labels only |
| Worker Qty | Optional; not used in balance calculation |
| Lot No | Stored on Stock In; item-level balance only (no lot-level stock in MVP) |
| Stock Out MFD Date | Optional batch reference |
| Approval | Required for Staff on both Stock In and Stock Out |
| Negative stock | Blocked at approval |
| Delete after approval | Not allowed in MVP |
| User onboarding | Admin creates accounts |
| Export | Deferred to Phase 2 |
| Expiry alerts | Deferred to Phase 2 (Expire Date stored for future use) |

---

## 13. Phase 2 (future, not MVP)

- Export to Excel/PDF
- Expiry date warnings on dashboard
- Lot-level balance tracking
- Email notification on pending approval
- Void/correction entries for approved transactions
- Low-stock threshold alerts per item
- Line chart: stock movement over time

---

## 14. Acceptance Criteria (MVP)

1. Admin can log in, create Staff users, and manage Items and Customers.
2. Admin can record Stock In/Out that is immediately approved and updates balance.
3. Staff can submit Stock In/Out that remains pending until Admin approves or rejects.
4. Approved Stock Out is blocked when quantity exceeds current balance.
5. Balance List shows correct quantity per item and category bar chart.
6. Reports filter correctly by date, category, customer, in/out type, and reason.
7. Dashboard shows pending approvals and recent activity per role rules.
8. All list/add/edit screens work on mobile browser without horizontal overflow breaking layout.
9. Printed report shows company name and company ID.

---

## 15. Reference

Source field layout: `docs/module.txt`

---

*End of document*
