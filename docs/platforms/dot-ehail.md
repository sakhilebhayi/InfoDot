# Dot.Ehail — Ride-Hailing and Transport Platform

**Role:** The Mobility and Transport Layer of the Dot Ecosystem  
**URL:** `ehail.infodot.app`  
**Tagline:** Move people. Move goods. Move smarter.

---

## Vision

Dot.Ehail is not just a ride-hailing app. It is the **intelligent mobility platform** for the Dot ecosystem — connecting passengers, drivers, and fleet operators in a real-time transport marketplace while feeding every trip's data back into the operational intelligence of Dot.Analytics.

Where Uber and Bolt operate as standalone platforms, Dot.Ehail integrates with Dot.Fleet for operator vehicle management, Dot.Finance for driver payouts and corporate billing, Dot.Agents for dispatch automation, and Dot.Engage for corporate account management. For transport companies and mining operations already using Dot.Fleet, Ehail adds a passenger and goods booking layer on top of their existing fleet data.

---

## Architecture

```
Dot Ecosystem

├── Dot.Ehail
│   ├── Passenger App (web + mobile web)
│   ├── Driver App (web + mobile web)
│   ├── Dispatcher Console
│   ├── Corporate Booking Portal
│   ├── Real-time Trip Engine (Reverb)
│   └── Settlement and Payout Engine
│
└── Shared: PostgreSQL · Redis · Reverb (real-time) · S3 · Stripe (Cashier)
        + Mapping: Leaflet + OpenStreetMap / Google Maps API
```

---

## Service Types

| Service | Description |
|---|---|
| On-demand ride | Passenger requests a vehicle immediately |
| Scheduled ride | Passenger books for a future date and time |
| Shared ride | Multiple passengers on a pooled route |
| Corporate ride | Billed to a company account, not the passenger |
| Parcel delivery | Small goods transport between two points |
| Long-haul | Inter-city transport with pre-approved route and price |
| Charter | Block booking of a vehicle for an event or business use |
| Shuttle | Fixed-route service with pre-set stops and schedule |

---

## Passenger Experience

### Booking flow
1. Open Dot.Ehail, enter destination
2. View available vehicle types and estimated prices
3. Select vehicle type and confirm pickup location
4. Match to nearest available driver
5. Real-time driver location on map
6. Trip tracking with ETA updates
7. Payment captured automatically on trip completion
8. Rate trip and driver

### Vehicle types
```
Economy      Standard      Premium      XL / Minibus
Motorbike    Electric      Accessible   Parcel van
```

### Fare calculation
- Base fare + per-km rate + per-minute rate (when slow)
- Surge pricing: dynamic multiplier when demand exceeds supply
- Fixed fares for known routes (airport runs, corporate shuttles)
- Corporate flat rates negotiated per account

---

## Driver Experience

### Driver onboarding
- Registration with license, vehicle, and insurance verification
- Background check integration hook (third-party service)
- Vehicle inspection photos uploaded via Dot.Files
- Training module completion tracked

### Driver app
- Online / offline toggle
- Incoming trip requests with accept / decline
- Navigation integration (Leaflet + OpenStreetMap, Google Maps API optional)
- Trip history and earnings dashboard
- Documents and compliance reminders
- In-app support and dispute initiation

### Driver earnings
- Earnings per trip displayed immediately after completion
- Weekly or daily payout via Stripe (configurable)
- Incentive bonuses: peak hours, acceptance rate, rating milestones
- Deductions: platform commission, tolls, cancellation penalties

---

## Dispatcher Console

For operators managing fleets of drivers (taxi companies, shuttle services, corporate fleets).

- Real-time map showing all active drivers and trips
- Manual dispatch: assign specific driver to a specific booking
- Zone management: define service areas and restrict drivers to zones
- Demand heat map: current booking density by area
- Driver performance: acceptance rate, rating, earnings, idle time
- Trip override: modify route, fare, or assignment on active trip

---

## Corporate Accounts

Organisations managing employee transport are served through a dedicated corporate portal.

- Monthly invoiced billing: no individual payment per trip
- Central booking by travellers or a designated travel coordinator
- Travel policy enforcement: allowed vehicle types, spending limits, approved hours
- Cost centre allocation per trip
- Monthly statement integration with Dot.Finance
- Dot.Agents automation: recurring pickup scheduling, calendar integration

---

## Real-time Trip Engine

The trip lifecycle is event-driven and transmitted in real time via Laravel Reverb.

```
Events:
  driver.accepted      → passenger sees driver en route
  driver.arrived       → passenger notified of arrival
  trip.started         → fare meter begins
  trip.locationUpdate  → passenger map updates every 5 seconds
  trip.completed       → fare calculated and charged
  rating.submitted     → driver and passenger profiles updated
```

All events are logged for audit, dispute resolution, and analytics.

---

## Safety Features

- SOS button on passenger app: sends GPS location to emergency contact and support
- Trip sharing: send live trip link to a contact
- Driver identity verification on each trip start
- Panic alert: notifies Dot.Ehail support and can alert local emergency services
- Incident reporting: in-app workflow for accidents and complaints
- Driver rating and deactivation threshold: drivers below 4.0 flagged for review

---

## Ecosystem Integration

| Platform | Integration |
|---|---|
| Dot.Fleet | Vehicles registered in Dot.Fleet appear as available in Dot.Ehail |
| Dot.Finance | Driver payouts, corporate invoices, and revenue processed here |
| Dot.Engage | Corporate accounts managed as CRM contacts |
| Dot.Agents | Scheduled pickups, driver reminders, and dispatch automation |
| Dot.Analytics | Trip volume, revenue per zone, driver efficiency, demand patterns |
| InfoDot | Passenger and driver identity via shared users table |

---

## Revenue Model

| Stream | Description |
|---|---|
| Commission per trip | Percentage of trip fare retained by platform |
| Corporate subscription | Monthly fee for corporate portal access |
| Driver subscription | Optional premium driver tools (earnings booster, priority dispatch) |
| Data intelligence | Anonymised mobility data reports for city planning and enterprise |
| White-label licence | Custom-branded Dot.Ehail for transport operators |
