# RFQ Marketplace Service (Yii2)

A Request for Quotation (RFQ) backend service where:
- Users create material/service requests
- Companies browse requests and submit quotations
- Users accept/reject quotations (request becomes awarded)
- Both sides receive notifications and realtime banner events (WebSocket via Centrifugo)

---

## Tech Stack
- **Backend:** PHP **Yii2** (REST API)
- **DB:** MySQL
- **Auth:** JWT (Bearer token)
- **Realtime:** Centrifugo (WebSocket + HTTP publish API)
- **Notifications:** Stored in DB + realtime events for banner UI

---

## Features Implemented
### Auth
- Register (user/company)
- Login (JWT)
- `GET /v1/auth/me`

### Requests (User)
- Create request
- View my requests
- View request details
- Update request (open only)
- Cancel request

### Requests (Company)
- Browse open requests (optionally filtered)

### Quotations (Company)
- Submit quotation
- View my quotations
- Withdraw quotation (if still pending) *(if implemented)*
- Update quotation *(if implemented)*

### Quotations (User)
- View quotations for a request *(access controlled)*
- Accept quotation → request becomes **awarded**
- Reject quotation *(if implemented)*

### Notifications
- `request.created` → companies subscribed to category
- `quotation.created` → request owner user
- mark read/unread

### Public Explore
- Public endpoint to explore open requests per category:
  - `GET /v1/public/requests?category_id=1`

---

## Project Structure (Backend)
- `modules/v1/controllers` → API controllers
- `models` → ActiveRecord models
- `migrations` → DB schema migrations
- `components` → JWT auth helpers
- `services` → Notification/Realtime helpers *(if used)*

---
