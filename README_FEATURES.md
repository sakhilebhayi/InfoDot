# InfoDot - Comprehensive Feature Documentation

> **Status of this document (2026-10-28).** It dates from the original build and has not been kept in step with the code: the stack versions in the technology sections are out of date (for example Livewire is 3.x, not 2.12). The feature lists were spot-checked on 2026-10-28 against the models, views and routes; the lines for likes, the social graph and real-time notifications were corrected. `README.md` and `docs/programme/` hold the current, verified description.


**InfoDot** is a modern web platform designed for entrepreneurs and professionals to search, discover, and share practical business solutions and insights. It combines a knowledge-sharing platform with team collaboration features, enabling users to connect, contribute, and solve business challenges collaboratively.

---

## Table of Contents

1. [Core Features](#core-features)
2. [User Management & Authentication](#user-management--authentication)
3. [Content Management](#content-management)
4. [Social & Collaboration Features](#social--collaboration-features)
5. [Search & Discovery](#search--discovery)
6. [Team Management](#team-management)
7. [Technical Architecture](#technical-architecture)
8. [Getting Started](#getting-started)
9. [API & Integration](#api--integration)
10. [Database Schema](#database-schema)

---

## Core Features

### 1. **Solutions Hub**
Users can create, publish, and discover business solutions with detailed step-by-step guidance.

**Key Features:**
- **Solution Creation**: Post comprehensive solutions with multiple steps
- **Rich Content**: Support for solution titles, descriptions, tags, and duration estimates
- **Duration Tracking**: Specify how long solutions take to implement (hours, days, weeks, months, years, or infinite)
- **Step Management**: Break down solutions into actionable steps with headings and detailed instructions
- **Tags/Categories**: Organize solutions by tags for better discoverability
- **Full-Text Search**: Search solutions by title, description, or tag keywords
- **Solution Comments**: Community engagement through commenting on solutions
- **Solution Likes**: Users can like solutions to show appreciation and help rank content

**Database Models:** `Solutions`, `Steps`  
**Routes:** `/solutions`, `/solution/create`, `/solution/add`, `/solution/view/{id}`

---

### 2. **Questions & Answers**
A Q&A system allowing users to ask business-related questions and get community responses.

**Key Features:**
- **Ask Questions**: Post questions about business challenges and solutions
- **Question Title & Description**: Detailed question formulation with searchable text
- **Status Tracking**: Monitor question status (open, resolved, etc.)
- **Answer Comments**: Community members can respond with answers and advice
- **Likes**: Questions and Solutions can be liked (answers cannot be liked)
- **Search Questions**: Full-text search across question titles and descriptions
- **Question Ownership**: Users maintain profiles of their asked questions
- **Mark as Solved**: Solution seekers can mark questions as solved

**Database Models:** `Questions`, `Answer`, `Comment`, `Like`  
**Routes:** `/questions`, `/questions/ask`, `/question/add`, `/question/view/{qid}`

---

### 3. **User Profiles**
Comprehensive user profiles showcasing expertise, contributions, and social connections.

**Key Features:**
- **Profile Information**: Display user name, email, and profile photo
- **Gravatar Integration**: Automatically fetch user avatars from Gravatar using MD5 email hashing
- **Activity History**: View user's contributed solutions and asked questions
- **Associates/Connections**: Build professional networks by connecting with other users
- **Social Graph**: Follow and unfollow other users ("Associates"); follower and following lists are not built
- **Profile Editing**: Users can update personal information and settings
- **Profile Access Control**: Guest users cannot view profiles (redirected to login)

**Database Models:** `User`, `Associates`, `Followers`  
**Routes:** `/user/profile/edit`, `/user/profile/{id}`

---

## User Management & Authentication

### 1. **Authentication System**
Secure authentication powered by Laravel Jetstream and Fortify frameworks.

**Key Features:**
- **User Registration**: Sign up with email and password
- **Secure Login**: Password-protected account access with session management
- **Email Verification**: Verify email addresses during registration
- **Password Reset**: Self-service password recovery via email links
- **Logout**: Secure session termination
- **Remember Me**: Persistent session options for convenience
- **Two-Factor Authentication**: Optional 2FA support for enhanced security (disabled by default)
- **Password Confirmation**: Confirm password before sensitive actions

**Routes:** 
- Login: `/login` (Fortify-powered)
- Register: `/register` (Fortify-powered)
- Forgot Password: `/forgot-password`
- Reset Password: `/reset-password/{token}`
- Password Confirmation: `/confirm-password`
- Email Verification: `/email/verify`

---

### 2. **User Roles & Permissions**
Role-based access control system for managing user capabilities.

**Current Roles:**
- **Guest**: Public access to platform information
- **Authenticated User**: Full platform access including creation, comments, and collaboration
- **Team Leader**: Can manage team members and team-specific resources
- **Team Member**: Limited access to team resources

**Protected Routes:** All authenticated content requires verified email and active session

---

## Content Management

### 1. **Solutions Management**

**Creating Solutions:**
- Users can create new solutions via `/solution/create`
- Solutions include: title, description, tags, and duration
- Multi-step format allows breaking down complex processes
- Each step supports: heading and detailed body content
- Automatically linked to creator's user profile

**Viewing Solutions:**
- Public access to solution listings on `/solutions`
- Guest users can browse but cannot interact
- Authenticated users can comment, like, and share

**Solution Attributes:**
```
- solution_title (string)
- solution_description (longText)
- tags (string - comma-separated)
- duration (integer - time required)
- duration_type (enum: hours|days|weeks|months|years|infinite)
- steps (integer - number of steps)
- created_by (user_id)
- timestamps (created_at, updated_at)
```

---

### 2. **Questions Management**

**Creating Questions:**
- Users post questions via `/questions/ask`
- Includes: question title, detailed description, optional tags
- Status tracking for question lifecycle

**Question Attributes:**
```
- question (string - title)
- description (longText - detailed question)
- status (integer - tracking state)
- tags (optional for categorization)
- created_by (user_id)
- timestamps (created_at, updated_at)
```

**Question Lifecycle:**
- Open: New question awaiting answers
- Answered: Got response from community
- Solved: Solution found and marked complete
- Closed: Question resolved

---

### 3. **Comments System**

**Hierarchical Comments:**
- Parent-child comment structure for threaded discussions
- Comments are polymorphic (can attach to Solutions or Questions)
- Full-text searchable content
- Soft delete support (comments can be recovered)

**Comment Features:**
- **Comment Body**: Text content with full-text indexing
- **Threading**: Reply to comments creating discussion threads
- **User Attribution**: Each comment linked to specific user
- **Likes on Comments**: Vote on comment helpfulness
- **Chronological Order**: Comments ordered from oldest to newest

---

### 4. **Likes & Reactions**

**Polymorphic Likes System:**
- Like Solutions, Questions, or other Comments
- Soft deletes allow "unlike" functionality
- Track user engagement and content popularity
- Hierarchical support for nested reactions

**Like Attributes:**
```
- like (boolean - true/false)
- user_id (who liked)
- likable_type (Solution|Question|Comment)
- likable_id (which item was liked)
- timestamps (created_at, updated_at, deleted_at)
```

---

## Social & Collaboration Features

### 1. **User Connections (Associates)**

**Building Networks:**
- Connect with other professionals and entrepreneurs
- One-way or mutual relationships
- Soft-deleted connection history (can restore if needed)
- View associated users' profiles and contributions

**Benefits:**
- Discover content from trusted users
- Build professional networks
- Foster community relationships
- Track user growth and influence

---

### 2. **Follower System**

**Following Features:**
- Self-referencing follower relationships
- Track who follows you and who you follow
- Visible follower counts on profiles
- Foundation for social feeds (extendable feature)

**Database Structure:**
- `user_id`: The follower
- `following_id`: The user being followed
- Cascade delete support

---

### 3. **User Search**

**Full-Text Search on Users:**
- Search by name and email
- Find users to connect with or view profiles
- Full-text index for performance
- Case-insensitive matching

---

## Search & Discovery

### 1. **Universal Search**

The platform includes a sophisticated Livewire-powered search component with real-time filtering.

**Search Capabilities:**
- **Solutions Search**: Search by title, description, or tags
- **Questions Search**: Search by question text or description
- **Users Search**: Find users by name or email
- **Real-Time Results**: Results update as you type
- **Keyboard Navigation**: Navigate search results with arrow keys
- **Highlighting**: Visual indication of highlighted search result

**Search Routes:** `/solution-results` (results display page)

**Livewire Search Component Features:**
```
@livewire('search')
- Real-time query updates
- Dynamic filtering of Solutions, Questions, Users
- Keyboard navigation (up/down arrows)
- Highlight index tracking
- Result clearing/reset
```

---

### 2. **Full-Text Search Indexes**

The platform implements FULLTEXT MySQL indexes on searchable content:

**Indexed Tables:**
- `users` - (name, email)
- `solutions` - (solution_title, solution_description, tags)
- `solutions_step` - (solution_heading, solution_body)
- `questions` - (question, description)
- `comments` - (body)

**Search Performance:**
- Fast query results on large datasets
- Natural language processing
- Relevance-based ranking

---

## Team Management

### 1. **Team Creation & Selection**

Users can create and manage teams for collaborative work.

**Team Features:**
- **Personal Teams**: Each user automatically gets a personal team
- **Multi-Team Support**: Create and join multiple teams
- **Team Ownership**: Track who created/owns each team
- **Current Team Selection**: Switch between teams dynamically
- **Team-based Resources**: Files, folders, and objects scoped to teams

**Team Attributes:**
```
- name (string)
- user_id (owner)
- personal_team (boolean - is this a personal workspace?)
- timestamps (created_at, updated_at)
```

---

### 2. **Team Members & Invitations**

**Team Collaboration:**
- **Add Members**: Invite users to join teams
- **Member Roles**: Assign roles to team members
- **Invitations**: Send and manage team invitations via email
- **Soft Invitations**: Batch invitation support
- **Member Removal**: Remove team members

**Team Membership:**
- Pivot table: `team_user` (many-to-many relationship)
- Stores: team ID, user ID, role
- Unique constraint on (team_id, user_id)

**Team Invitations:**
```
- team_id (foreign key)
- email (email address being invited)
- role (optional role assignment)
- unique: (team_id, email)
```

---

### 3. **Hierarchical File Storage (Objects)**

**Polymorphic Storage System:**
- **Objects**: Hierarchical containers for organizing files/folders
- **Files**: Individual file storage within objects
- **Folders**: Create folder structures
- **UUID Support**: Unique identifiers for all storage items
- **Team Scoping**: All storage is team-specific

**Object Structure:**
```
- uuid (unique identifier)
- objectable_id/type (polymorphic - what this object contains)
- parent_id (nullable - hierarchical structure)
- team_id (scoped to team)
```

**File Storage:**
```
- name, size, path
- UUID for unique identification
- team_id for scoping
- timestamps
```

---

## Technical Architecture

### 1. **Framework & Technology Stack**

**Backend:**
- **Laravel 10.50.2**: Modern PHP framework
- **PHP 8.3+**: Latest PHP version support
- **Laravel Jetstream 3.3**: Full-stack authentication & team management
- **Laravel Sanctum 3.3**: API token authentication
- **Laravel Fortify**: Authentication backend logic
- **Livewire 2.12**: Real-time component reactivity
- **Laravel Scout 10.25**: Full-text search
- **Algolia Search**: Cloud-based search integration
- **TNTSearch Driver**: Offline search option

**Frontend:**
- **Tailwind CSS 3.0.8**: Utility-first CSS framework
- **DaisyUI 1.25.4**: Component library for Tailwind
- **Alpine.js 3.7.1**: Lightweight JavaScript framework
- **Vue.js 2.6.14**: Progressive JavaScript framework
- **Tiptap 1.32.2**: Rich text editor
- **Pusher.js 7.0.3**: Client library for Laravel Echo (present; nothing listens yet)
- **Bootstrap 5.1.3**: CSS framework (legacy support)

**Real-Time Communication:**
- **Pusher / Reverb**: configured for broadcasting; one event (`QuestionWasAsked`) broadcasts
- **Laravel Echo**: initialised in `resources/js/bootstrap.js`; no page uses `window.Echo` yet
- **WebSockets**: Self-hosted WebSocket server via BeyondCode

**Database:**
- **MySQL**: Primary database
- **Full-Text Indexes**: For efficient searching
- **Soft Deletes**: Logical deletion support
- **Migrations**: Version-controlled schema

**Additional Tools:**
- **Laravel Tinker**: Interactive shell for code execution
- **Laravel Sail**: Docker-based development environment
- **PHPUnit**: Testing framework
- **Faker**: Test data generation

---

### 2. **API Architecture**

**Token-Based Authentication:**
- Personal access tokens via Sanctum
- API-specific permissions system
- Token abilities/scopes for fine-grained access control

**API Token Attributes:**
```
- token (unique, 64-character hash)
- tokenable_id/type (user ownership)
- name (token description)
- abilities (JSON array of permissions)
- last_used_at (tracking)
```

---

### 3. **Real-Time Features**

**Broadcasting System:**
- **Channels**: User-specific and event-specific channels
- **Notifications**: Real-time database notifications
- **Broadcasting Events**: Push notifications via Pusher/WebSockets
- **Database Notifications**: Persistent notification history

**WebSocket Support:**
- Self-hosted via BeyondCode Laravel WebSockets package
- Statistics tracking
- Multiple connection support

---

## Getting Started

### 1. **Prerequisites**

```
- PHP 8.3 or higher
- MySQL 8.0 or higher
- Node.js & npm (for frontend assets)
- Composer (PHP package manager)
- Git
```

### 2. **Installation**

```bash
# Clone the repository
git clone <repository-url> infodot
cd infodot

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Configure database
# Edit .env with your database credentials

# Run migrations
php artisan migrate

# Seed sample data (optional)
php artisan db:seed

# Build frontend assets
npm run production

# For development with watch
npm run watch
```

### 3. **First Run**

```bash
# Start the application
php artisan serve

# In another terminal, start WebSocket server
php artisan websockets:serve

# Visit http://localhost:8000 in your browser
```

### 4. **User Registration**

1. Navigate to `/register`
2. Enter name, email, and password
3. Click sign up
4. Verify your email
5. Start creating content!

---

## API & Integration

### 1. **Authentication Routes**

**Authentication Endpoints:**
```
POST /login                    - User login
POST /register                 - User registration
POST /forgot-password         - Request password reset
POST /reset-password          - Complete password reset
POST /logout                  - Logout current user
GET  /verify-email            - Email verification page
POST /email/notification      - Resend verification
POST /confirm-password        - Password confirmation
```

### 2. **Solution Routes**

```
GET  /solutions               - List all solutions
GET  /solution/create         - Solution creation form
POST /solution/add            - Create new solution
GET  /solution/view/{id}      - View specific solution
```

### 3. **Question Routes**

```
GET  /questions               - List all questions
GET  /questions/ask           - Question form
POST /questions/add           - Create new question
GET  /question/view/{qid}     - View specific question
```

### 4. **Profile Routes**

```
GET  /user/profile/{id}       - View user profile
GET  /user/profile/edit       - Edit own profile
```

### 5. **Public Pages**

```
GET  /                        - Landing page
GET  /about                   - About page
GET  /contact                 - Contact page
POST /contact-send            - Submit contact form
GET  /faqs                    - FAQ page
GET  /complains               - Complaints/Feedback page
GET  /features                - Features overview
GET  /terms                   - Terms of service
GET  /solution-results        - Search results page
```

---

## Database Schema

### Complete Table Overview

**Core Tables:**
- `users` - User accounts and profiles
- `team_user` - Team membership (many-to-many)
- `teams` - Team definitions
- `team_invitations` - Pending team invitations
- `personal_access_tokens` - API tokens
- `password_resets` - Password reset tokens
- `sessions` - Active user sessions

**Content Tables:**
- `solutions` - Solution posts
- `solutions_step` - Solution steps (one-to-many)
- `questions` - Question posts
- `answers` - Question answers
- `comments` - Comments (polymorphic, threaded)
- `likes` - Likes (polymorphic)

**Social Tables:**
- `followers` - Follow relationships (self-referencing)
- `associates` - User connections

**Storage Tables:**
- `objects` - Hierarchical storage containers
- `files` - File storage
- `folders` - Folder storage

**System Tables:**
- `failed_jobs` - Job queue failures
- `websockets_statistics_entries` - WebSocket metrics
- `notifications` - Database notifications

### Key Relationships

```
Users (1) ──→ (Many) Solutions
Users (1) ──→ (Many) Questions
Users (1) ──→ (Many) Comments
Users (1) ──→ (Many) Likes
Users (1) ──→ (Many) Teams
Users (1) ──→ (Many) Files
Solutions (1) ──→ (Many) Steps
Solutions (1) ──→ (Many) Comments (polymorphic)
Solutions (1) ──→ (Many) Likes (polymorphic)
Questions (1) ──→ (Many) Comments (polymorphic)
Questions (1) ──→ (Many) Likes (polymorphic)
Comments (1) ──→ (Many) Comments (parent-child threading)
Users ←→ Users (Followers - self-referencing)
Users ←→ Users (Associates)
Teams ←→ Users (Team membership)
```

---

## Advanced Features

### 1. **Real-Time Notifications**

Users receive real-time notifications for:
- New comments on their solutions/questions
- New follows or connections
- Team invitations
- Replies in comment threads
- Likes on content

**Notification Channels:**
- Database (persistent)
- Broadcast (real-time via WebSockets)
- Email (optional)
- Custom channels (extendable)

### 2. **Content Ranking & Discovery**

Content visibility improved through:
- Most liked solutions and questions
- Recent activity filtering
- User reputation tracking
- Search relevance scoring
- Tag-based categorization

### 3. **Performance Optimization**

- **Full-text indexes** on searchable columns
- **Database query optimization**
- **Eager loading** of relationships
- **Caching** for frequently accessed data
- **Asset minification** via Laravel Mix
- **CDN support** for static files

### 4. **Security Features**

- **CSRF Protection** on all forms
- **SQL Injection Prevention** via ORM
- **Password Hashing** with bcrypt
- **HTTPS Ready** configuration
- **API Rate Limiting**
- **Two-Factor Authentication** support
- **Email Verification** required
- **Password Reset Tokens** with expiration

---

## Deployment

### 1. **Production Deployment**

See [PRODUCTION_DEPLOYMENT.md](PRODUCTION_DEPLOYMENT.md) for:
- Server setup and configuration
- SSL certificate installation
- Database backup strategies
- Zero-downtime deployment
- Monitoring and logging
- Performance optimization
- Security hardening

### 2. **Environment Configuration**

Key `.env` variables:
```
APP_NAME=InfoDot
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=infodot
DB_USERNAME=root
QUEUE_CONNECTION=database
CACHE_DRIVER=redis
SESSION_DRIVER=database
FORTIFY_FEATURES=registration,email-verification,password-reset
JETSTREAM_FEATURES=teams,api,profile-photo,notifications
```

---

## Testing

### Running Tests

```bash
# Run auth-related tests
composer test:auth

# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/AuthenticationFlowSmokeTest.php

# With test coverage
php artisan test --coverage
```

### Test Suites Included

- `AuthenticationFlowSmokeTest` - Auth flow integration tests
- `AuthenticationTest` - Login/logout functionality
- `RegistrationTest` - User registration flow
- `PasswordResetTest` - Password recovery process

---

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## Support & Documentation

- **Issues**: Report bugs via GitHub Issues
- **Docs**: See `/docs` folder for additional documentation
- **FAQ**: Check `/faqs` page on the platform
- **Contact**: Use `/contact` page for support inquiries

---

## Roadmap

### Planned Features

- [ ] Advanced analytics dashboard
- [ ] Solution recommendations engine
- [ ] Batch operations on content
- [ ] Advanced tagging system with hierarchies
- [ ] Community moderation tools
- [ ] Content translation support
- [ ] Mobile app (iOS/Android)
- [ ] Extended API with webhooks
- [ ] Marketplace for solutions
- [ ] Premium features/subscriptions

---

## Version History

**Current Version:** 1.0.0

### Recent Updates
- ✅ Jetstream authentication activated and integrated
- ✅ Full test suite for authentication flows
- ✅ Real-time WebSocket support
- ✅ Comprehensive search functionality
- ✅ Team management system
- ✅ User profile system with gravatar support

---

## Credits

Built with:
- Laravel Framework community
- Jetstream team management
- Livewire for real-time components
- Tailwind CSS for styling
- All contributing developers and community members

---

**Last Updated:** March 18, 2026  
**Maintained By:** InfoDot Development Team

For the latest updates, visit the [GitHub Repository](https://github.com/infodot/platform).
