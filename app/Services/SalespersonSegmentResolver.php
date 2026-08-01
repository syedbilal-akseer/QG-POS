<?php

namespace App\Services;

/**
 * Hardcoded salesperson → business-segment mapping for the sales-order
 * percentage report (Commercial / Corporate / Directors / HBM / KHI /
 * Others / POS). There is no such grouping in Oracle, so this list must be
 * updated by hand whenever a salesperson is added, renamed, or reassigned.
 */
class SalespersonSegmentResolver
{
    public const DEFAULT_SEGMENT = 'Others';

    private const MAP = [
        'Commercial' => [
            'Bilal, Mr. Muhammad',
            'Farhan Shah',
            'Hassan Shafi',
            'Mobeen Asad',
            'Shabbir, Mohammad',
        ],
        'Corporate' => [
            'Ali Raza',
            'M. Adnan',
            'M. Asim',
            'Usman Akbar',
        ],
        'Directors' => [
            'ASQ',
            'KHQ',
            'WJQ',
        ],
        'HBM' => [
            'Abdul Qadir',
            'Ahmad, Ali',
            'Anjum, Tahir',
            'Aqib, Mr. Muhammad',
            'Arif,',
            'Dawood, Ahmed',
            'Fahad Khan',
            'Haider Ali,',
            'Kunwar, Adnan',
            'Sheeraz,',
            'Siddiqui, Waqar Ahmed',
            'Usama, Mr. Muhammad',
            'Zeeshan, Mr. Muhammad',
        ],
        'KHI' => [
            'AAB',
            'Abdul Raffay',
            'Ghouri, Mr. Mubeen Khan',
            'Hasnain, Mr. Syed Ghulam',
            'Imran Shah',
            'Mr. Abdullah Rauf Khan',
            'Tajammul Ahmed',
            'Umair Quadri',
        ],
        'Others' => [
            'Khan, Mr. Khurram Ali',
            'Mohsin Gillani',
            'No Sales Credit',
        ],
        'POS' => [
            'Ali, Mr. Rao Shahzaib',
            'Ali, Syed Fazil',
            'Kashif,',
            'M Ali',
            'Sheharyar Anwar',
            'Waqar Ali',
        ],
    ];

    /** @var array<string, string>|null salesperson => segment, built once from MAP */
    private static ?array $lookup = null;

    public static function forSalesperson(string $salesperson): string
    {
        return self::lookup()[$salesperson] ?? self::DEFAULT_SEGMENT;
    }

    /** @return array<string, string> */
    private static function lookup(): array
    {
        if (self::$lookup === null) {
            self::$lookup = [];
            foreach (self::MAP as $segment => $salespeople) {
                foreach ($salespeople as $salesperson) {
                    self::$lookup[$salesperson] = $segment;
                }
            }
        }

        return self::$lookup;
    }
}
