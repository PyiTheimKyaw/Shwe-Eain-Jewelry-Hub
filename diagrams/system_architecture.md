# Shwe Eain Jewelry Hub — System Architecture

This document describes the high-level architecture of the Jewelry Hub platform: mobile clients (Flutter), admin web (Filament), REST API (Laravel), persistence (MySQL), real-time delivery (Firebase), and media storage.

---

## 1. High-level system context

```mermaid
flowchart TB
  subgraph clients [Client Layer]
    CustomerApp[Flutter Customer App]
    ArtisanApp[Flutter Artisan App]
    AdminMobile[Flutter Admin Shell optional]
    AdminWeb[Filament Admin Web]
  end

  subgraph platform [Application Platform]
    API[Laravel REST API v1]
    Filament[Filament Admin Panel]
    Services[Domain Services]
  end

  subgraph data [Data & Realtime]
    MySQL[(MySQL)]
    Firestore[(Cloud Firestore)]
    Storage[File Storage public disk]
    FCM[Firebase Cloud Messaging]
  end

  CustomerApp -->|HTTPS JSON API| API
  ArtisanApp -->|HTTPS JSON API| API
  AdminMobile -->|HTTPS JSON API| API
  AdminWeb -->|Session HTTP| Filament
  Filament --> Services
  API --> Services
  Services --> MySQL
  Services --> Storage
  API -->|Chat bootstrap tokens| Firestore
  CustomerApp -->|Custom token + Firestore SDK| Firestore
  ArtisanApp -->|Custom token + Firestore SDK| Firestore
  AdminWeb -->|JS SDK optional| Firestore
  API --> FCM
  FCM --> CustomerApp
  FCM --> ArtisanApp
```

---

## 2. Logical architecture (roles and surfaces)

```mermaid
flowchart LR
  subgraph roles [User Roles]
    C[Customer]
    A[Artisan]
    AD[Admin]
  end

  subgraph mobile [Single Flutter Binary]
    Gate[Auth Gate / Role Router]
    CS[CustomerShell]
    AS[ArtisanShell]
    AMS[AdminShell]
  end

  subgraph web [Admin Web]
    F[Filament /admin]
  end

  C --> Gate
  A --> Gate
  AD --> Gate
  Gate --> CS
  Gate --> AS
  Gate --> AMS
  AD --> F
```

---

## 3. Backend layers (Laravel)

```mermaid
flowchart TB
  subgraph entry [HTTP Entry]
    Routes[api.php v1 + web admin]
  end

  subgraph app [Application]
    Controllers[API Controllers]
    FilamentR[Filament Resources Pages]
    Policies[Policies + Middleware]
  end

  subgraph domain [Domain Services]
    OLS[OrderLifecycleService]
    OBS[OrderBuilderService]
    QBS[QuoteBuilderService]
    PIS[InquiryPipelinePresenter]
    FIS[FirebaseInquiryService]
    PRS[PricingService]
    MES[MediaStorageService]
  end

  subgraph models [Persistence]
    Eloquent[Eloquent Models]
  end

  Routes --> Controllers
  Routes --> FilamentR
  Controllers --> Policies
  Controllers --> OLS
  Controllers --> QBS
  Controllers --> PIS
  Controllers --> MES
  FilamentR --> OLS
  FIS --> FirestoreExt[Firestore gRPC]
  Eloquent --> MySQL[(MySQL)]
  PRS --> GoldFX[Gold and FX Snapshots]
  FIS --> MySQL
```

---

## 4. Data flow: business truth vs real-time delivery

```mermaid
flowchart LR
  subgraph source [Source of Truth]
    MySQL[(MySQL)]
  end

  subgraph api [Laravel]
    API[REST API]
  end

  subgraph mobile [Flutter]
    UI[UI Screens]
  end

  subgraph rt [Realtime Channel]
    FS[(Firestore)]
  end

  MySQL -->|read write| API
  API -->|JSON| UI
  API -->|thread messages cards| FS
  UI -->|subscribe| FS
  API -->|pipeline meta| UI
  note1[Chat and card delivery on Firestore.]
  note2[Workflow and money on MySQL.]
```

MySQL holds formal state: inquiries, quotes, orders, payments, assignments, receipts, and pricing snapshots. Firestore delivers real-time chat, server-written cards, and thread metadata; the app can fall back to REST pipeline data when Firestore lags.

---

## 5. Order and quote lifecycle (bespoke)

```mermaid
stateDiagram-v2
  [*] --> Inquiry: customer creates
  Inquiry --> Quoted: admin publishes quote
  Quoted --> AwaitingDeposit: customer accepts
  AwaitingDeposit --> DepositConfirmed: payment verified
  DepositConfirmed --> InProduction: production starts
  InProduction --> PendingQC: ready for review
  PendingQC --> Ready: QC approved
  Ready --> Completed: collected or settled
  Quoted --> [*]: decline or close
  AwaitingDeposit --> [*]: supersede cancel paths
```

Business rules are enforced in Laravel services (`OrderBuilderService`, `OrderLifecycleService`); statuses in MySQL are authoritative.

---

## 6. External integrations

```mermaid
flowchart LR
  Laravel[Laravel Backend]
  MySQL[(MySQL)]
  FS[(Firestore)]
  Auth[FIREBASE_CUSTOM_TOKEN]
  FCM[FCM Push]
  Disk[Local or S3 compatible storage]

  Laravel --> MySQL
  Laravel --> FS
  Laravel --> Auth
  Laravel --> FCM
  Laravel --> Disk
```

---

## 7. Technology summary

| Layer | Technology |
|-------|------------|
| Customer & Artisan mobile | Flutter (Dart), Provider, Firebase SDK |
| Admin operations | Filament v3 on Laravel |
| API | Laravel REST ` /api/v1`, Sanctum bearer tokens |
| Database | MySQL |
| Real-time chat | Firebase Firestore |
| Push | Firebase Cloud Messaging |
| Pricing | MMK base; Gold + FX snapshots |

---

## Document maintenance

Update this diagram when adding new integrations (e.g., payment gateways, CDN), splitting apps, or changing the source-of-truth boundary between MySQL and Firestore.
