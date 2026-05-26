# Resurvey Alda: Architecture & Technical Specification

Resurvey Alda is a server-rendered, mobile-optimised PHP web application that manages the end-to-end lifecycle of ALDA debt collection task assignments for PT Suzuki Finance Indonesia. The system bridges a centralised SQL Server database of vehicle financing contracts with a distributed mobile workforce, enabling back-office supervisors to assign, reassign, and monitoring tasks whilst granting field officers (PICs — *Person in Charge*) a structured, real-time interface to progress their individual workloads from any mobile browser. All database interactions are executed exclusively via stored procedures;.

---

## Table of Contents

1. [Business Context](#1-business-context)
2. [System Architecture](#2-system-architecture)
3. [Database Schema](#3-database-schema)
4. [End-to-End Data Flow](#4-end-to-end-data-flow)
5. [Authentication & Session Management](#5-authentication--session-management)
6. [Module Reference](#6-module-reference)
7. [Stored Procedure Catalogue](#7-stored-procedure-catalogue)
8. [Task Lifecycle & Status Model](#8-task-lifecycle--status-model)
9. [Data Normalisation & Enrichment](#9-data-normalisation--enrichment)
10. [Security Posture](#10-security-posture)
11. [Technical Dependencies](#11-technical-dependencies)
12. [System Constraints & Stub Modules](#12-system-constraints--stub-modules)
13. [Deployment Runbook](#13-deployment-runbook)

---

## 1. Business Context

Suzuki Finance Indonesia maintains a portfolio of ALDA vehicle financing contracts. When a contract enters a resurvey or collection lifecycle stage — typically triggered by payment delinquency or a required field verification — a back-office supervisor assigns the contract to a field officer. The PIC visits the customer, validates information on-site, and advances the task through defined lifecycle stages until it reaches a terminal state. This system provides the operational infrastructure for that workflow.

**Workforce Mobilisation:** Delivers field staff a responsive, real-time task interface accessible from any mobile browser without requiring a native application installation or offline capability.

**Process Enforcement:** Mandates a structured, unidirectional status progression — `ASSIGNED` → `IN_PROGRESS` → `COMPLETED` — enforced at the database tier via stored procedure logic rather than application-layer rules, ensuring consistency regardless of how the system is accessed.

**Queue Visibility:** Surfaces real-time workload counts across all status stages on the PIC dashboard, enabling immediate situational awareness without navigating into individual task lists.

**Centralised Governance:** Provides back-office operators with full assignment, reassignment, and cancellation controls mediated through stored procedures, eliminating the need for direct database access and ensuring every mutation is captured in an immutable audit trail.

---

## 2. System Architecture

The solution implements a three-tier, server-rendered architecture with a strict separation between presentation, application, and data layers. The PHP application tier handles session management, input validation, HTML rendering, and stored procedure dispatch. No business logic resides in the browser; the client receives rendered HTML and executes minimal vanilla JavaScript for modal interactions and form submission guards only.

```
┌──────────────────────────────────────────────────────────────┐
│                  Client — Mobile Browser                     │
│        Server-rendered HTML · Vanilla ES5+ JavaScript        │
└──────────────────────────┬───────────────────────────────────┘
                           │  HTTP — Form POST / GET
┌──────────────────────────▼───────────────────────────────────┐
│               Application Tier — PHP 8.3                     │
│                                                              │
│  index.php                  Routing / session redirect       │
│  login.php                  Credential validation            │
│  dashboard.php              Workload summary hub             │
│  tugas-baru.php             ASSIGNED task list & advance     │
│  tugas-proses.php           IN_PROGRESS task list (read)     │
│  tugas-sedang-berjalan.php  Stub — COMPLETED view            │
│  selesai.php                Stub                             │
│  upload.php                 Stub                             │
│  logout.php                 Session teardown                 │
└──────────────────────────┬───────────────────────────────────┘
                           │  sqlsrv extension — RPC / T-SQL
┌──────────────────────────▼───────────────────────────────────┐
│        Data Tier — SQL Server 2008 · MOBILE_COLLECTION       │
│                                                              │
│  MASTER_ALDA               Customer & ContractID             │
│  MASTER_ALDA_PIC           PIC Master Data                   │
│  ALDA_PENUGASAN            Active task assignments           │
│  ALDA_PENUGASAN_HISTORY    Immutable audit ledger            │
│  ALDA_STATUS_REF           Status code reference             │
│                                                              │
│  11 Stored Procedures (see §7)                               │
└──────────────────────────────────────────────────────────────┘
```

The sole exception to the stored-procedure-only data access rule is an inline `SELECT` in `dashboard.php`, which queries `MASTER_ALDA_PIC` directly to re-validate PIC session liveness on every page load.

---

## 3. Database Schema

### 3.1 `MASTER_ALDA` — Customer Contract Data

The read-only source of all customer and contract data. Records are provisioned externally — by a core banking or contract management platform — and are never written to by this application. Assignment procedures snapshot relevant columns into `ALDA_PENUGASAN` at the time of assignment.

| Column | Type | Purpose |
|---|---|---|
| `AREA` | `varchar(50)` | Regional area classification for the contract. |
| `BRANCH_ID` | `varchar(20)` | Identifier of the branch responsible for the contract. |
| `BRANCH_NAME` | `varchar(200)` | Human-readable name of the branch. |
| `PORTFOLIO` | `varchar(20)` | Portfolio segment classification (e.g. retail, commercial). |
| `NOMOR_KONTRAK` | `varchar(50)` PK | Unique contract number; the primary identifier joining this table to `ALDA_PENUGASAN`. |
| `CUSTOMER_NAME` | `varchar(300)` | Full registered name of the financing customer. |
| `GO_LIVE_DATE` | `datetime` | Date the financing contract was activated. |
| `TANGGAL_BAYAR_ANGS_TERAKHIR` | `datetime` | Date of the customer's most recent instalment payment. |
| `MERK_KENDARAAN` | `varchar(100)` | Vehicle make (manufacturer brand). |
| `TYPE_KENDARAAN` | `varchar(200)` | Vehicle model or variant designation. |
| `TAHUN_KENDARAAN` | `int` | Vehicle year of manufacture. |
| `CUSTOMER_PHONE` | `varchar(50)` | Customer contact telephone number. |
| `LEGAL_ADDRESS` | `varchar(max)` | Customer's registered legal address as recorded on the financing agreement. |
| `CONTRACT_STATUS` | `varchar(50)` | Current contract status as reported by the core system (e.g. active, overdue). |
| `AMOUNT_TO_BE_PAID` | `decimal(18,2)` | Outstanding amount due from the customer at the time of data extraction. |

---

### 3.2 `MASTER_ALDA_PIC` — PIC Personnel Directory

Defines the complete roster of field officers authorised to receive task assignments. Authentication and session validation depend entirely on this table. The `IS_ACTIVE` flag serves as a soft-delete mechanism; deactivating a PIC blocks login and immediately terminates any live session upon the next `dashboard.php` load.

| Column | Type | Purpose |
|---|---|---|
| `AREA` | `varchar(100)` | Regional area to which the PIC is assigned. |
| `CABANG` | `varchar(100)` | Branch name of the PIC's home office. |
| `BRANCH_ID` | `varchar(10)` | Machine-readable branch identifier; used to filter PIC dropdowns by branch in the back-office. |
| `NIK` | `varchar(50)` | Employee identification number; the login key and the primary identifier used in all assignment and comparison operations. |
| `NAMA` | `varchar(200)` | PIC full name, written to the session on login and displayed throughout the application. |
| `JABATAN` | `varchar(100)` | Job title or designation; snapshotted into `ALDA_PENUGASAN` at assignment time. |
| `PILAR` | `varchar(100)` | Organisational pillar or division; snapshotted at assignment. |
| `LOKASI_FISIK` | `varchar(100)` | Physical office or location of the PIC; snapshotted at assignment. |
| `LOKASI_PEKERJAAN` | `varchar(100)` | Operational work location, which may differ from `LOKASI_FISIK`; snapshotted at assignment. |
| `IS_ACTIVE` | `bit` | Active status flag. `1` permits login; `0` blocks authentication and invalidates live sessions. Default is `0`. |

---

### 3.3 `ALDA_PENUGASAN` — Active Task Assignments

The central operational table. Each row represents a single active contract assignment. A `UNIQUE` constraint on `CONTRACT_NO` enforces that at most one assignment record exists per contract at any given time. Reassignment updates the existing row in-place rather than inserting a new record, with the prior state preserved in `ALDA_PENUGASAN_HISTORY`. PIC attributes and customer data are denormalised into this table at assignment time to preserve historical integrity regardless of subsequent changes to `MASTER_ALDA` or `MASTER_ALDA_PIC`.

| Column | Type | Purpose |
|---|---|---|
| `PENUGASAN_ID` | `bigint` IDENTITY PK | Surrogate primary key; auto-incremented by the database. |
| `CONTRACT_NO` | `varchar(50)` UNIQUE | Foreign reference to `MASTER_ALDA.NOMOR_KONTRAK`; enforces one active assignment per contract. |
| `SUBMISSION_ID` | `bigint` | Batch identifier generated from the assignment datetime, formatted as `yyyyMMddHHmmssmmm` and cast to `BIGINT`. |
| `STATUS` | `varchar(20)` | Current lifecycle status; constrained to `ASSIGNED`, `IN_PROGRESS`, `COMPLETED`, or `CANCELLED`. Default is `ASSIGNED`. |
| `ASSIGN_VERSION` | `int` | Monotonically incrementing counter, incremented on every status change or reassignment. Provides a lightweight optimistic-concurrency indicator. Default is `1`. |
| `NOTES` | `varchar(500)` | Free-text annotation recorded at assignment or at the point of cancellation or reassignment. |
| `PIC_NIK` | `varchar(50)` | NIK of the assigned PIC, snapshotted at assignment time. |
| `PIC_NAME` | `varchar(200)` | Full name of the assigned PIC, snapshotted at assignment time. |
| `PIC_JABATAN` | `varchar(100)` | Job title of the assigned PIC, snapshotted at assignment time. |
| `PIC_PILAR` | `varchar(100)` | Organisational pillar of the assigned PIC, snapshotted at assignment time. |
| `PIC_LOKASI_FISIK` | `varchar(100)` | Physical location of the assigned PIC, snapshotted at assignment time. |
| `PIC_LOKASI_PEKERJAAN` | `varchar(100)` | Work location of the assigned PIC, snapshotted at assignment time. |
| `PIC_AREA` | `varchar(100)` | Regional area of the assigned PIC, snapshotted at assignment time. |
| `PIC_CABANG` | `varchar(100)` | Branch name of the assigned PIC, snapshotted at assignment time. |
| `PIC_BRANCH_ID` | `varchar(10)` | Branch identifier of the assigned PIC, snapshotted at assignment time. |
| `AREA` | `varchar(50)` | Customer contract area, snapshotted from `MASTER_ALDA` at assignment. |
| `BRANCH_ID` | `varchar(20)` | Customer contract branch identifier, snapshotted from `MASTER_ALDA`. |
| `BRANCH_NAME` | `varchar(200)` | Customer contract branch name, snapshotted from `MASTER_ALDA`. |
| `PORTFOLIO` | `varchar(20)` | Customer contract portfolio classification, snapshotted from `MASTER_ALDA`. |
| `CUSTOMER_NAME` | `varchar(300)` | Customer full name, snapshotted from `MASTER_ALDA` at assignment. |
| `LEGAL_ADDRESS` | `varchar(max)` | Customer legal address, snapshotted from `MASTER_ALDA` at assignment. |
| `CUSTOMER_PHONE` | `varchar(50)` | Customer telephone number, snapshotted from `MASTER_ALDA` at assignment. |
| `GO_LIVE_DATE` | `datetime` | Contract activation date, snapshotted from `MASTER_ALDA` at assignment. |
| `TANGGAL_BAYAR_ANGS_TERAKHIR` | `datetime` | Date of last instalment payment, snapshotted from `MASTER_ALDA` at assignment. |
| `MERK_KENDARAAN` | `varchar(100)` | Vehicle make, snapshotted from `MASTER_ALDA` at assignment. |
| `TYPE_KENDARAAN` | `varchar(200)` | Vehicle model, snapshotted from `MASTER_ALDA` at assignment. |
| `TAHUN_KENDARAAN` | `int` | Vehicle year of manufacture, snapshotted from `MASTER_ALDA` at assignment. |
| `CONTRACT_STATUS_SNAPSHOT` | `varchar(50)` | Contract status as recorded in `MASTER_ALDA` at the time of assignment; a point-in-time snapshot independent of live master data. |
| `AMOUNT_TO_BE_PAID` | `decimal(18,2)` | Outstanding amount due, snapshotted from `MASTER_ALDA` at assignment. |
| `CREATED_AT` | `datetime` | Timestamp of the initial record insertion; immutable after creation. Default is `GETDATE()`. |
| `CREATED_BY` | `varchar(200)` | Identity of the operator or system that created the assignment record. |
| `UPDATED_AT` | `datetime` | Timestamp of the most recent mutation to the record. |
| `UPDATED_BY` | `varchar(200)` | Identity of the operator or system responsible for the most recent mutation. |

---

### 3.4 `ALDA_PENUGASAN_HISTORY` — Audit Ledger

An append-only table that records the full state of an assignment immediately before each mutation. A history row is inserted prior to every status change, reassignment, or cancellation, capturing both the before and after values to provide complete, queryable traceability. The `CHANGE_TYPE` column is constrained to `REASSIGN`, `STATUS_CHANGE`, or `CANCEL`.

| Column | Type | Purpose |
|---|---|---|
| `HISTORY_ID` | `bigint` IDENTITY PK | Surrogate primary key for the history record. |
| `PENUGASAN_ID` | `bigint` | Foreign reference to the `ALDA_PENUGASAN` record being mutated. |
| `CONTRACT_NO` | `varchar(50)` | Contract number; denormalised for direct query access without joining to the parent table. |
| `CHANGE_TYPE` | `varchar(20)` | Classification of the mutation; constrained to `REASSIGN`, `STATUS_CHANGE`, or `CANCEL`. |
| `CHANGE_REASON` | `varchar(500)` | Operator-supplied or system-generated rationale for the change. |
| `CHANGED_AT` | `datetime` | Timestamp of the mutation. Default is `GETDATE()`. |
| `CHANGED_BY` | `varchar(200)` | Identity of the operator or system that initiated the change. |
| `STATUS_BEFORE` | `varchar(20)` | Assignment status immediately prior to the mutation. |
| `STATUS_AFTER` | `varchar(20)` | Assignment status applied by the mutation. |
| `ASSIGN_VERSION_BEFORE` | `int` | `ASSIGN_VERSION` value before the mutation. |
| `ASSIGN_VERSION_AFTER` | `int` | `ASSIGN_VERSION` value after the mutation. |
| `PIC_NIK_BEFORE` | `varchar(50)` | NIK of the PIC holding the assignment before the mutation. |
| `PIC_NAME_BEFORE` | `varchar(200)` | Full name of the PIC before the mutation. |
| `PIC_JABATAN_BEFORE` | `varchar(100)` | Job title of the PIC before the mutation. |
| `PIC_PILAR_BEFORE` | `varchar(100)` | Organisational pillar of the PIC before the mutation. |
| `PIC_LOKASI_FISIK_BEFORE` | `varchar(100)` | Physical location of the PIC before the mutation. |
| `PIC_LOKASI_PEKERJAAN_BEFORE` | `varchar(100)` | Work location of the PIC before the mutation. |
| `PIC_AREA_BEFORE` | `varchar(100)` | Regional area of the PIC before the mutation. |
| `PIC_CABANG_BEFORE` | `varchar(100)` | Branch of the PIC before the mutation. |
| `PIC_NIK_AFTER` | `varchar(50)` | NIK of the PIC assigned after the mutation; `NULL` for cancellations. |
| `PIC_NAME_AFTER` | `varchar(200)` | Full name of the PIC after the mutation; `NULL` for cancellations. |
| `PIC_JABATAN_AFTER` | `varchar(100)` | Job title of the incoming PIC after the mutation. |
| `PIC_PILAR_AFTER` | `varchar(100)` | Organisational pillar of the incoming PIC after the mutation. |
| `PIC_LOKASI_FISIK_AFTER` | `varchar(100)` | Physical location of the incoming PIC after the mutation. |
| `PIC_LOKASI_PEKERJAAN_AFTER` | `varchar(100)` | Work location of the incoming PIC after the mutation. |
| `PIC_AREA_AFTER` | `varchar(100)` | Regional area of the incoming PIC after the mutation. |
| `PIC_CABANG_AFTER` | `varchar(100)` | Branch of the incoming PIC after the mutation. |
| `CONTRACT_STATUS_SNAPSHOT` | `varchar(50)` | Contract status from `MASTER_ALDA` at the time the history record was created. |
| `AMOUNT_TO_BE_PAID` | `decimal(18,2)` | Outstanding amount at the time the history record was created. |

---

### 3.5 `ALDA_STATUS_REF` — Status Code Reference

A metadata table that decorates each status code with operational attributes. Stored procedures consult this table to determine whether a given status permits further mutation before executing any write operation.

| Column | Type | Purpose |
|---|---|---|
| `STATUS_CODE` | `varchar(20)` PK | The canonical status identifier used in `ALDA_PENUGASAN.STATUS` (e.g. `ASSIGNED`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`). |
| `STATUS_LABEL` | `varchar(100)` | Human-readable display label for the status; returned by `SP_ALDA_GET_PENUGASAN` as `ASSIGN_STATUS_LABEL`. |
| `DESCRIPTION` | `varchar(300)` | Extended description of the status and its operational meaning. |
| `IS_FINAL` | `bit` | Terminal state flag. When `1`, stored procedures block any further status change, reassignment, or cancellation. Default is `0`. |
| `SORT_ORDER` | `int` | Integer for ordering statuses in UI lists and reports. Default is `0`. |
| `IS_ACTIVE` | `bit` | Controls whether the status code is available for use in assignment operations. Default is `1`. |

---

## 4. End-to-End Data Flow

### 4.1 Task Creation — Back-Office

```
Supervisor identifies a contract eligible for assignment
         │
         ▼
SP_ALDA_TASKLIST_PENUGASAN(@BRANCH_ID, ...)
  Queries MASTER_ALDA LEFT JOIN ALDA_PENUGASAN where the assignment
  is absent, CANCELLED, or in a final state — i.e. contracts
  available to receive a new assignment.
         │
         ▼
Supervisor selects a PIC from SP_ALDA_DROPDOWN_PIC(@BRANCH_ID)
  Returns NIK, name, title, and physical location for all PICs
  within the specified branch.
         │
         ▼
SP_ALDA_SUBMIT_ASSIGN(@usercreate, @pic_nik, @nomor_kontrak, @notes)
  Validates contract exists in MASTER_ALDA.
  Validates PIC exists in MASTER_ALDA_PIC.
  If no existing assignment → INSERT into ALDA_PENUGASAN (STATUS = 'ASSIGNED', ASSIGN_VERSION = 1).
  If a non-final or CANCELLED record exists → INSERT history row → UPDATE record in-place.
  Generates SUBMISSION_ID from GETDATE() formatted as yyyyMMddHHmmssmmm cast to BIGINT.
```

### 4.2 Authentication & Dashboard Load

```
PIC submits NIK and password via login.php
         │
         ▼
SP_LOGIN_RESURVEY_ALDA(@p_NIK, @p_Password)
  Verifies NIK exists in MASTER_ALDA_PIC.
  Confirms IS_ACTIVE = 1.
  Validates password equals the static value 'user.100'.
  Returns LoginStatus (1 = success), NIK, NAMA, and Message.
         │
         ▼
PHP writes session: user_logged_in = true, user_nik, user_name.
         │
         ▼
dashboard.php re-validates session against MASTER_ALDA_PIC (inline SELECT).
  Re-checks IS_ACTIVE — detects accounts deactivated mid-session.
  Refreshes user_name from live data. Destroys session if IS_ACTIVE = 0.
         │
         ▼
SP_ALDA_PIC_TASKLIST_SUMMARY(@PIC_NIK)
  Issues a single SUM(CASE …) aggregation over ALDA_PENUGASAN.
  Returns TUGAS_BARU (ASSIGNED count), TUGAS_PROSES (IN_PROGRESS count),
  TUGAS_BERJALAN (COMPLETED count) as dashboard badge values.
```

### 4.3 Task Progression — ASSIGNED → IN_PROGRESS

```
PIC opens tugas-baru.php
         │
         ▼
SP_ALDA_PIC_GET_TASKS(@PIC_NIK, 'ASSIGNED')
  Joins ALDA_PENUGASAN to MASTER_ALDA on CONTRACT_NO.
  Applies NIK normalisation (dot-stripping) on both sides.
  Computes KENDARAAN composite field from MERK + TYPE + TAHUN.
  Applies ISNULL fallback to MASTER_ALDA for CUSTOMER_NAME,
  LEGAL_ADDRESS, CUSTOMER_PHONE, AMOUNT_TO_BE_PAID.
  Returns result set ordered by CREATED_AT DESC.
         │
         ▼
PIC taps "Proses" (POST: action = proses_tugas, penugasan_id = N)
  PHP validates penugasan_id with ctype_digit() and positivity check.
         │
         ▼
SP_ALDA_PIC_UPDATE_STATUS(@PENUGASAN_ID, @PIC_NIK, 'IN_PROGRESS')
  Verifies ownership: PENUGASAN_ID matches and PIC_NIK matches
  after dot-normalisation on both sides.
  Guards against no-op: rejects if current STATUS = @NEW_STATUS.
  INSERT into ALDA_PENUGASAN_HISTORY (CHANGE_TYPE = 'STATUS_CHANGE').
  UPDATE ALDA_PENUGASAN: STATUS = 'IN_PROGRESS', ASSIGN_VERSION++,
  UPDATED_AT = GETDATE(), UPDATED_BY = @PIC_NIK.
  Returns success BIT and message VARCHAR.
         │
         ▼
On success → redirect to tugas-proses.php with flash_success session message.
```

### 4.4 Reassignment — Back-Office

```
SP_ALDA_UPDATE_PIC(@usercreate, @pic_nik_new, @nomor_kontrak, @notes)
  Verifies the assignment exists and IS_FINAL = 0 for its current status.
  Resolves the new PIC's full attribute set from MASTER_ALDA_PIC.
  Refreshes the customer data snapshot from MASTER_ALDA.
  INSERT into ALDA_PENUGASAN_HISTORY (CHANGE_TYPE = 'REASSIGN').
  UPDATE ALDA_PENUGASAN: all PIC_ columns, all customer snapshot columns,
  STATUS reset to 'ASSIGNED', ASSIGN_VERSION++.
```

### 4.5 Cancellation — Back-Office

```
SP_ALDA_CANCEL_ASSIGN(@usercreate, @nomor_kontrak, @cancel_reason)
  Verifies the assignment exists.
  Consults ALDA_STATUS_REF.IS_FINAL — blocks cancellation of terminal states.
  INSERT into ALDA_PENUGASAN_HISTORY (CHANGE_TYPE = 'CANCEL').
  UPDATE ALDA_PENUGASAN: STATUS = 'CANCELLED', NOTES = @cancel_reason.
```

---

## 5. Authentication & Session Management

Authentication is executed entirely within `SP_LOGIN_RESURVEY_ALDA` via a three-stage validation sequence. The PHP application receives the result set and acts on the `LoginStatus` integer; it performs no independent credential logic.

**Stage 1 — Existence check:** The procedure queries `MASTER_ALDA_PIC` for the supplied `@p_NIK`. If no row is found, it returns `LoginStatus = -1` with the message *"NIK tidak terdaftar di sistem."*

**Stage 2 — Active status check:** If the NIK exists but `IS_ACTIVE = 0`, the procedure returns `LoginStatus = -1` with the message *"Akun Anda sudah tidak aktif."*, blocking login without disclosing whether the account exists.

**Stage 3 — Credential validation:** The supplied password is compared directly against the static string `'user.100'`. A match returns `LoginStatus = 1` along with the PIC's `NIK` and `NAMA`. A mismatch returns `LoginStatus = 0`.

On successful authentication, PHP writes three session variables:

| Variable | Value |
|---|---|
| `$_SESSION['user_logged_in']` | `true` |
| `$_SESSION['user_nik']` | PIC NIK from the stored procedure response |
| `$_SESSION['user_name']` | PIC full name from the stored procedure response |

Every protected page enforces a session guard at the file entry point, verifying both `user_logged_in === true` and the presence of `user_nik`. `dashboard.php` additionally re-validates the PIC's `IS_ACTIVE` status against `MASTER_ALDA_PIC` on every load. If the account has been deactivated since the session was established, the session is immediately destroyed and the user is redirected to `login.php`. Session teardown in `logout.php` calls `session_unset()` followed by `session_destroy()` before issuing the redirect.

> **Security note:** The password validation in `SP_LOGIN_RESURVEY_ALDA` relies on a plaintext static string. There is no per-user credential storage, hashing, or salting. This constitutes a high-risk vulnerability and must be replaced with a cryptographic hashing scheme prior to production deployment.

---

## 6. Module Reference

| File | Route | Auth Required | Description |
|---|---|---|---|
| `index.php` | `/` | No | Entry-point redirect. Authenticated users are forwarded to `dashboard.php`; all others to `login.php`. |
| `login.php` | `/login.php` | No | Renders the login form and processes POST submissions via `SP_LOGIN_RESURVEY_ALDA`. Establishes the session on a `LoginStatus = 1` response; renders inline error messages otherwise. |
| `dashboard.php` | `/dashboard.php` | Yes | Workload hub. Re-validates PIC session liveness against `MASTER_ALDA_PIC` on every load; fetches task badge counts via `SP_ALDA_PIC_TASKLIST_SUMMARY`; renders three primary navigation buttons with counts and two secondary buttons. |
| `tugas-baru.php` | `/tugas-baru.php` | Yes | Displays all `ASSIGNED` tasks for the PIC via `SP_ALDA_PIC_GET_TASKS`. Handles POST submissions for the **Proses** action, dispatching `SP_ALDA_PIC_UPDATE_STATUS` to transition the task to `IN_PROGRESS`. Provides a Detail modal populated via a JSON payload embedded in each task card. |
| `tugas-proses.php` | `/tugas-proses.php` | Yes | Displays all `IN_PROGRESS` tasks for the PIC via `SP_ALDA_PIC_GET_TASKS`. Read-only; no status transitions are exposed. Renders a flash success message from `$_SESSION['flash_success']` if present, then clears it. |
| `tugas-sedang-berjalan.php` | `/tugas-sedang-berjalan.php` | Yes | **Stub.** Intended to display `COMPLETED` tasks. Renders an empty-state placeholder; no data retrieval is implemented. |
| `selesai.php` | `/selesai.php` | Yes | **Stub.** Intended to display closed or finalised task history. Renders an empty-state placeholder. |
| `upload.php` | `/upload.php` | Yes | **Stub.** Intended to support evidence or document upload. Renders an empty-state placeholder. |
| `logout.php` | `/logout.php` | No | Calls `session_unset()` and `session_destroy()`, then redirects to `login.php`. |

---

## 7. Stored Procedure Catalogue

All procedures reside in the `[dbo]` schema of the `MOBILE_COLLECTION` database. Procedures that write data follow a consistent pattern: validate preconditions, insert a history record, then execute the mutation. All procedures return a `success BIT` and `message VARCHAR` result set on write operations to allow the application tier to surface contextual error messages without exposing internal state.

---

### `SP_LOGIN_RESURVEY_ALDA`

**Invoked by:** `login.php`

| Parameter | Type | Direction | Description |
|---|---|---|---|
| `@p_NIK` | `varchar(50)` | IN | Employee identification number submitted by the user. |
| `@p_Password` | `varchar(255)` | IN | Password submitted by the user. |

**Returns:** `LoginStatus INT`, `Message VARCHAR(255)`, `NIK VARCHAR(50)`, `NAMA VARCHAR(200)`

Executes the three-stage authentication sequence described in §5. Returns `LoginStatus = 1` on success with the PIC's NIK and full name populated; returns `-1` or `0` with a descriptive `Message` on failure, with `NIK` and `NAMA` set to `NULL`. Does not use transactions; the read-only nature of the operation makes them unnecessary.

---

### `SP_ALDA_PIC_TASKLIST_SUMMARY`

**Invoked by:** `dashboard.php`

| Parameter | Type | Direction | Description |
|---|---|---|---|
| `@PIC_NIK` | `varchar(50)` | IN | NIK of the PIC whose task counts are being requested. |

**Returns:** `TUGAS_BARU INT`, `TUGAS_PROSES INT`, `TUGAS_BERJALAN INT`

Issues a single conditional aggregation over `ALDA_PENUGASAN` filtered to the dot-normalised NIK. Maps `ASSIGNED` to `TUGAS_BARU`, `IN_PROGRESS` to `TUGAS_PROSES`, and `COMPLETED` to `TUGAS_BERJALAN`. Always returns exactly one row with zero values if no matching records exist. Used exclusively to populate the three badge counters on the PIC dashboard.

---

### `SP_ALDA_PIC_GET_TASKS`

**Invoked by:** `tugas-baru.php`, `tugas-proses.php`

| Parameter | Type | Direction | Description |
|---|---|---|---|
| `@PIC_NIK` | `varchar(50)` | IN | NIK of the PIC whose tasks are being retrieved. |
| `@STATUS` | `varchar(20)` | IN | Status filter; must be a valid value from `ALDA_PENUGASAN.STATUS` (e.g. `ASSIGNED`, `IN_PROGRESS`). |

**Returns:** Enriched task result set ordered by `CREATED_AT DESC`.

Joins `ALDA_PENUGASAN` to `MASTER_ALDA` on `CONTRACT_NO`. Applies `REPLACE(PIC_NIK, '.', '') = @normalised_nik` to ensure NIK formatting variants match correctly. Computes the composite `KENDARAAN` column by concatenating `MERK_KENDARAAN`, `TYPE_KENDARAAN`, and `TAHUN_KENDARAAN` with whitespace guards (see §9.2). Applies `ISNULL(P.field, A.field)` fallback for `CUSTOMER_NAME`, `LEGAL_ADDRESS`, `CUSTOMER_PHONE`, and `AMOUNT_TO_BE_PAID` to recover live master data when assignment snapshots are absent. Returns `CREATED_AT` aliased as `TANGGAL_ASSIGN` alongside all core task fields including `PENUGASAN_ID`, `CONTRACT_NO`, `STATUS`, `ASSIGN_VERSION`, and `NOTES`.

---

### `SP_ALDA_PIC_UPDATE_STATUS`

**Invoked by:** `tugas-baru.php` (POST handler)

| Parameter | Type | Direction | Description |
|---|---|---|---|
| `@PENUGASAN_ID` | `bigint` | IN | Surrogate key of the assignment record to be updated. |
| `@PIC_NIK` | `varchar(50)` | IN | NIK of the PIC submitting the status change; verified against the assignment record. |
| `@NEW_STATUS` | `varchar(20)` | IN | Target status value; the only transition exposed by the PIC UI is `IN_PROGRESS`. |

**Returns:** `success BIT`, `message VARCHAR(500)`

Queries `ALDA_PENUGASAN` by `PENUGASAN_ID` with a dot-normalised NIK match to verify both record existence and PIC ownership simultaneously. Rejects the operation if the record is not found or if the current status already equals `@NEW_STATUS`. On a valid request, increments `ASSIGN_VERSION` to `ISNULL(existing_ver, 0) + 1`, inserts a `STATUS_CHANGE` record into `ALDA_PENUGASAN_HISTORY` capturing the full before/after state, and updates `ALDA_PENUGASAN.STATUS`, `ASSIGN_VERSION`, `UPDATED_AT`, and `UPDATED_BY`. Returns `success = 1` and a confirmation message on success; returns `success = 0` with a descriptive message on any failure, including those caught by `TRY/CATCH`.

---

### `SP_ALDA_SUBMIT_ASSIGN`

**Invoked by:** Back-office interface

| Parameter | Type | Direction | Description |
|---|---|---|---|
| `@usercreate` | `varchar(200)` | IN | Identity of the operator creating or updating the assignment. |
| `@pic_nik` | `varchar(50)` | IN | NIK of the PIC to whom the contract is being assigned. |
| `@nomor_kontrak` | `varchar(50)` | IN | Contract number from `MASTER_ALDA` to be assigned. |
| `@notes` | `varchar(500)` | IN | Optional annotation; defaults to `'Assigned via Web'` if absent. |

**Returns:** `success BIT`, `message VARCHAR(500)`, `submission_id BIGINT`

Validates that the contract exists in `MASTER_ALDA` and that the target PIC exists in `MASTER_ALDA_PIC`. Generates `SUBMISSION_ID` by formatting `GETDATE()` as `yyyyMMddHHmmssmmm` and casting to `BIGINT`. Resolves the full PIC attribute set from `MASTER_ALDA_PIC` and the full customer snapshot from `MASTER_ALDA`. If no prior assignment record exists, inserts a new row with `STATUS = 'ASSIGNED'` and `ASSIGN_VERSION = 1`. If a prior record exists in a non-final or `CANCELLED` state, inserts a `REASSIGN` history entry and updates the existing record in-place. Blocks reassignment if `ALDA_STATUS_REF.IS_FINAL = 1` for the current status.

---

### `SP_ALDA_CANCEL_ASSIGN`

**Invoked by:** Back-office interface

| Parameter | Type | Direction | Description |
|---|---|---|---|
| `@usercreate` | `varchar(200)` | IN | Identity of the operator executing the cancellation. |
| `@nomor_kontrak` | `varchar(50)` | IN | Contract number of the assignment to be cancelled. |
| `@cancel_reason` | `varchar(500)` | IN | Optional cancellation rationale recorded in the history and notes fields. |

**Returns:** `success BIT`, `message VARCHAR(500)`

Retrieves the current assignment state for the supplied contract number. Returns a failure response if no assignment exists. Consults `ALDA_STATUS_REF.IS_FINAL` to block cancellation of terminal assignments. On a valid request, inserts a `CANCEL` record into `ALDA_PENUGASAN_HISTORY` with the full before-state of the assignment, then sets `STATUS = 'CANCELLED'` and records the cancellation reason in `NOTES`. Any exception is caught, logged to `dbo.ERROR_LOG`, and returned as a `success = 0` message.

---

### `SP_ALDA_UPDATE_PIC`

**Invoked by:** Back-office interface

| Parameter | Type | Direction | Description |
|---|---|---|---|
| `@usercreate` | `varchar(200)` | IN | Identity of the operator executing the reassignment. |
| `@pic_nik_new` | `varchar(200)` | IN | NIK of the incoming PIC to whom the assignment is being transferred. |
| `@nomor_kontrak` | `varchar(50)` | IN | Contract number of the assignment to be transferred. |
| `@notes` | `varchar(500)` | IN | Optional annotation; defaults to `'Perubahan PIC via Web'` if absent. |

**Returns:** `success BIT`, `message VARCHAR(500)`

Validates the existing assignment is present and not in a final state. Verifies the incoming PIC exists in `MASTER_ALDA_PIC` using dot-normalised NIK comparison. Resolves the full attribute set for the new PIC from `MASTER_ALDA_PIC` and refreshes all customer snapshot columns from `MASTER_ALDA`. Generates a new `SUBMISSION_ID` from the current timestamp. Inserts a `REASSIGN` record into `ALDA_PENUGASAN_HISTORY` capturing both the outgoing and incoming PIC attributes. Updates `ALDA_PENUGASAN` with all new PIC columns, the refreshed customer snapshot, resets `STATUS = 'ASSIGNED'`, and increments `ASSIGN_VERSION`. Any exception is caught, logged to `dbo.ERROR_LOG`, and surfaced as a failure message.

---

### `SP_ALDA_GET_PENUGASAN`

**Invoked by:** Back-office reporting and monitoring interface

| Parameter | Type | Direction | Description |
|---|---|---|---|
| `@BRANCH_ID` | `varchar(10)` | IN | Mandatory branch filter; restricts results to contracts belonging to the specified branch. |
| `@NOMOR_KONTRAK` | `varchar(50)` | IN | Optional contract number filter; pass an empty string to return all contracts. |
| `@STATUS` | `varchar(20)` | IN | Optional status filter; pass an empty string to return all statuses. |
| `@PIC_NIK` | `varchar(50)` | IN | Optional PIC NIK filter with dot-normalisation; pass an empty string to return all PICs. |
| `@DATE_FROM` | `datetime` | IN | Optional lower bound on `CREATED_AT`; pass `NULL` for no lower bound. |
| `@DATE_TO` | `datetime` | IN | Optional upper bound on `CREATED_AT` (inclusive, using `DATEADD(DAY, 1, …)`); pass `NULL` for no upper bound. |

**Returns:** Full assignment record set including all `ALDA_PENUGASAN` columns, `ISNULL`-resolved customer fields from `MASTER_ALDA`, `ALDA_STATUS_REF.STATUS_LABEL` as `ASSIGN_STATUS_LABEL`, and both `MASTER_ALDA.CONTRACT_STATUS` and `MASTER_ALDA.AMOUNT_TO_BE_PAID` as unresolved master-data reference columns. Results are ordered by `CREATED_AT DESC`.

---

### `SP_ALDA_TASKLIST_PENUGASAN`

**Invoked by:** Back-office assignment interface

| Parameter | Type | Direction | Description |
|---|---|---|---|
| `@BRANCH_ID` | `varchar(10)` | IN | Mandatory branch filter; restricts results to contracts within the specified branch. |
| `@NOMOR_KONTRAK` | `varchar(50)` | IN | Optional contract number filter; pass an empty string to return all. |
| `@CONTRACT_STATUS` | `varchar(20)` | IN | Optional contract status filter against `MASTER_ALDA.CONTRACT_STATUS`; pass an empty string to return all. |
| `@PORTFOLIO` | `varchar(20)` | IN | Optional portfolio filter; pass an empty string to return all. |

**Returns:** Columns from `MASTER_ALDA` for contracts eligible to receive a new assignment, ordered by `NOMOR_KONTRAK`.

Queries `MASTER_ALDA` with a `LEFT JOIN` to `ALDA_PENUGASAN` and `ALDA_STATUS_REF`. The eligibility condition returns contracts where: no assignment row exists, the existing assignment is `CANCELLED`, or the existing assignment carries `IS_FINAL = 1`. This ensures contracts with active non-final assignments are excluded from the picker, enforcing the one-active-assignment-per-contract rule at the query level.

---

### `SP_ALDA_RECORD_PENUGASAN`

**Invoked by:** Back-office operational reporting interface

| Parameter | Type | Direction | Description |
|---|---|---|---|
| `@BRANCH_ID` | `varchar(10)` | IN | Mandatory branch filter. |
| `@NOMOR_KONTRAK` | `varchar(50)` | IN | Optional contract number filter; pass an empty string to return all. |
| `@PIC_NIK` | `varchar(50)` | IN | Optional PIC NIK filter with dot-normalisation; pass an empty string to return all. |
| `@DATE_FROM` | `datetime` | IN | Optional lower bound on `CREATED_AT`; pass `NULL` for no lower bound. |
| `@DATE_TO` | `datetime` | IN | Optional upper bound on `CREATED_AT` (inclusive); pass `NULL` for no upper bound. |

**Returns:** Consolidated operational report columns including `NOMOR_KONTRAK`, `PIC_NIK`, `PIC` (name), `JABATAN_PIC`, `LOKASI_PIC`, `CABANG`, `PORTFOLIO`, `UNIT` (vehicle type), `AMOUNT_TO_BE_PAID`, `CUSTOMER_NAME`, `ASSIGN_STATUS`, `CREATED_DATE` (formatted as `DD-MM-YYYY` via `CONVERT(VARCHAR(10), …, 105)`), and `CREATED_BY`. Excludes `CANCELLED` assignments. Ordered by `CREATED_AT DESC`.

---

### `SP_ALDA_DROPDOWN_PIC`

**Invoked by:** Back-office assignment and reassignment interface

| Parameter | Type | Direction | Description |
|---|---|---|---|
| `@BRANCH_ID` | `varchar(10)` | IN | Branch identifier; restricts the result set to PICs belonging to that branch. |

**Returns:** `VALUE` (`NIK`), `DATA_PIC` (`NAMA`), `JABATAN`, `LOKASI` (`LOKASI_FISIK`) for all PICs matching the supplied branch. No active-status filter is applied within the procedure; the back-office interface is responsible for any additional filtering.

---

## 8. Task Lifecycle & Status Model

Tasks progress through a defined, unidirectional state machine. Transition logic is enforced exclusively at the database tier; the application tier submits target status values and acts on the stored procedure's response.

```
  MASTER_ALDA (no active assignment)
            │
            │  SP_ALDA_SUBMIT_ASSIGN
            ▼
       ┌──────────┐
       │ ASSIGNED │ ◄─── SP_ALDA_SUBMIT_ASSIGN  (re-assign after CANCELLED)
       │          │ ◄─── SP_ALDA_UPDATE_PIC     (PIC transfer; resets to ASSIGNED)
       └────┬─────┘
            │  SP_ALDA_PIC_UPDATE_STATUS('IN_PROGRESS')
            │  [PIC action via tugas-baru.php]
            ▼
     ┌─────────────┐
     │ IN_PROGRESS │
     └──────┬──────┘
            │  SP_ALDA_PIC_UPDATE_STATUS('COMPLETED')
            │  [back-office or future tugas-proses.php action]
            ▼
      ┌───────────┐
      │ COMPLETED │  IS_FINAL = 1 · No further transitions permitted.
      └───────────┘

  From ASSIGNED or IN_PROGRESS:
            │  SP_ALDA_CANCEL_ASSIGN
            ▼
      ┌───────────┐
      │ CANCELLED │  IS_FINAL = 0 · Eligible for reassignment via SP_ALDA_SUBMIT_ASSIGN.
      └───────────┘
```

The `IS_FINAL` flag in `ALDA_STATUS_REF` is the authoritative guard for all mutation operations. `SP_ALDA_CANCEL_ASSIGN`, `SP_ALDA_UPDATE_PIC`, and `SP_ALDA_SUBMIT_ASSIGN` all query this table before executing any write. `CANCELLED` carries `IS_FINAL = 0` by design, permitting the contract to re-enter the assignment workflow without requiring manual data correction. The PIC-facing application currently exposes only the `ASSIGNED → IN_PROGRESS` transition; the `IN_PROGRESS → COMPLETED` path exists at the database level but has no corresponding UI in the current release.

---

## 9. Data Normalisation & Enrichment

### 9.1 NIK Normalisation

NIK values may be stored with or without dot separators (e.g. `123.456.789` versus `123456789`). All stored procedures that filter or compare NIK values apply `REPLACE(NIK, '.', '')` to both the stored value and the supplied parameter before comparison. This normalisation is applied consistently across all eleven procedures to eliminate false mismatches caused by formatting inconsistency in source data or user input.

### 9.2 KENDARAAN Composite Field

The vehicle description displayed on task cards is computed at query time within `SP_ALDA_PIC_GET_TASKS` by concatenating three discrete columns from `ALDA_PENUGASAN`:

```sql
LTRIM(RTRIM(
    ISNULL(P.MERK_KENDARAAN, '')
    + CASE WHEN P.TYPE_KENDARAAN IS NOT NULL
             AND LTRIM(RTRIM(P.TYPE_KENDARAAN)) <> ''
           THEN ' ' + LTRIM(RTRIM(P.TYPE_KENDARAAN)) ELSE '' END
    + CASE WHEN P.TAHUN_KENDARAAN IS NOT NULL
           THEN ' ' + CAST(P.TAHUN_KENDARAAN AS VARCHAR(4)) ELSE '' END
)) AS KENDARAAN
```

The result is a whitespace-safe, human-readable vehicle label (e.g. `Suzuki Ertiga 2021`). The PHP rendering layer applies a secondary guard — `trim((string) $task['KENDARAAN']) !== ''` — before rendering the vehicle row, suppressing empty output when all three source columns are `NULL`.

### 9.3 ISNULL Fallback to Master Data

When an `ALDA_PENUGASAN` snapshot column is `NULL` — either because the assignment predates the denormalisation strategy or because the column was not populated at the time of insertion — `SP_ALDA_PIC_GET_TASKS` resolves the live value from `MASTER_ALDA` transparently:

| Assignment Column | Fallback Source |
|---|---|
| `P.CUSTOMER_NAME` | `A.CUSTOMER_NAME` |
| `P.LEGAL_ADDRESS` | `A.LEGAL_ADDRESS` |
| `P.CUSTOMER_PHONE` | `A.CUSTOMER_PHONE` |
| `P.AMOUNT_TO_BE_PAID` | `A.AMOUNT_TO_BE_PAID` |

This ensures task cards always render complete information without requiring a backfill of historical assignment records.

### 9.4 Rupiah Formatting

The PHP helper `formatRupiah()` converts a raw `decimal(18,2)` database value to an Indonesian Rupiah string for display:

```php
function formatRupiah(mixed $angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}
```

This transformation is applied at the presentation layer only. The raw numeric value is preserved separately in the JSON payload embedded in each task card's `onclick` attribute, ensuring the modal display is consistent with the card display without a second database call.

### 9.5 DateTime Handling

The `sqlsrv` extension returns SQL `datetime` columns as PHP `DateTime` objects. All template code applies an `instanceof DateTime` guard before invoking `->format()` and falls back to `'-'` when the column value is `NULL`, preventing fatal errors on records with absent timestamps.

---

## 10. Security Posture

| Vector | Implementation |
|---|---|
| **Session guards** | Every protected page verifies `$_SESSION['user_logged_in'] === true` and the presence of `$_SESSION['user_nik']` at file entry. Non-compliant requests are redirected immediately without processing further input. |
| **Mid-session deactivation** | `dashboard.php` re-queries `MASTER_ALDA_PIC.IS_ACTIVE` on every page load. A deactivated account triggers immediate session destruction and redirect, regardless of session validity. |
| **Input validation** | `penugasan_id` from POST submissions is validated using `ctype_digit()` combined with a positivity check prior to any database dispatch. Invalid values are rejected with an error message and no stored procedure is called. |
| **SQL injection prevention** | All database interactions use parameterised stored procedure calls via `sqlsrv_query()` with a typed `$params` array. No string interpolation into SQL occurs anywhere in the application. |
| **Output escaping** | All user-supplied and database-sourced strings passed to HTML output are processed through `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` before rendering. |
| **JSON modal payload** | Task card payloads are serialised with `json_encode()` server-side and injected into `onclick` attributes via `htmlspecialchars()`, preventing HTML injection through crafted contract or customer data. |
| **Error detail suppression** | Stored procedure exceptions are caught within `TRY/CATCH` blocks and logged to `dbo.ERROR_LOG` with procedure name and line number. Only generic messages are returned to the application tier; internal schema or stack details are never exposed to the client. |
| **High-risk vulnerability** | `SP_LOGIN_RESURVEY_ALDA` validates credentials against the hardcoded plaintext string `'user.100'` with no per-user credential storage, hashing, or salting. This must be remediated with a cryptographic hashing scheme before the system is exposed to any non-development environment. |

---

## 11. Technical Dependencies

| Component | Specification | Notes |
|---|---|---|
| **PHP Runtime** | 8.3.x | `declare(strict_types=1)` is applied on files that perform database operations. |
| **PHP Extensions** | `sqlsrv`, `pdo_sqlsrv` | Required for all SQL Server connectivity via the Microsoft ODBC driver. |
| **Database** | SQL Server 2008+ — `MOBILE_COLLECTION` | All queries and stored procedures are compatible with SQL Server 2008 T-SQL. `TRY_CAST` and `TRY_CONVERT` are not used; `ISNUMERIC` equivalents or integer casting guards are applied where necessary. |
| **Database connection** | `config/connection.php` | Must reside one directory above the application root and export a valid `$conn` resource via `sqlsrv_connect()`. |
| **SVG assets** | `assets/icons/*.svg` | Consumed by the `svgIcon()` helper present in every PHP file. Missing icons degrade gracefully to an empty `<svg>` element. |
| **CSS** | `assets/css/styles.css` | Single stylesheet serving all pages; no pre-processor or build step. |
| **JavaScript** | Vanilla ES5+ | Used solely for modal open/close, password toggle, and form submission guards. No external libraries, frameworks, or build pipeline. |
| **Composer / npm** | Not required | The application has no package manager dependencies and is deployable as a flat directory of PHP files. |

---

## 12. System Constraints & Stub Modules

Three routes are fully scaffolded with session guards, navigation, and empty-state UI, but contain no functional data retrieval or business logic:

| Module | Intended Function | Current State |
|---|---|---|
| `tugas-sedang-berjalan.php` | Display `COMPLETED` tasks for the PIC. | Stub. The data layer is ready — `SP_ALDA_PIC_TASKLIST_SUMMARY` already counts `COMPLETED` tasks as `TUGAS_BERJALAN` and `SP_ALDA_PIC_GET_TASKS` accepts `'COMPLETED'` as a valid status parameter. Only the view layer requires implementation. |
| `selesai.php` | Display finalised task history or closed contract records. | Stub. No stored procedure for this view has been identified in the current codebase. |
| `upload.php` | Upload survey evidence, photographs, or supporting documentation. | Stub. No file handling logic, stored procedure, or database schema for attachments exists in the current codebase. |

The `IN_PROGRESS → COMPLETED` status transition is supported by `SP_ALDA_PIC_UPDATE_STATUS` and requires only a completion action in `tugas-proses.php` — analogous to the **Proses** button in `tugas-baru.php` — to be fully operational.

---

## 13. Deployment Runbook

### Step 1 — Database Connection

Provision `config/connection.php` one directory above the application web root. The file must expose a `$conn` resource via `sqlsrv_connect()` targeting the `MOBILE_COLLECTION` database. The application checks `isset($conn)` in `login.php` and will halt with a diagnostic message if the resource is absent.

### Step 2 — Stored Procedure Deployment

Execute all eleven `.sql` files within the `MOBILE_COLLECTION` database under the `[dbo]` schema. The provided scripts use `ALTER PROCEDURE` syntax; replace `ALTER` with `CREATE` for initial deployment on a clean database. Execution order is not significant as there are no cross-procedure dependencies at the DDL level.

### Step 3 — Reference Data Seeding

Insert the four operational status codes into `ALDA_STATUS_REF` before any assignment operations are executed:

| `STATUS_CODE` | `STATUS_LABEL` | `IS_FINAL` | Notes |
|---|---|---|---|
| `ASSIGNED` | Tugas Baru | `0` | Initial state after assignment or reassignment. |
| `IN_PROGRESS` | Sedang Diproses | `0` | PIC has accepted and is actively processing the task. |
| `COMPLETED` | Selesai | `1` | Terminal state; no further transitions permitted. |
| `CANCELLED` | Dibatalkan | `0` | Soft-terminal; the contract remains eligible for reassignment. |

### Step 4 — Asset Integrity

Confirm that `assets/icons/` contains all SVG files referenced by `svgIcon()` calls across the application. The helper degrades gracefully to an empty `<svg>` element if a file is absent, but navigation icons and empty-state graphics will not render.

### Step 5 — Error Logging Permissions

Verify that the SQL Server login used by the PHP connection holds `INSERT` permission on `dbo.ERROR_LOG`. The `TRY/CATCH` blocks in `SP_ALDA_CANCEL_ASSIGN`, `SP_ALDA_UPDATE_PIC`, and `SP_ALDA_SUBMIT_ASSIGN` write to this table on exception. Absent permissions will cause silent failure of the catch block without surfacing the original error to the operator.

### Step 6 — Session Configuration

The application uses PHP's default file-based session handler. In multi-server deployments, configure a shared session store — such as a Redis instance or a database-backed handler — to prevent session loss on server rotation. The session isolation requirements are minimal: only three variables (`user_logged_in`, `user_nik`, `user_name`) plus an optional `flash_success` message are written per session.