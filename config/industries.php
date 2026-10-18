<?php

/*
|--------------------------------------------------------------------------
| Industries
|--------------------------------------------------------------------------
| What a company does decides which examples InfoDot shows: the search placeholder,
| the questions and solutions it suggests, and the placeholders in the forms. These are
| writing prompts, not facts or advice; nothing here is stored as company content.
|
| Keys are stored on teams.industry. `general` is the fallback and is complete on its own;
| every other industry overrides only what it names.
*/

return [

    'general' => [
        'label' => 'General business',
        'searches' => [
            'How do we onboard a new supplier?',
            'Who approves purchase orders?',
            'How do we handle a customer complaint?',
            'Where is the leave policy?',
        ],
        'questions' => [
            'How do we onboard a new supplier?',
            'Who approves a purchase order above our usual limit?',
            'What is our process for handling a customer complaint?',
            'How do we request leave and who signs it off?',
            'Where do we keep signed contracts, and who can see them?',
            'How do we prepare for month-end?',
        ],
        'solutions' => [
            'Onboarding a new employee in their first week',
            'Processing a supplier invoice from receipt to payment',
            'Opening and closing the business each day',
            'Handling a customer refund',
        ],
        'ask_title' => 'How do we onboard a new supplier?',
        'ask_details' => 'What are you trying to do, what did you try, and what happened? Include the names of any forms, systems or people involved.',
        'solution_title' => 'How to onboard a new supplier',
        'solution_description' => 'Say the outcome: what gets done, who does it, and which documents or access are needed.',
        'solution_tags' => 'suppliers, onboarding, procurement',
    ],

    'retail' => [
        'label' => 'Retail and e-commerce',
        'searches' => [
            'How do we process a customer return?',
            'What is the till cash-up procedure?',
            'How do we reorder fast-moving stock?',
            'Who handles a supplier short delivery?',
        ],
        'questions' => [
            'How do we process a return without the original receipt?',
            'What is the end-of-day till cash-up procedure?',
            'How do we reorder stock before it runs out?',
            'What do we do when a supplier delivers short?',
            'How do we handle a price discrepancy at the till?',
            'Who approves a markdown or a promotion?',
        ],
        'solutions' => [
            'Opening the store and checking the tills',
            'Receiving and counting a supplier delivery',
            'Doing a monthly stock take',
            'Handling an online order that was delivered damaged',
        ],
        'ask_title' => 'How do we process a return without the original receipt?',
        'ask_details' => 'Which store or channel is this about, what did the customer ask for, and what did you try at the till or in the system?',
        'solution_title' => 'How to receive and count a supplier delivery',
        'solution_description' => 'What a delivery needs before it is signed for, who checks it against the order, and where discrepancies are recorded.',
        'solution_tags' => 'stock, deliveries, tills',
    ],

    'mining' => [
        'label' => 'Mining and quarrying',
        'searches' => [
            'What is the pre-shift inspection checklist?',
            'How do we report a near miss?',
            'Who signs off a plant maintenance permit?',
            'How do we log a production shortfall?',
        ],
        'questions' => [
            'What is the pre-shift inspection checklist for the section?',
            'How do we report a near miss and who must be told?',
            'Who signs off a maintenance permit on the plant?',
            'How do we record a production shortfall and its cause?',
            'What must be in a handover between shifts?',
            'How do we keep contractor induction records up to date?',
        ],
        'solutions' => [
            'Conducting a pre-shift safety inspection',
            'Isolating and locking out equipment before maintenance',
            'Handing over between shifts',
            'Recording and reporting an incident',
        ],
        'ask_title' => 'What is the pre-shift inspection checklist for the section?',
        'ask_details' => 'Which site, section or shift is this about, what did you check, and what was unclear or missing?',
        'solution_title' => 'How to hand over between shifts',
        'solution_description' => 'What the outgoing shift records, what the incoming shift must confirm, and where the handover is kept.',
        'solution_tags' => 'safety, shifts, inspections',
    ],

    'construction' => [
        'label' => 'Construction and property',
        'searches' => [
            'How do we raise a variation order?',
            'What is the site induction process?',
            'Who approves a subcontractor payment?',
            'How do we log a snag list item?',
        ],
        'questions' => [
            'How do we raise and approve a variation order?',
            'What must a new subcontractor complete before starting on site?',
            'Who approves a progress payment to a subcontractor?',
            'How do we record and close out a snag list item?',
            'What is our process for a delay or weather claim?',
            'Where are the latest drawings, and how do we know they are current?',
        ],
        'solutions' => [
            'Inducting a new subcontractor on site',
            'Preparing a monthly progress claim',
            'Running a practical completion inspection',
            'Issuing and tracking a site instruction',
        ],
        'ask_title' => 'How do we raise and approve a variation order?',
        'ask_details' => 'Which project and trade is this about, what changed, and who has already been told?',
        'solution_title' => 'How to induct a new subcontractor on site',
        'solution_description' => 'Which documents are checked, who briefs them, and what must be signed before they start work.',
        'solution_tags' => 'site, subcontractors, compliance',
    ],

    'finance' => [
        'label' => 'Financial services',
        'searches' => [
            'How do we onboard and verify a new client?',
            'What is the month-end close checklist?',
            'Who approves a payment above the limit?',
            'How do we log a compliance breach?',
        ],
        'questions' => [
            'How do we onboard and verify a new client?',
            'What is on the month-end close checklist?',
            'Who approves a payment above the daily limit?',
            'How do we record and escalate a compliance concern?',
            'Where do we keep client records and for how long?',
            'How do we reconcile a bank account that does not balance?',
        ],
        'solutions' => [
            'Onboarding a new client with the required checks',
            'Closing the month and preparing the management accounts',
            'Reconciling a bank statement',
            'Handling a client complaint',
        ],
        'ask_title' => 'How do we onboard and verify a new client?',
        'ask_details' => 'Which product or team is this about, what has been done so far, and what is blocking the next step?',
        'solution_title' => 'How to reconcile a bank statement',
        'solution_description' => 'Which accounts and periods it covers, who prepares and who reviews, and where the working is saved.',
        'solution_tags' => 'reconciliation, month-end, controls',
    ],

    'agriculture' => [
        'label' => 'Agriculture and farming',
        'searches' => [
            'How do we schedule irrigation for a block?',
            'What is the spray record procedure?',
            'How do we track equipment servicing?',
            'Who signs off a harvest delivery?',
        ],
        'questions' => [
            'How do we plan irrigation for each block?',
            'What must be recorded every time we spray?',
            'How do we track tractor and equipment servicing?',
            'Who signs off a harvest delivery to the buyer?',
            'How do we manage seasonal worker contracts and pay?',
            'What do we do when a pump or sensor fails?',
        ],
        'solutions' => [
            'Recording a spray application',
            'Servicing a tractor on schedule',
            'Receiving and weighing a harvest load',
            'Onboarding seasonal workers',
        ],
        'ask_title' => 'What must be recorded every time we spray?',
        'ask_details' => 'Which farm, block or crop is this about, what was applied, and what is unclear in the record?',
        'solution_title' => 'How to record a spray application',
        'solution_description' => 'What is written down before, during and after, who signs it, and where the record is kept.',
        'solution_tags' => 'records, spraying, compliance',
    ],

    'manufacturing' => [
        'label' => 'Manufacturing',
        'searches' => [
            'What is the line start-up checklist?',
            'How do we log a quality defect?',
            'Who approves a change to a work instruction?',
            'How do we schedule preventive maintenance?',
        ],
        'questions' => [
            'What is on the line start-up checklist?',
            'How do we record a quality defect and quarantine the batch?',
            'Who approves a change to a work instruction?',
            'How do we schedule and record preventive maintenance?',
            'What do we do when raw material arrives out of specification?',
            'How do we trace a finished batch back to its inputs?',
        ],
        'solutions' => [
            'Starting up a production line',
            'Quarantining and releasing a batch',
            'Changing over a line between products',
            'Doing a preventive maintenance round',
        ],
        'ask_title' => 'What is on the line start-up checklist?',
        'ask_details' => 'Which line, shift or product is this about, what happened, and what the work instruction says today.',
        'solution_title' => 'How to quarantine and release a batch',
        'solution_description' => 'What triggers a quarantine, who decides, how the batch is marked and what is needed to release it.',
        'solution_tags' => 'quality, production, batches',
    ],

    'logistics' => [
        'label' => 'Transport and logistics',
        'searches' => [
            'How do we log a delayed delivery?',
            'What is the vehicle pre-trip check?',
            'Who approves a fuel card limit change?',
            'How do we handle a damaged load?',
        ],
        'questions' => [
            'How do we log and communicate a delayed delivery?',
            'What is on the vehicle pre-trip inspection?',
            'Who approves a change to a fuel card limit?',
            'What do we do when a load arrives damaged?',
            'How do we assign a driver to a route?',
            'How do we reconcile proof-of-delivery with invoices?',
        ],
        'solutions' => [
            'Doing a vehicle pre-trip inspection',
            'Handling a damaged or short delivery',
            'Closing out a trip and submitting proof of delivery',
            'Booking a vehicle for service',
        ],
        'ask_title' => 'How do we log and communicate a delayed delivery?',
        'ask_details' => 'Which route, vehicle or customer is this about, what caused the delay, and who has been told.',
        'solution_title' => 'How to handle a damaged or short delivery',
        'solution_description' => 'What the driver records on the spot, who is called, and what the customer is told.',
        'solution_tags' => 'deliveries, drivers, claims',
    ],

    'hospitality' => [
        'label' => 'Hospitality and food',
        'searches' => [
            'What is the kitchen opening checklist?',
            'How do we handle an allergy request?',
            'Who orders from the produce supplier?',
            'How do we do a daily cash-up?',
        ],
        'questions' => [
            'What is on the kitchen opening checklist?',
            'How do we handle and record an allergy request?',
            'Who places the weekly produce order?',
            'How do we do the daily cash-up and bank?',
            'What do we do when a guest complains about a booking?',
            'How do we log temperature checks for the fridges?',
        ],
        'solutions' => [
            'Opening and closing the kitchen',
            'Handling a guest complaint',
            'Doing the daily cash-up',
            'Receiving and storing a food delivery',
        ],
        'ask_title' => 'How do we handle and record an allergy request?',
        'ask_details' => 'Which venue, shift or dish is this about, what the guest asked for, and what happened in the kitchen.',
        'solution_title' => 'How to open and close the kitchen',
        'solution_description' => 'What is checked, cleaned and recorded, who signs off, and where the checklist is kept.',
        'solution_tags' => 'kitchen, hygiene, daily checks',
    ],

    'healthcare' => [
        'label' => 'Healthcare and wellness',
        'searches' => [
            'How do we book a patient follow-up?',
            'What is the infection control routine?',
            'Who orders medical consumables?',
            'How do we record a patient complaint?',
        ],
        'questions' => [
            'How do we book and confirm a patient follow-up?',
            'What is the daily infection control routine?',
            'Who orders and receives medical consumables?',
            'How do we record and respond to a patient complaint?',
            'How do we handle a patient who missed their appointment?',
            'What do we do when a device needs calibration or repair?',
        ],
        'solutions' => [
            'Preparing a consulting room for the day',
            'Receiving and storing medical stock',
            'Following up a missed appointment',
            'Recording and escalating a complaint',
        ],
        'ask_title' => 'How do we book and confirm a patient follow-up?',
        'ask_details' => 'Which practice, department or service is this about, what happened, and what the current routine is. Leave out patient names and personal details.',
        'solution_title' => 'How to prepare a consulting room for the day',
        'solution_description' => 'What is checked, stocked and cleaned, and who confirms it is ready. Keep patient details out.',
        'solution_tags' => 'clinic, hygiene, stock',
    ],

    'professional' => [
        'label' => 'Professional services',
        'searches' => [
            'How do we open a new client file?',
            'What is the engagement letter template?',
            'How do we record billable time?',
            'Who reviews work before it is sent?',
        ],
        'questions' => [
            'How do we open and number a new client file?',
            'Which engagement letter template do we use, and who signs it?',
            'How do we record and approve billable time?',
            'Who reviews work before it goes to the client?',
            'How do we handle a conflict-of-interest check?',
            'How do we archive a closed matter?',
        ],
        'solutions' => [
            'Opening a new client engagement',
            'Preparing and issuing a monthly invoice',
            'Reviewing work before it leaves the firm',
            'Closing and archiving a matter',
        ],
        'ask_title' => 'How do we open and number a new client file?',
        'ask_details' => 'Which team or client type is this about, what you have done so far, and what the current process says.',
        'solution_title' => 'How to open a new client engagement',
        'solution_description' => 'The checks, documents and approvals needed before work starts, and who does each one.',
        'solution_tags' => 'clients, engagements, billing',
    ],

    'technology' => [
        'label' => 'Technology and software',
        'searches' => [
            'How do we deploy to production?',
            'Who is on call this week?',
            'How do we rotate a shared credential?',
            'What is the incident review process?',
        ],
        'questions' => [
            'How do we deploy to production and roll back if it fails?',
            'Who is on call and how do we escalate an outage?',
            'How do we rotate a shared credential without downtime?',
            'What is our process for an incident review?',
            'How do we onboard a new developer to the codebase?',
            'Where do we record architecture decisions?',
        ],
        'solutions' => [
            'Deploying a release and rolling it back',
            'Handling an outage from alert to review',
            'Onboarding a new developer',
            'Rotating an API key safely',
        ],
        'ask_title' => 'How do we rotate a shared credential without downtime?',
        'ask_details' => 'Which service or environment is this about, what you tried, and the exact error or behaviour you saw. Never paste secrets.',
        'solution_title' => 'How to deploy a release and roll it back',
        'solution_description' => 'The steps, who approves, what is checked afterwards and how to undo it. Never include secrets.',
        'solution_tags' => 'deployments, on-call, runbooks',
    ],
];
