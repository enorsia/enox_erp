# 🚀 Laravel Horizontal Scaling Architecture (Pre-Kubernetes Guide)

## 🧠 Core Principle

> Only **stateless services scale horizontally**
> Stateful services must be **shared / external**

---

## ✅ 1. External Services (Shared Across Servers)

These must live **outside your app containers** and be accessible from all servers.

### 🗄️ Database (MySQL)

* Always external in production
* Single source of truth

**Options:**

* Managed database (recommended)
* Dedicated DB server

❗ Do NOT run inside Docker in production

---

### ⚡ Redis (Critical)

* Shared across all app instances

**Used for:**

* Sessions ✅
* Cache ✅
* Queues ✅

❗ If each server has its own Redis → sessions will break

---

### 📁 File Storage

* Must be shared across servers

❌ Wrong:

* Local `/storage` inside container

✅ Correct:

* S3 (recommended)
* Shared NFS

---

### 🌐 Load Balancer

* Entry point for all traffic

**Options:**

* Nginx (simple setup)
* Cloud Load Balancer (better)

---

## 🔁 2. Internal Services (Per Server)

These run on each server and can be replicated.

---

### 🧩 App (Laravel / PHP-FPM)

* Stateless
* Horizontally scalable

```
Server 1 → app
Server 2 → app
Server 3 → app
```

---

### 🛠️ Queue Worker

* Same codebase as app
* Processes jobs

**Scaling example:**

* 2 workers on one server
* 10 workers on another

---

### 🌐 Nginx (Optional Internal)

**Two patterns:**

**Option A (Simple):**

* Nginx runs on each server
* External load balancer distributes traffic

**Option B (Cleaner):**

* Central load balancer
* App containers behind it

---

## ❌ 3. What Should NOT Be Internal

### ❌ MySQL per server

* Causes data inconsistency

### ❌ Redis per server

* Breaks session handling

---

## 🧱 4. Final Architecture (Industry Standard)

```
                Load Balancer
                      |
        ---------------------------------
        |               |               |
     Server 1        Server 2        Server 3
     --------        --------        --------
     Nginx           Nginx           Nginx
     App             App             App
     Worker          Worker          Worker

            |           |           |
            ----------- Shared -----------

                Redis (external)
                MySQL (external)
                Storage (S3)
```

---

## ⚖️ 5. Dev vs Production

### 🧪 Development Environment

✔ OK to include:

* MySQL container
* Redis container
* Nginx container

👉 All-in-one for convenience

---

### 🚀 Production Environment

❌ Remove:

* `db` container

✔ Keep:

* app
* worker

✔ Use external:

* Database
* Redis
* Storage

---

## 🔑 6. Golden Rules

### Rule 1

App must be **stateless**

### Rule 2

Anything shared → **external**

### Rule 3

Scaling = add more app instances, NOT database

### Rule 4

Sessions must NOT live inside app container

---

## ⚠️ 7. Reality Check

> “When a new server comes, it auto scales”

✔ You can manually add servers easily
❌ Auto-scaling is NOT available yet

**Auto-scaling requires:**

* Orchestration
* Health checks
* Metrics

---

## 🧭 8. Immediate Action Steps

### Step 1

Remove database service from production `docker-compose`

### Step 2

Use external services:

```
DB_HOST=external-db
REDIS_HOST=external-redis
```

### Step 3

Ensure configuration:

```
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
```

---

## ✔️ Final Service Separation

| Service | Internal     | External          |
| ------- | ------------ | ----------------- |
| App     | ✅            | ❌                 |
| Worker  | ✅            | ❌                 |
| Nginx   | ✅ (optional) | ✅ (Load Balancer) |
| Redis   | ❌            | ✅                 |
| MySQL   | ❌            | ✅                 |
| Storage | ❌            | ✅                 |

---

## 📌 Summary

* Scale **app and worker only**
* Keep **DB, Redis, Storage external**
* Make app **stateless**
* Use Redis for **sessions and queues**
* Prepare for orchestration later (e.g., Kubernetes)

---
