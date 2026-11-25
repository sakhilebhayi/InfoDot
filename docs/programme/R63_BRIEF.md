# R-63 — Business Booking & Appointment Management (owner brief, stored verbatim)

Received 2026-11-25. The text below is the owner's brief exactly as given.

---

INFODOT — BUSINESS BOOKING & APPOINTMENT MANAGEMENT SYSTEM
1. Objective
Introduce a Business Booking & Appointment Management feature within InfoDot that allows businesses offering consultation, professional services, meetings, assessments, demonstrations, or any other appointment-based service to receive bookings directly through their InfoDot business profile.
The goal is to make InfoDot more than a business directory. It should become a platform where businesses can be discovered, contacted, and booked by other users and businesses without having to leave InfoDot.
This feature must be available to eligible businesses and accessible to all authenticated InfoDot users, subject to the business's booking settings.
2. Public Booking Button on Business Profiles
Add a prominent "Book an Appointment" button to the public business profile page.
Position it alongside the business's existing contact information, website links, business description, and other profile details.
When visitors select this button, open a dedicated booking experience for that business.
The booking experience should display:

* Business name, logo and relevant profile information.
* Services or appointment types offered.
* Appointment descriptions and expected durations.
* Available dates and times.
* Unavailable dates and times.
* Appointment duration and booking requirements.
* Any applicable consultation fees, where configured.
* Business location or online meeting options.
* Booking instructions and additional information.

The booking interface must be publicly visible so visitors can see when a business is available or fully booked, even before submitting a booking.
3. Public Availability Calendar
Build an interactive calendar that allows visitors to see the business's availability.
Use clear visual distinctions between:

* Available dates.
* Fully booked dates.
* Unavailable dates.
* Dates outside the business's booking window.
* Dates on which appointments are not offered.

When a visitor selects an available date, display the available appointment time slots.
Availability must update dynamically as bookings are created, modified, cancelled, or approved.
Important: Never display private customer information, the names of people who have booked, or confidential appointment details on the public calendar. Only show availability status.
4. Business Booking Management Dashboard
Every business must have access to a dedicated booking management area within its company workspace.
Business owners and authorised team members should be able to:

* View all incoming appointment requests.
* Accept or reject booking requests.
* Confirm, reschedule, or cancel appointments.
* View upcoming, completed, and cancelled appointments.
* Block specific dates and time periods.
* Set recurring working hours.
* Configure available appointment durations.
* Add breaks and buffer periods between appointments.
* Set minimum notice periods for bookings.
* Set how far in advance customers can book.
* Configure holidays and business closure dates.
* Manage multiple employees or service providers who offer appointments.
* Assign appointments to specific team members.
* Add internal notes to appointments.
* Export or view booking reports.

Provide calendar views for daily, weekly, and monthly appointments.
5. Services and Appointment Types
Allow businesses to configure the types of appointments they offer.
For example:

* Business consultations.
* Professional services.
* Client meetings.
* Product demonstrations.
* Site visits.
* Assessments.
* Training sessions.
* General enquiries.

Each appointment type should support configurable:

* Name.
* Description.
* Duration.
* Price or consultation fee, if applicable.
* Available team member or service provider.
* Booking availability.
* Online or physical meeting format.
* Preparation instructions.
* Booking approval requirements.

A business should be able to offer multiple appointment types with different availability and durations.
Do not force every business into a single appointment model. The system must be flexible enough to support different industries and service requirements.
6. Customer Booking Experience
When another user or business wants to book an appointment:

1. They visit the business's public InfoDot profile.
2. They select "Book an Appointment".
3. They choose the service or appointment type.
4. They view the public availability calendar.
5. They select an available date.
6. They choose an available time slot.
7. They provide the required booking information.
8. They review and submit their booking.
9. They receive a booking confirmation or pending-approval notification.

Collect only the information necessary for the appointment, such as:

* Name.
* Contact details.
* Business name, where applicable.
* Reason for the appointment.
* Additional information requested by the service provider.

Allow both individuals and businesses to make bookings.
If the booking requires approval, clearly communicate that the appointment is pending until the business confirms it.
7. Notifications and Communication
Integrate the booking system with InfoDot's existing notification infrastructure.
Notify the business when:

* A new booking is received.
* A booking is cancelled.
* A customer requests a reschedule.
* An appointment is approaching.

Notify the customer when:

* A booking is submitted.
* A booking is confirmed or rejected.
* An appointment is rescheduled.
* An appointment is cancelled.
* An appointment reminder is due.

Where supported by the existing infrastructure, provide email and in-platform notifications.
Include the appointment date, time, business details, appointment type, and relevant booking reference in notifications.
8. Booking Rules and Availability Protection
This is a critical part of the implementation.
The system must prevent:

* Double bookings.
* Two customers reserving the same available slot.
* Bookings outside configured business hours.
* Bookings on blocked dates.
* Bookings that conflict with existing appointments.
* Bookings that violate minimum notice or advance-booking rules.

Availability must be calculated on the backend, not merely displayed by the frontend.
Use appropriate database transactions, locking, and validation to ensure that simultaneous booking attempts cannot reserve the same slot.
All dates and times must be handled using the business's configured timezone, with correct conversion for customers viewing the calendar from other timezones.
9. Booking Settings and Business Control
Add a booking settings section where each business can:

* Enable or disable appointment bookings.
* Publish or temporarily hide its availability calendar.
* Choose whether bookings are automatically confirmed or require approval.
* Configure working hours.
* Configure appointment types and durations.
* Set cancellation and rescheduling rules.
* Configure booking notice periods.
* Set availability for individual team members.
* Add booking instructions and terms.

The business must retain control over its booking availability at all times.
When bookings are disabled, the public profile should no longer allow new appointments, but existing booking records must remain accessible to authorised business members.
10. Design and User Experience
The booking experience must feel like a natural part of InfoDot, not an external booking application embedded into the platform.
Design requirements:

* Premium, clean, modern interface.
* Simple calendar navigation.
* Clear available and unavailable date indicators.
* Responsive desktop, tablet, and mobile layouts.
* Mobile-first booking experience.
* Minimal steps between selecting a date and submitting a booking.
* Accessible colour contrast and clear status indicators.
* Smooth transitions and subtle animations.
* Consistent design language with the existing InfoDot platform.

The booking button should be visible and easy to find without overwhelming the business profile.
11. Security, Permissions and Data Isolation
Every booking must belong to the correct business workspace.
Enforce strict tenant isolation so that one business cannot access another business's private booking records.
Implement role-based permissions for business owners, administrators, managers, and authorised employees.
Customers must only be able to access their own booking information.
Public visitors may view availability but must never access private appointment records or customer information.
Protect all booking actions with appropriate authentication, authorisation, validation, rate limiting, and audit logging.
12. Architecture and Integration
Before implementation, audit the existing InfoDot architecture.
Inspect:

* Business profile structure.
* Company workspace and multi-company architecture.
* Existing authentication and authorisation.
* Tenant isolation.
* User and business relationships.
* Notification infrastructure.
* Existing calendar or scheduling functionality.
* Existing subscription and feature-entitlement system.

Reuse existing components and services wherever appropriate.
Do not introduce a separate authentication system or create duplicate business profiles.
The booking feature must integrate with the existing InfoDot business workspace and Dot ecosystem architecture.
If a booking feature entitlement or pricing control is required, integrate it with the central InfoDot feature-control system rather than introducing a separate billing mechanism.
13. Analytics and Reporting
Provide useful booking insights to businesses, including:

* Total bookings.
* Confirmed appointments.
* Pending requests.
* Cancelled appointments.
* Completed appointments.
* Most popular appointment types.
* Booking trends over time.
* Appointment utilisation.
* No-show rates, where tracked.

Only show meaningful metrics based on actual recorded data. Never populate dashboards with fabricated figures.
14. Implementation and Testing
Implement this feature end-to-end.
Do not deliver a visual-only interface or placeholder calendar.
Required testing must cover:

* Public availability visibility.
* Booking creation.
* Automatic confirmation.
* Manual approval.
* Booking rejection.
* Cancellation and rescheduling.
* Double-booking prevention.
* Timezone handling.
* Business working hours.
* Multiple appointment types.
* Multiple service providers.
* Tenant isolation.
* Role permissions.
* Notifications.
* Mobile responsiveness.
* Booking settings and availability controls.

Include database migrations, backend services, validation, frontend interfaces, automated tests, and appropriate audit logs.
Preserve all existing InfoDot functionality.
15. Definition of Success
The feature is complete when a business can publish its appointment availability on its InfoDot profile, customers and other businesses can view available dates and book appointments, and the business can manage its entire appointment schedule from its InfoDot workspace.
The experience should be simple enough that a small business can start accepting appointments without needing a separate booking website or third-party scheduling application.
