<?php

namespace App\Support;

/**
 * The ten catalogue categories (R-50 section 7). A platform may sit in several. Kept in one place so the catalogue,
 * the consistency test and, later, the Platforms page all read the same list.
 */
final class PlatformCategories
{
    /** @var array<string, string> key => the name shown to people */
    public const ALL = [
        'business_management' => 'Business Management',
        'finance_administration' => 'Finance and Administration',
        'productivity_collaboration' => 'Productivity and Collaboration',
        'sales_commerce' => 'Sales and Commerce',
        'marketing_design' => 'Marketing and Design',
        'data_intelligence' => 'Data and Intelligence',
        'human_resources' => 'Human Resources',
        'learning_development' => 'Learning and Development',
        'operations_industry' => 'Operations and Industry',
        'automation_ai' => 'Automation and AI',
    ];
}
