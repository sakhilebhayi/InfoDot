# Dot.Tutor — E-Learning and Education Platform

**Role:** The Knowledge Transfer and Learning Layer of the Dot Ecosystem  
**URL:** `tutor.infodot.app`  
**Tagline:** Learn anything. Teach anyone. Grow together.

---

## Vision

Dot.Tutor is not an LMS with video uploads. It is the **intelligent learning platform** that serves three audiences simultaneously: individual learners growing their skills, organisations upskilling their teams, and educators monetising their knowledge.

Where Udemy, Teachable, and Moodle treat education as content delivery, Dot.Tutor treats it as a continuous process. Learner progress feeds into Dot.Analytics to surface skill gaps. Course completion triggers certification events in InfoDot profiles. AI tutors powered by Dot.Agents answer questions and personalise learning paths. An organisation's HR team sees team competency maps updated in real time.

---

## Architecture

```
Dot Ecosystem

├── Dot.Tutor
│   ├── Course Builder
│   ├── Live Session Engine (Reverb)
│   ├── Learner Dashboard and Progress Tracker
│   ├── Assessment and Certification Engine
│   ├── Tutor Marketplace
│   └── Organisation Learning Portal
│
└── Shared: PostgreSQL · Redis · Reverb · S3 (media) · Stripe (Cashier) · Meilisearch
```

---

## Course Builder

Tutors and organisations create structured learning experiences.

### Course structure
```
Course
  └── Modules (themed sections)
        └── Lessons (individual learning units)
              ├── Video
              ├── Text (Dot.docs-style rich text)
              ├── Slideshow
              ├── Interactive quiz
              ├── Assignment (file submission)
              ├── Live session (scheduled)
              └── Discussion thread
```

### Lesson types

| Type | Description |
|---|---|
| Video lesson | Pre-recorded video with in-video quizzes and transcript |
| Text lesson | Rich text with embedded media, code blocks, and equations |
| Slideshow | Slide deck with narration track |
| Live session | Scheduled class via WebRTC or Reverb-powered stream |
| Quiz | Multiple choice, true/false, short answer, matching |
| Assignment | Learner submits a file or document for review |
| Project | Multi-step deliverable with rubric-based grading |
| Discussion | Moderated Q&A thread per lesson |

### Course settings
- Public (marketplace listing) or private (invite only / organisation)
- Free or paid (one-time or subscription)
- Prerequisites: require completion of another course
- Certificate: automatic on completion with configurable pass threshold
- Drip scheduling: release lessons over time rather than all at once

---

## AI Tutor

Every course has an AI tutor powered by Dot.Agents.

- Answers learner questions in natural language using course content as context
- Explains concepts in multiple ways when a learner signals confusion
- Generates practice questions on demand
- Recommends supplementary lessons based on quiz performance
- Provides personalised study schedule suggestions
- Available 24/7 via the course chat interface

---

## Live Sessions

Real-time classes and tutoring sessions.

- Scheduled sessions with calendar invites sent to enrolled learners
- Live streaming with chat, Q&A queue, reactions, and polls
- Screen sharing and whiteboard
- Breakout rooms for small group exercises
- Session recording stored in Dot.Files / S3
- Attendance tracking with automatic certificate credit

---

## Assessment and Certification

### Quiz and test engine
- Multiple question types: MCQ, true/false, short answer, drag-and-drop, code exercise
- Question bank with randomised selection per attempt
- Timed assessments with auto-submit on expiry
- Anti-cheating: tab-focus detection, randomised question order
- Instant feedback with explanations on incorrect answers
- Weighted scoring per question

### Certification
- Custom certificate templates with logo and signatory
- Unique certificate ID with public verification URL
- Expiry dates for certifications requiring renewal
- Certification appears on learner's InfoDot profile
- Organisation HR portal shows team certification status

---

## Tutor Marketplace

Individual educators publish and sell courses to the Dot ecosystem community.

### Tutor profile
- Biography, areas of expertise, credentials
- Rating and reviews from learners
- Courses offered with preview
- Booking availability for one-on-one sessions

### One-on-one tutoring
- Learner books a session from tutor's availability calendar
- Payment processed via Stripe at booking
- Session conducted via live session engine
- Session recording and notes shared post-session
- Follow-up resources attached to the booking

---

## Organisation Learning Portal

Corporate and enterprise teams manage learning for their workforce.

### Administrator view
- Enrol team members in courses (individual or group)
- Assign mandatory training with completion deadlines
- View team competency map: skills acquired vs required
- Compliance tracking: certifications current and expiring
- Custom learning paths: sequence of courses for a role

### Skill gap analysis (Dot.Analytics integration)
- Define required skills per role
- Compare against actual certifications in the team
- Surface recommended courses to close identified gaps
- Trend view: skill acquisition rate per team over time

---

## Ecosystem Integration

| Platform | Integration |
|---|---|
| InfoDot | Learner identity, certifications displayed on user profile |
| Dot.Agents | AI tutor powered by Dot.Agents; course completion triggers workflows |
| Dot.Analytics | Learning progress, skill gaps, course engagement feed EIP |
| Dot.Files | Course videos, assignments, and certificates stored in S3 |
| Dot.Finance | Tutor payouts and course revenue handled via Stripe integration |
| Dot.Engage | Course completions update contact skill records in CRM |
| Dot.Press | Course landing pages published on organisation website |

---

## Revenue Model

| Stream | Description |
|---|---|
| Marketplace commission | Platform takes a percentage of each paid course sale |
| Organisation subscription | Per-seat fee for enterprise learning portal |
| Tutor subscription | Monthly fee for advanced publishing and analytics tools |
| Live session booking fee | Small platform fee per one-on-one booking |
| Certificate verification API | Charged per verification lookup by third parties |
