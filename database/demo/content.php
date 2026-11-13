<?php

/*
|--------------------------------------------------------------------------
| Demonstration content (R-49)
|--------------------------------------------------------------------------
| What `php artisan demo:install` writes, as data: reviewed in a pull request like any other change, the way
| database/knowledge/ is. Rules for editing this file:
|
|  - The people and businesses are fictional and say so in their names. Addresses use reserved domains
|    (*.infodot.test for email, example.com for websites) and the phone numbers use a format no network issues.
|  - The knowledge is general small-business practice written to be useful and true. It is not legal, tax or
|    financial advice and quotes no regulatory figure; where a rule depends on the law it says to check with
|    the authority or a professional.
|  - No customer counts, testimonials, ratings, revenue or activity numbers. Nothing here is a claim about a
|    real business.
|
|  - A person marked 'moderator' is created only on request (demo:install --with-moderator) and must not be the
|    author of anything below: the default install has to be complete without them.
|
| Keys ("naledi", "ledger", "late-invoice") only tie the entries together inside this file.
*/

return [

    'people' => [
        'naledi' => ['name' => 'Naledi Demo', 'email' => 'naledi@demo.infodot.test', 'ground' => 'knowledge', 'role' => 'Owner of the first demonstration company'],
        'sipho' => ['name' => 'Sipho Demo', 'email' => 'sipho@demo.infodot.test', 'ground' => 'documents', 'role' => 'Colleague in the first demonstration company'],
        'lerato' => ['name' => 'Lerato Demo', 'email' => 'lerato@demo.infodot.test', 'ground' => 'files', 'role' => 'Outside the first company; owner of the second'],
        'thabo' => ['name' => 'Thabo Demo', 'email' => 'thabo@demo.infodot.test', 'ground' => 'people', 'role' => 'Demonstration moderator: created only by demo:install --with-moderator', 'moderator' => true],
    ],

    'companies' => [
        'ledger' => [
            'name' => 'Ledger Works (Demonstration)',
            'owner' => 'naledi',
            'members' => ['sipho' => 'editor'],
            'industry' => 'finance',
            'categories' => ['finance-and-accounting', 'consulting'],
            'profile' => [
                'trading_name' => 'Ledger Works',
                'description' => 'A demonstration bookkeeping practice, created to show how a business presents itself on InfoDot. It is not a real firm and cannot take work. As written, it keeps the books for small businesses: monthly bookkeeping, payroll administration and a plain-language report an owner can read in ten minutes.',
                'business_type' => 'company',
                'industry' => 'Finance and accounting',
                'category' => 'Bookkeeping',
                'products_services' => 'Monthly bookkeeping, payroll administration, management reports, bookkeeping training for owners.',
                'industries_served' => 'Retail, trades, professional practices',
                'location' => 'Johannesburg, Gauteng',
                'regions' => 'Gauteng',
                'website' => 'https://ledgerworks.example.com',
                'contact_email' => 'hello@ledgerworks.infodot.test',
                'contact_phone' => '+27 00 000 0101',
                'size' => '1–10 people',
            ],
            'services' => [
                ['name' => 'Monthly bookkeeping', 'kind' => 'service', 'description' => 'Bank, debtors and creditors reconciled every month, with a one-page summary for the owner.', 'price_note' => 'Demonstration price: from R2 500 a month'],
                ['name' => 'Payroll administration', 'kind' => 'service', 'description' => 'Payslips and the monthly payroll run for teams of up to twenty people.', 'price_note' => 'Demonstration price: R95 per payslip'],
                ['name' => 'Bookkeeping training for owners', 'kind' => 'service', 'description' => 'A half-day session on reading your own management accounts.', 'price_note' => 'Demonstration price: R1 800 per session'],
                ['name' => 'Month-end checklist pack', 'kind' => 'product', 'description' => 'The printable checklists the practice works from.', 'price_note' => 'Demonstration price: R250'],
            ],
            'project' => ['title' => 'Month-end close reduced to one morning', 'summary' => 'A worked example, not a client: the checklist a five-person shop would follow so its month-end takes a morning. The same steps are published as a solution on InfoDot.', 'year' => 2026, 'location' => 'Johannesburg'],
            'location' => ['label' => 'Office', 'address' => '1 Example Street', 'city' => 'Johannesburg', 'province' => 'Gauteng', 'postal_code' => '0000', 'country' => 'South Africa', 'show_address' => false],
            'announcement' => ['title' => 'This is a demonstration business', 'body' => 'Ledger Works exists to show what a business page on InfoDot looks like when it is filled in: services with price notes, a portfolio entry, a location, announcements and an inbox. Nothing on this page is an offer.'],
        ],
        'fieldline' => [
            'name' => 'Fieldline IT (Demonstration)',
            'owner' => 'lerato',
            'members' => [],
            'industry' => 'technology',
            'categories' => ['technology-and-software', 'maintenance-and-technical-services'],
            'profile' => [
                'trading_name' => 'Fieldline IT',
                'description' => 'A demonstration IT support business, created to give the InfoDot directory a second listing in a different category. It is not a real firm and cannot take work. As written, it looks after the computers, backups and network of small offices.',
                'business_type' => 'sole_proprietor',
                'industry' => 'Technology and software',
                'category' => 'IT support',
                'products_services' => 'Office IT support, backup set-up, network set-up, staff IT training.',
                'industries_served' => 'Small offices, practices, shops',
                'location' => 'Durban, KwaZulu-Natal',
                'regions' => 'KwaZulu-Natal',
                'website' => 'https://fieldline.example.com',
                'contact_email' => 'hello@fieldline.infodot.test',
                'contact_phone' => '+27 00 000 0102',
                'size' => '1–10 people',
            ],
            'services' => [
                ['name' => 'Office IT support', 'kind' => 'service', 'description' => 'A named person to call when a computer, printer or the network stops working.', 'price_note' => 'Demonstration price: from R1 200 a month'],
                ['name' => 'Backup set-up', 'kind' => 'service', 'description' => 'Three copies of the office files, on two kinds of storage, one of them away from the office, and a restore test.', 'price_note' => 'Demonstration price: R3 500 once-off'],
                ['name' => 'Staff IT training', 'kind' => 'service', 'description' => 'A one-hour session on passwords, phishing and where files belong.', 'price_note' => 'Demonstration price: R900 per session'],
            ],
            'project' => ['title' => 'Backups that survive a power cut', 'summary' => 'A worked example, not a client: how a small office keeps working copies of its files when the power and the internet are unreliable. The steps are published as a solution on InfoDot.', 'year' => 2026, 'location' => 'Durban'],
            'location' => ['label' => 'Workshop', 'address' => '2 Example Road', 'city' => 'Durban', 'province' => 'KwaZulu-Natal', 'postal_code' => '0000', 'country' => 'South Africa', 'show_address' => false],
            'announcement' => ['title' => 'This is a demonstration business', 'body' => 'Fieldline IT exists so the InfoDot directory has two demonstration listings to search, filter and compare. Nothing on this page is an offer.'],
        ],
    ],

    // Topics the content is filed under when they already exist on the site. The installer never creates a topic.
    'questions' => [
        'late-invoice' => [
            'by' => 'lerato', 'public' => true, 'topics' => ['Money and cash flow', 'Customers and sales'],
            'question' => 'How do I chase a late invoice without souring the relationship?',
            'description' => "A customer I like is three weeks past the due date on an invoice. I do not want to sound like a debt collector, but I also cannot carry it much longer.\n\nWhat do people who are good at this actually say, and in what order?",
            'answers' => [
                ['by' => 'naledi', 'accept' => true, 'body' => "Treat it as an administration problem first, because it usually is one: the invoice went to the wrong person, a purchase order number is missing, or it is waiting for one signature.\n\n1. Phone or message the person who pays, not the person who ordered. Ask one question: \"Is there anything you need from me to get invoice 0042 paid?\" That finds most blockers without any accusation.\n2. Follow the call with a short written note the same day: the invoice number, the amount, the original due date, and the date you agreed on the call. Attach the invoice again.\n3. If the new date passes, write once more, calmly, and say what happens next under the terms you both agreed: for example that new work pauses until the account is up to date.\n4. Only then consider a formal letter. Before charging interest or handing the debt to anyone, check what your signed terms and the law allow; an attorney or your accountant can tell you in one conversation.\n\nWhat keeps the relationship is being specific and unsurprised. What damages it is silence for two months followed by an angry message."],
            ],
        ],
        'quote-scope' => [
            'by' => 'sipho', 'public' => true, 'topics' => ['Customers and sales', 'Pricing'],
            'question' => 'What should a quote include so the customer cannot dispute the scope later?',
            'description' => "Twice this year a customer has said \"I thought that was included\" after we finished. Both times our quote was one line and a price.\n\nWhat belongs in a quote for service work so both sides mean the same thing?",
            'answers' => [
                ['by' => 'lerato', 'accept' => true, 'body' => "A quote is doing its job when a stranger could read it and say what will be delivered. I use six headings:\n\n1. What is included, as a list of things the customer will have at the end, not a list of activities.\n2. What is not included. This is the line that prevents the argument. Name the two or three things customers most often assume.\n3. What you need from the customer and by when: access, decisions, information. Say that dates move if these are late.\n4. The price, what it is based on (fixed, or an estimate of hours at a rate), and how changes are priced.\n5. Payment terms: deposit, milestones, due date.\n6. How long the quote stands, and how the customer accepts it. Ask for acceptance in writing; a reply that says \"go ahead\" is enough to point to later.\n\nWhen the customer asks for something outside the list mid-job, answer with a one-paragraph change note and a price before doing it."],
            ],
        ],
        'cash-reserve' => [
            'by' => 'lerato', 'public' => true, 'topics' => ['Money and cash flow', 'Planning and strategy'],
            'question' => 'How much cash should a small business keep in reserve?',
            'description' => 'I keep reading "three months of expenses" but that feels like a number someone made up. Is there a way to work out a reserve that fits my own business?',
            'answers' => [
                ['by' => 'naledi', 'body' => "\"Three months\" is a rule of thumb, not a requirement, and you are right to want your own number. Work it out from what could actually go wrong:\n\n1. List the costs you must pay even in a month with no sales: salaries, rent, loan repayments, software, insurance. That monthly total is your fixed base.\n2. Look at how unevenly money arrives. If one customer is a third of your income, or customers pay 60 days after invoice, you need more cover than a shop paid at the till.\n3. Ask how long it would take to replace your largest customer or recover from your likeliest interruption. That number of months, times the fixed base, is a reserve that means something.\n\nThen build it gradually: a fixed transfer on the day customers pay you, into an account you do not trade from. A small reserve you really have is worth more than a target you never reach."],
                ['by' => 'sipho', 'body' => 'One practical addition: keep the reserve somewhere you can reach within a few days but not with the card in your wallet. The point is that using it should be a decision, and written down, so that you can see afterwards how often the business needed it and top it back up.'],
            ],
            'comments' => [
                ['by' => 'lerato', 'body' => 'The "how long to replace my largest customer" question is the part I had never asked myself. Thank you both.'],
            ],
        ],
        'backups' => [
            'by' => 'naledi', 'public' => true, 'topics' => ['Getting started', 'Data & reports'],
            'question' => 'How do we back up company files when the power and the internet are unreliable?',
            'description' => "We are five people sharing files from one office computer. Power cuts interrupt us most weeks and the internet line is slow in the afternoons.\n\nWhat is a sensible backup arrangement for an office like ours?",
            'answers' => [
                ['by' => 'lerato', 'accept' => true, 'body' => "Aim for three copies, on two kinds of storage, with one copy away from the office. For five people that can be simple:\n\n1. The working copy on the office computer, protected by a small uninterruptible power supply so a power cut does not corrupt a file that is being saved.\n2. A nightly copy to an external drive that stays connected. Use the backup tool built into the operating system and let it keep versions, so you can go back to last Tuesday.\n3. An off-site copy. If the line is slow, schedule the cloud backup to run overnight and start with the folders you could not recreate: accounts, contracts, client files.\n\nThen the step most offices skip: once a month, restore one file from each copy and open it. A backup you have never restored from is a hope, not a backup. I wrote the full set-up as a solution on InfoDot."],
            ],
        ],
        'month-end-internal' => [
            'by' => 'sipho', 'public' => false, 'topics' => ['Data & reports'],
            'question' => 'Which month-end checklist are we using for the new client files?',
            'description' => "I have two versions of the month-end checklist in my folder and I am not sure which one applies to clients we took on this quarter.\n\n(This question is visible to Ledger Works only: it shows how a company keeps its own questions private.)",
            'answers' => [
                ['by' => 'naledi', 'accept' => true, 'body' => 'Use the one that starts with the bank reconciliation: it is the version we published as the solution "Close the month in one morning". The older version starts with debtors and can be archived. I have noted it in the onboarding procedure as well, which is also private to us.'],
            ],
        ],
        'spreadsheet-or-software' => [
            'by' => 'naledi', 'public' => true, 'topics' => ['Getting started', 'Money and cash flow'],
            'question' => 'When is it worth moving from a spreadsheet to accounting software?',
            'description' => 'A client runs a small catering business from a spreadsheet and it works. At what point does that stop being enough, and what are the signs?',
            'answers' => [
                ['by' => 'sipho', 'body' => "A spreadsheet is fine while one careful person captures everything and nobody else needs the numbers. The signs that it has stopped being enough:\n\n1. You cannot say what customers owe you today without rebuilding a list.\n2. Two people edit the file, and you have found a formula overwritten at least once.\n3. Sending invoices, matching payments to them and following up are three separate manual jobs.\n4. Your accountant spends the first hours of every engagement cleaning the data.\n\nIf two of those are true, the software will pay for itself in time. Choose one your accountant already works with, start at the beginning of a month, and run the spreadsheet alongside it for one month to check the totals agree."],
            ],
        ],
    ],

    'solutions' => [
        'month-end' => [
            'by' => 'naledi', 'public' => true, 'topics' => ['Money and cash flow', 'Data & reports'],
            'title' => 'Close the month in one morning: a checklist for a small business',
            'description' => "For an owner or bookkeeper who wants the month's numbers finished and readable by lunchtime on the first working day. It assumes invoices and receipts were captured during the month; if they were not, start there.\n\nGeneral practice, written for demonstration. It is not accounting advice for your particular business.",
            'tags' => 'month-end,bookkeeping,checklist',
            'duration' => 4, 'duration_type' => 'hours',
            'steps' => [
                ['Reconcile the bank first', "Download the month's bank statement and match every line to an invoice, a receipt or a transfer. Anything unmatched goes on a short list with a name next to it. Everything else in the close depends on this step being finished."],
                ['Review what customers owe', 'Print or open the list of unpaid customer invoices by age. For anything past its due date, decide today who contacts the customer and write the date next to it.'],
                ['Review what you owe', "Compare each supplier's statement with the bills you captured. Note anything missing or charged twice before you schedule payments."],
                ['Check the payroll and the regular items', 'Confirm that salaries, rent, loan repayments and subscriptions went through once each and for the expected amounts.'],
                ['Write the one-page summary', 'Four lines are enough: money in, money out, what is owed to you, what you owe. Add one sentence on what changed since last month and why. Keep the summaries together so a year reads as twelve pages.'],
            ],
        ],
        'backups' => [
            'by' => 'lerato', 'public' => true, 'topics' => ['Getting started', 'Data & reports'],
            'title' => 'Set up three-copy backups for a small office',
            'description' => "For an office of two to ten people who share files and have never tested a restore. It works where power cuts and a slow internet line are normal.\n\nGeneral practice, written for demonstration. Products and settings differ; adapt the steps to the equipment you have.",
            'tags' => 'backup,it,office',
            'duration' => 1, 'duration_type' => 'days',
            'steps' => [
                ['Decide what cannot be recreated', 'List the folders the business could not rebuild: accounts, contracts, client work, HR records. These are backed up first and restored first.'],
                ['Protect the working copy from power cuts', 'Put the computer or storage device that holds the shared files on an uninterruptible power supply, and set it to shut down cleanly when the battery runs low.'],
                ['Make a nightly local copy with versions', "Connect an external drive and schedule the operating system's backup tool to run every night, keeping older versions of changed files."],
                ['Add a copy away from the office', 'Schedule a cloud backup of the priority folders to run overnight, when the line is free. Check after the first week that it completed.'],
                ['Restore one file every month', 'Pick a file, restore it from the local copy and from the off-site copy, and open both. Write the date in a log. If a restore fails, fix that before anything else.'],
            ],
            'comments' => [
                ['by' => 'sipho', 'body' => 'The monthly restore test is the step we were missing. We have added it to our first-Monday routine.'],
            ],
        ],
        'quote' => [
            'by' => 'sipho', 'public' => true, 'topics' => ['Customers and sales', 'Pricing'],
            'title' => 'Write a quote that prevents scope disputes',
            'description' => "For anyone who quotes for service work and has heard \"I thought that was included\". The result is a one-page quote a stranger could read and say what will be delivered.\n\nGeneral practice, written for demonstration. For contract wording that must hold up in a dispute, ask an attorney.",
            'tags' => 'quotes,sales,scope',
            'duration' => 2, 'duration_type' => 'hours',
            'steps' => [
                ['List what the customer will have at the end', 'Write deliverables as things, not activities: "twelve monthly management reports", not "reporting".'],
                ['Say what is not included', 'Name the two or three things customers most often assume are part of the job. This single paragraph prevents most disputes.'],
                ['Say what you need from the customer', 'Access, decisions and information, each with a date. State that your dates move if theirs do.'],
                ['Price it and say how changes are priced', 'State whether the price is fixed or an estimate, what it is based on, and that extra work is quoted in writing before it starts.'],
                ['Set the terms and ask for written acceptance', 'Deposit, milestones, due date, and how long the quote stands. A written "go ahead" is your reference later.'],
            ],
        ],
        'client-onboarding-internal' => [
            'by' => 'naledi', 'public' => false, 'topics' => ['Getting started'],
            'title' => 'How we onboard a new bookkeeping client',
            'description' => "The practice's own procedure for the first two weeks with a new client.\n\n(This solution is visible to Ledger Works only: it shows how a company keeps its own procedures private.)",
            'tags' => 'onboarding,internal,procedure',
            'duration' => 2, 'duration_type' => 'weeks',
            'steps' => [
                ['Agree the scope in writing', 'Send the engagement note and wait for written acceptance before any work starts.'],
                ['Collect access and opening balances', 'Read-only bank access, the last set of accounts, and the list of unpaid invoices on both sides.'],
                ['Run the first month-end together', 'Use the checklist that starts with the bank reconciliation, with the client present for the summary.'],
            ],
        ],
    ],

    // One "I tried this and it works", with the optional context the form offers.
    'confirmation' => [
        'solution' => 'month-end', 'by' => 'lerato',
        'business_industry' => 'IT support',
        'applied_part' => 'All five steps, starting with the bank reconciliation.',
        'results' => 'Written for demonstration: the steps were followed in order and the summary took four lines.',
        'timeframe' => 'One month-end',
        'recommend' => true,
    ],

    // One conversation in the InfoDot Inbox between the outside person and the first company.
    'enquiry' => [
        'from' => 'lerato', 'to' => 'ledger', 'reply_by' => 'naledi',
        'subject' => 'Monthly bookkeeping for a five-person IT business',
        'category' => 'quote',
        'body' => "Hello. I run a small IT support business and I am behind on my books. Could you tell me what you would need from me to quote for monthly bookkeeping, and how the first month usually works?\n\n(A demonstration enquiry, written to show the InfoDot Inbox.)",
        'reply' => "Thank you for getting in touch. To quote we would need read-only access to the business bank account, your last set of accounts and a list of what customers currently owe you. The first month is a catch-up month and we do the month-end together.\n\n(A demonstration reply: Ledger Works is not a real firm.)",
    ],
];
