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

    /**
     * How a category is plated wherever platforms are grouped (the Platforms page, Dot Apps): the stencilled code
     * and the container colour of its plate. Every key of ALL has one; a test pins that.
     *
     * @var array<string, array{0: string, 1: string}> key => [code, tone]
     */
    public const PLATES = [
        'business_management' => ['BSNS', 'people'],
        'finance_administration' => ['FINA', 'files'],
        'productivity_collaboration' => ['WORK', 'knowledge'],
        'sales_commerce' => ['SALE', 'platforms'],
        'marketing_design' => ['MRKT', 'documents'],
        'data_intelligence' => ['DATA', 'analytics'],
        'human_resources' => ['CREW', 'people'],
        'learning_development' => ['LEARN', 'documents'],
        'operations_industry' => ['OPER', 'files'],
        'automation_ai' => ['AUTO', 'analytics'],
    ];

    /** The first category a platform names that the catalogue knows: where it is listed when a list shows it once. */
    public static function primary(mixed $categories): ?string
    {
        foreach ((array) $categories as $key) {
            if (is_string($key) && isset(self::ALL[$key])) {
                return $key;
            }
        }

        return null;
    }
}
