<?php

/*
 * The marketing layer of the public Business Tools page (R-56). It holds ONLY what the catalogue does not: an outcome
 * headline, the everyday problem, why a business needs the platform and how it helps the business grow. Everything the
 * page states as fact about a platform (its name, tagline, capabilities, declared connections and whether it is a core
 * platform) is read, word for word, from database/data/platform_catalogue.php by App\Support\BusinessTools, so this file
 * can never contradict the product. The catalogue's own rule applies here: describe only what the product does today.
 *
 * Copy rules: plain South African English, no invented numbers, no claim a platform does not make in its catalogue entry,
 * no dashes in visible text. Each platform has its own reason to exist; nothing is reused between them.
 *
 * 'pillars' says which of the three outcomes the platform serves (discover, manage, grow); 'role' is its one-line place in the
 * journey; 'note' is an honest limit the visitor should know before they sign up.
 */

return [
    'pillars' => [
        'discover' => ['title' => 'Discover', 'line' => 'Find knowledge, insights and solutions.', 'tone' => 'knowledge'],
        'manage' => ['title' => 'Manage', 'line' => 'Organise your business and simplify everyday operations.', 'tone' => 'platforms'],
        'grow' => ['title' => 'Grow', 'line' => 'Make informed decisions and create opportunities for progress.', 'tone' => 'documents'],
    ],

    /* The eight groups, in page order. Each platform sits where its real function puts it. */
    'groups' => [
        [
            'key' => 'understand',
            'category' => 'data_intelligence',
            'title' => 'Understand how your business is doing',
            'problem' => 'Decisions get made on instinct because the numbers are scattered, late or missing.',
            'together' => 'One gives you the industry and your own figures side by side. The other lets you test a rule against the past before you risk anything on it.',
            'tools' => ['analytics', 'charts'],
        ],
        [
            'key' => 'money',
            'category' => 'finance_administration',
            'title' => 'Manage your money',
            'problem' => 'Who has paid, what you owe and what you can spend all need answers you can trust, not a guess from three places.',
            'together' => 'One set of books that you, your team and your accountant can all work from.',
            'tools' => ['finance'],
        ],
        [
            'key' => 'people',
            'category' => 'human_resources',
            'title' => 'Look after your people',
            'problem' => 'People records, leave and hiring spread across messages and drawers, and nobody is sure what is current.',
            'together' => 'The routines around your people follow the same steps every time, whoever is doing them.',
            'tools' => ['hr'],
        ],
        [
            'key' => 'work',
            'category' => 'productivity_collaboration',
            'title' => 'Organise your work',
            'problem' => 'Work slips when the plan, the tasks and the deadlines live in different places.',
            'together' => 'Projects holds the plan and the milestones. Tasks gives each person their own list. Both work under your one InfoDot account, and Projects keeps its files in Dot.Files.',
            'tools' => ['projects', 'tasks'],
        ],
        [
            'key' => 'information',
            'category' => 'productivity_collaboration',
            'title' => 'Create, share and keep your information',
            'problem' => 'Documents, figures and files end up on personal devices and in email threads, so the business cannot find or trust what it knows.',
            'together' => 'Between them you can write, calculate, present, design, collect and store, all in the browser under one account. Dot.Files stores files for the other Dot platforms.',
            'tools' => ['files', 'docs', 'sheet', 'forms', 'press', 'design'],
        ],
        [
            'key' => 'communicate',
            'category' => 'business_management',
            'title' => 'Communicate and agree',
            'problem' => 'People do not hear what they need to hear, and agreements are argued over because nobody can show what was agreed.',
            'together' => 'Notify makes sure the right people are told and keeps the proof. Engage makes sure an agreement is agreed, signed and checkable.',
            'tools' => ['notify', 'engage'],
        ],
        [
            'key' => 'customers',
            'category' => 'sales_commerce',
            'title' => 'Reach customers and partners',
            'problem' => 'Finding buyers, suppliers and partners means a lot of cold effort, and you cannot tell who is a fit.',
            'together' => 'Three different ways to be found: introduced by a match, listed in a store, or put up for a timed sale. Each one is built around a clear record of what happened.',
            'tools' => ['pulse', 'emall', 'auction'],
        ],
        [
            'key' => 'specialised',
            'category' => 'operations_industry',
            'title' => 'Run specialised operations',
            'problem' => 'Some industries need tools made for them. A general app cannot follow a machine across a pit or a ride across a city.',
            'together' => 'Platforms built around one kind of operation, each with the records that operation depends on.',
            'tools' => ['mines', 'ehail', 'tutor'],
        ],
    ],

    /* "What does your business need help with?": every need points at platforms whose catalogue capabilities cover it. */
    /*
     * R-73: the homepage's "imagine what your business could become" journey. Each stage says what a business is trying to do, what
     * InfoDot itself does there (only features that work today, linked to a page a visitor can open, or to sign-up when the feature
     * needs an account), and which platforms belong to it. Platform facts are not restated: names, problems, what each does, its value
     * and any limit come from the 'tools' entries below and the catalogue, and a platform appears only when BusinessTools::shown() allows.
     */
    'journey' => [
        'start' => [
            'title' => 'Start',
            'line' => 'Find your footing. Answers, researched steps and people who have done it before you.',
            'infodot' => [
                ['title' => 'Ask in plain words', 'body' => 'Describe what you are stuck on and hear from people who have been there. The answer that solved it is stamped, so the next person sees it first.', 'route' => 'solution_search_results'],
                ['title' => 'Follow researched steps', 'body' => 'Solutions written as numbered steps, from sources that are named and dated: registering, tax, pricing, hiring your first person.', 'route' => 'solution_search_results'],
            ],
            'tools' => ['tutor'],
        ],
        'manage' => [
            'title' => 'Manage',
            'line' => 'Run the day to day. Money, people, work and information, in tools that know about each other.',
            'infodot' => [
                ['title' => 'One sign-in for every tool', 'body' => 'Open the tools your business uses with your InfoDot account. Nobody keeps a separate password for each, and people leave the business in one step.', 'route' => 'business-tools'],
            ],
            'tools' => ['finance', 'hr', 'projects', 'tasks', 'files', 'docs', 'sheet', 'forms', 'notify'],
        ],
        'connect' => [
            'title' => 'Connect',
            'line' => 'Be easy to find, and find the businesses you need.',
            'infodot' => [
                ['title' => 'A page people can find', 'body' => 'Give your business a page with what you offer and where you work, listed in a directory people search by need and by place.', 'route' => 'businesses.index'],
                ['title' => 'Messages and appointments', 'body' => 'Customers and other businesses message you from your page or ask for an appointment, and every reply lands in one inbox.', 'route' => 'businesses.index'],
            ],
            'tools' => ['pulse', 'engage'],
        ],
        'grow' => [
            'title' => 'Grow',
            'line' => 'See what is working, reach new customers and present your business at its best.',
            'infodot' => [
                ['title' => 'Knowledge that adds up', 'body' => 'What your business learns is kept with your company and improved over time, with earlier versions kept, so the next person starts from the answer.', 'route' => 'register'],
            ],
            'tools' => ['analytics', 'charts', 'emall', 'auction', 'press', 'design'],
        ],
    ],

    'needs' => [
        ['label' => 'I want to understand my business performance', 'tools' => ['analytics', 'sheet']],
        ['label' => 'I need to manage my finances', 'tools' => ['finance', 'sheet']],
        ['label' => 'I want to organise my employees', 'tools' => ['hr', 'notify']],
        ['label' => 'I need to manage projects and tasks', 'tools' => ['projects', 'tasks']],
        ['label' => 'I want to improve communication', 'tools' => ['notify', 'engage', 'pulse']],
        ['label' => 'I need better ways to manage information', 'tools' => ['files', 'docs', 'sheet', 'forms']],
        ['label' => 'I want to find customers and sell', 'tools' => ['pulse', 'emall', 'auction']],
        ['label' => 'I want my business to look professional', 'tools' => ['design', 'press', 'docs']],
        ['label' => 'I run a mine, a ride-hailing operation or a tutoring business', 'tools' => ['mines', 'ehail', 'tutor']],
    ],

    'tools' => [
        'analytics' => [
            'pillars' => ['discover', 'grow'],
            'role' => 'Understands performance',
            'headline' => 'Turn your business data into better decisions.',
            'problem' => 'Most owners run on instinct and last month\'s spreadsheet. The figures that show how the industry is moving, and how your own business is doing, sit in different places.',
            'why' => 'You cannot improve what you cannot measure. Dot.Analytics puts published industry and country indicators, with their sources, next to the figures your own Dot platforms report, so you can see where you stand.',
            'growth' => 'Follow the indicators that matter to your sector with a watchlist and alerts, then look at your own numbers beside them. Decisions about pricing, hiring and where to focus have evidence behind them.',
            'outcome' => 'Better visibility. Smarter decisions. Improved performance.',
        ],
        'charts' => [
            'pillars' => ['discover'],
            'role' => 'Tests ideas against the past',
            'headline' => 'Test a trading rule before you risk money on it.',
            'problem' => 'Trading ideas often get tried with real money first. By the time the rule fails, the loss has already happened.',
            'why' => 'Dot.Charts runs a strategy against historical prices and reports the result with its drawdown and risk notes, so you can see how a rule would have behaved before you rely on it.',
            'growth' => 'For anyone who studies markets, a hunch becomes a tested rule that you can refine, and the saved results let you compare one version with the next.',
            'outcome' => 'Evidence before commitment.',
            'note' => 'A backtest describes the past. It does not predict what will happen, and it is not financial advice.',
        ],
        'finance' => [
            'pillars' => ['manage'],
            'role' => 'Manages the money',
            'headline' => 'Keep the books straight and know where the money is.',
            'problem' => 'Invoices in one place, bills in another, bank statements in a drawer. Month-end turns into a hunt for numbers, and tax time arrives before the figures do.',
            'why' => 'Dot.Finance keeps transactions, customers, suppliers, quotes, invoices, bills and bank reconciliation in one set of books, and prepares South African VAT figures and financial reports from them.',
            'growth' => 'When the books are current you can price with confidence, see who owes you and what you owe, and hand your accountant clean figures instead of a shoebox.',
            'outcome' => 'Clear books. Fewer surprises. Calmer tax time.',
            'note' => 'It prepares VAT figures and tax estimates. It does not submit returns to the tax authority.',
        ],
        'hr' => [
            'pillars' => ['manage'],
            'role' => 'Looks after people',
            'headline' => 'Look after your people without the paperwork pile.',
            'problem' => 'Leave approved in a message, contracts in a drawer, a hire tracked in an inbox. People records scatter, and nobody is sure what is current.',
            'why' => 'Dot.HR keeps employee records, positions, leave, onboarding and offboarding checklists, shifts, attendance and timesheets together, with access limited by role. It also runs a public jobs board with applications, interviews and offers.',
            'growth' => 'Hiring, onboarding and learning follow the same routine every time, so adding the next person to your team takes less of your own time.',
            'outcome' => 'Organised records. Smoother hiring. Less admin.',
            'note' => 'It prepares payroll inputs for a period. It does not calculate pay or store salaries.',
        ],
        'projects' => [
            'pillars' => ['manage', 'grow'],
            'role' => 'Coordinates the work',
            'headline' => 'Get everyone looking at the same plan.',
            'problem' => 'Work spread across chats and spreadsheets means nobody is sure what comes next, who is waiting on whom or what is holding a project up.',
            'why' => 'Dot.Projects gives each project milestones, a task board and work list, comments and an activity log, plus an overview that says where the project stands and what is in the way.',
            'growth' => 'Clear milestones and a closure approval let you finish projects properly and repeat what worked on the next one.',
            'outcome' => 'One plan. Visible progress. Projects that finish.',
        ],
        'tasks' => [
            'pillars' => ['manage'],
            'role' => 'Organises the doing',
            'headline' => 'Know what is yours to do today.',
            'problem' => 'Small jobs fall through the gaps: something promised in a meeting, a request in a message, a due date nobody wrote down.',
            'why' => 'Dot.Tasks puts work on boards and lists with an assignee, a priority, a due date, subtasks and comments, and gives every person a private inbox of what is theirs, what is due and what is overdue.',
            'growth' => 'Reliable follow-through is what lets a business take on more work without dropping the basics, and every change is kept in an audit log.',
            'outcome' => 'Nothing forgotten. Everyone clear on their part.',
        ],
        'files' => [
            'pillars' => ['manage'],
            'role' => 'Keeps business information',
            'headline' => 'Keep company files in one place you control.',
            'problem' => 'Files live on personal laptops, in email attachments and in chat. When someone leaves or a client asks, nobody can find the right version.',
            'why' => 'Dot.Files stores company files in folders with search, previews and a version history. Sharing outside the company goes through an owner or administrator\'s approval and leaves a record.',
            'growth' => 'Information that is easy to find and safe to share keeps a growing team moving, and it is where the other Dot platforms keep their files.',
            'outcome' => 'Findable files. Controlled sharing. A record of who shared what.',
        ],
        'docs' => [
            'pillars' => ['manage'],
            'role' => 'Writes the documents',
            'headline' => 'Write business documents that look right the first time.',
            'problem' => 'Reports, proposals and policies lose hours to formatting: numbering that breaks, a contents page rebuilt by hand, three copies in three inboxes.',
            'why' => 'Dot.docs gives you numbered headings, a table of contents and ready document styles, with comments, suggested changes and a version history. Print to PDF or export to Word and Markdown, or share by link with an optional password and expiry.',
            'growth' => 'Clear, consistent documents earn trust with clients, boards and staff, and one shared copy with a history ends the argument about which version is current.',
            'outcome' => 'Consistent documents. One current version.',
        ],
        'sheet' => [
            'pillars' => ['manage', 'discover'],
            'role' => 'Works with the figures',
            'headline' => 'One workbook everyone can open, in the browser.',
            'problem' => 'Budgets and stock lists live in files that get emailed around, edited twice and then no longer agree.',
            'why' => 'Dot.Sheet is a spreadsheet in the browser with formulas, charts and pivot tables. Import from and export to CSV or Excel, add comments, protect ranges and restore any earlier version.',
            'growth' => 'Shared figures stay in one place, so decisions are made on the same numbers, and a mistaken edit can be undone.',
            'outcome' => 'The same numbers for everyone.',
        ],
        'forms' => [
            'pillars' => ['manage'],
            'role' => 'Collects what you need',
            'headline' => 'Turn paper and email forms into one tracked record.',
            'problem' => 'Applications, requests and registrations arrive as emails and scanned pages, and nobody can say which have been dealt with.',
            'why' => 'Dot.Forms lets you build a form with many field types, show or hide questions with rules, publish it at a public link and work through the responses on a desk with a status, an assignee and CSV export.',
            'growth' => 'Every request is numbered and followed to a decision, so customers and staff get answers faster and you can see where requests pile up.',
            'outcome' => 'Every request counted. Every request answered.',
        ],
        'press' => [
            'pillars' => ['grow'],
            'role' => 'Presents the story',
            'headline' => 'Build, present and hand over a deck from the browser.',
            'problem' => 'Presentations depend on one person\'s laptop, one version of a file and software the client may not have.',
            'why' => 'Dot.Press is a slide editor with text, shapes, images and charts. Several people can edit together, present from the browser and export the deck to PDF or PowerPoint.',
            'growth' => 'Pitches, board packs and training decks come together faster, and the people who need them get a file they can open anywhere.',
            'outcome' => 'Faster decks. Easier hand-overs.',
        ],
        'design' => [
            'pillars' => ['grow'],
            'role' => 'Shapes how you look',
            'headline' => 'Make graphics that look like your business, without a designer.',
            'problem' => 'Posts, flyers and documents made in a hurry come out in different colours and fonts, so the business looks different every time.',
            'why' => 'Dot.Design is a studio in the browser. Start from a brief or a template, pick a format, edit and export. Brand kits keep your colours, fonts and logos consistent, and designs carry versions, comments and review.',
            'growth' => 'A consistent look makes a small business easier to recognise, and staff can produce what they need without waiting for someone else.',
            'outcome' => 'A consistent brand. Made in-house.',
        ],
        'notify' => [
            'pillars' => ['manage'],
            'role' => 'Tells the right people',
            'headline' => 'Tell the right people, the right way, every time.',
            'problem' => 'Reminders and updates depend on someone remembering to send them, and when a customer says they never heard, nobody can show what was sent.',
            'why' => 'Dot.Notify sends messages by channels such as email, SMS and webhook from templates with variables, triggered by rules that map events to messages. Every message is recorded in a delivery log with its status.',
            'growth' => 'Routine messages go out on time and each one can be traced, which frees your staff for the conversations that need a person.',
            'outcome' => 'On-time messages. A trace of every one.',
        ],
        'engage' => [
            'pillars' => ['grow'],
            'role' => 'Closes agreements',
            'headline' => 'Get agreements signed, and prove they were.',
            'problem' => 'Contracts go back and forth as attachments. Who changed what, who approved it and whether it was really signed becomes an argument.',
            'why' => 'Dot.Engage takes an engagement with outside parties from first draft to signature: versioned documents, notes, amendments and approvals, signing by several people, and sealed evidence that anyone can check on a public verification page. Parties can meet by video call in the browser.',
            'growth' => 'Deals close with a clear record behind them, so clients, suppliers and partners can trust the paperwork as well as you.',
            'outcome' => 'Agreed in one place. Provable afterwards.',
        ],
        'pulse' => [
            'pillars' => ['grow', 'discover'],
            'role' => 'Introduces the right businesses',
            'headline' => 'Meet the buyers, suppliers and candidates who fit.',
            'problem' => 'Finding the right customer, supplier or hire usually means scrolling feeds and cold outreach, with no sign of whether anyone is a fit.',
            'why' => 'On Dot.Pulse a business posts what it offers, the roles it is filling and what it needs. Pulse introduces the ones that match and shows why they were matched.',
            'growth' => 'You start conversations with context, exchange quotes and record the outcome when both sides confirm it, so your reputation grows from work that really happened.',
            'outcome' => 'Relevant introductions. Reputation built on real outcomes.',
        ],
        'emall' => [
            'pillars' => ['grow'],
            'role' => 'Sells your products online',
            'headline' => 'Open an online store without building one.',
            'problem' => 'Selling online usually means building and paying for your own shop, stock tracking and order handling before the first sale.',
            'why' => 'Dot.Emall is a multi-seller marketplace. A seller opens a storefront, lists products with variants and stock, and fulfils orders from a seller desk. Buyers search, check out with or without an account, and can follow orders, returns and refunds.',
            'growth' => 'You reach online buyers from the start, and the orders and sales ledger show you what sells.',
            'outcome' => 'A storefront from day one.',
        ],
        'auction' => [
            'pillars' => ['grow'],
            'role' => 'Sells by timed sale',
            'headline' => 'Sell by auction with a record every bidder can trust.',
            'problem' => 'Selling surplus stock, equipment or one-off items at a fair price means finding bidders and keeping the bidding honest.',
            'why' => 'On Dot.Auction a seller lists lots with a starting price, an optional reserve and a closing time, and buyers browse, watch and bid. The reserve is never shown to buyers, bids are kept in an append-only record, and the winner settles the sale after the auction closes.',
            'growth' => 'A timed sale brings bidders to your lots at a set time and leaves a clear record for both sides.',
            'outcome' => 'Competitive bidding. A clear record.',
        ],
        'mines' => [
            'pillars' => ['manage'],
            'role' => 'Keeps the fleet record',
            'headline' => 'Know where every machine is and what it needs.',
            'problem' => 'In a mining operation, fuel, maintenance, operators and machine positions sit in separate books, so problems are found after the fact.',
            'why' => 'Dot.Mines holds machines, positions and telemetry, fuel, maintenance, geofences and operators, and shows them on a live map. Other systems read the same records through an API.',
            'growth' => 'Current records let a fleet controller catch maintenance and fuel issues earlier and keep operators and their licences in order.',
            'outcome' => 'One current fleet record.',
        ],
        'ehail' => [
            'pillars' => ['manage', 'grow'],
            'role' => 'Runs the ride operation',
            'headline' => 'Run a ride-hailing operation from one desk.',
            'problem' => 'Taking bookings, finding drivers, setting fares and handling safety incidents across calls, messages and spreadsheets does not scale.',
            'why' => 'On Dot.Ehail passengers request a ride and see the fare before booking, drivers receive ride offers one at a time, and the operator watches rides, drivers and incidents from an operations desk. Drivers need approved documents before they can go online.',
            'growth' => 'An operator can add drivers and vehicles without losing sight of safety, because verification and incidents are part of the same system.',
            'outcome' => 'Bookings, drivers and safety in one place.',
            'note' => 'Fares need a routing service and a published tariff, which the operator sets up.',
        ],
        'tutor' => [
            'pillars' => ['discover', 'grow'],
            'role' => 'Connects learners and tutors',
            'headline' => 'Find a reviewed tutor, or teach on your own terms.',
            'problem' => 'Learners and parents struggle to find a tutor who suits the subject and level, and tutors struggle to run bookings, classes and course material in one place.',
            'why' => 'On Dot.Tutor learners search by subject and level, book lessons and attend them in an online classroom. Tutors publish their offerings, courses and resources. Guardians approve bookings and purchases for younger learners, and tutors are reviewed before they are listed.',
            'growth' => 'Learners find tutors they can check, and tutors run their teaching from one place, with courses and quizzes alongside lessons.',
            'outcome' => 'Learning that is easy to find and easy to run.',
        ],
    ],
];
