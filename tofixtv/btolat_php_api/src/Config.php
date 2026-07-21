<?php

declare(strict_types=1);

namespace BtolatApi;

final class Config
{
    public const BASE_URL = 'https://mobile.btolat.com';
    public const CACHE_TTL = 300;
    public const CONNECT_TIMEOUT = 8;
    public const REQUEST_TIMEOUT = 20;
    public const MAX_PAGE = 100;
    public const MAX_PAGES_PER_REQUEST = 10;
    public const MAX_ENRICH_ITEMS = 15;

    /**
     * @return array<string, array{name: string, type: string, id: int|null, slug: string|null, path: string}>
     */
    public static function categories(): array
    {
        return [
            'all' => ['name' => 'الكل', 'type' => 'all', 'id' => null, 'slug' => null, 'path' => '/videos'],

            'egyptian-league' => ['name' => 'الدوري المصري', 'type' => 'league', 'id' => 1193, 'slug' => 'premier-league', 'path' => '/league/videos/1193/premier-league'],
            'afcon' => ['name' => 'كأس الأمم الإفريقية', 'type' => 'league', 'id' => 1059, 'slug' => 'cup-of-nations', 'path' => '/league/videos/1059/cup-of-nations'],
            'caf-champions-league' => ['name' => 'دوري أبطال أفريقيا', 'type' => 'league', 'id' => 1513, 'slug' => 'caf-champions-league', 'path' => '/league/videos/1513/caf-champions-league'],
            'saudi-league' => ['name' => 'الدوري السعودي', 'type' => 'league', 'id' => 1368, 'slug' => 'saudi-professional-league', 'path' => '/league/videos/1368/saudi-professional-league'],
            'champions-league' => ['name' => 'دوري أبطال أوروبا', 'type' => 'league', 'id' => 1005, 'slug' => 'champleague', 'path' => '/league/videos/1005/champleague'],
            'premier-league-en' => ['name' => 'الدوري الإنجليزي', 'type' => 'league', 'id' => 1204, 'slug' => 'premier-league', 'path' => '/league/videos/1204/premier-league'],
            'la-liga' => ['name' => 'الدوري الإسباني', 'type' => 'league', 'id' => 1399, 'slug' => 'primera', 'path' => '/league/videos/1399/primera'],
            'europa-league' => ['name' => 'الدوري الأوروبي', 'type' => 'league', 'id' => 1007, 'slug' => 'europaleague', 'path' => '/league/videos/1007/europaleague'],
            'serie-a' => ['name' => 'الدوري الإيطالي', 'type' => 'league', 'id' => 1269, 'slug' => 'serie-a', 'path' => '/league/videos/1269/serie-a'],
            'bundesliga' => ['name' => 'الدوري الألماني', 'type' => 'league', 'id' => 1229, 'slug' => 'playoffs12', 'path' => '/league/videos/1229/playoffs12'],
            'ligue-1' => ['name' => 'الدوري الفرنسي', 'type' => 'league', 'id' => 1221, 'slug' => 'ligue-1', 'path' => '/league/videos/1221/ligue-1'],
            'world-cup' => ['name' => 'كأس العالم', 'type' => 'league', 'id' => 1056, 'slug' => 'world-cup', 'path' => '/league/videos/1056/world-cup'],

            'egypt' => ['name' => 'منتخب مصر', 'type' => 'team', 'id' => 8878, 'slug' => 'egypt', 'path' => '/team/videos/8878/egypt'],
            'al-ahly' => ['name' => 'الأهلي', 'type' => 'team', 'id' => 8883, 'slug' => 'al-ahly', 'path' => '/team/videos/8883/al-ahly'],
            'zamalek' => ['name' => 'الزمالك', 'type' => 'team', 'id' => 8959, 'slug' => 'zamalek', 'path' => '/team/videos/8959/zamalek'],
            'real-madrid' => ['name' => 'ريال مدريد', 'type' => 'team', 'id' => 16110, 'slug' => 'real-madrid', 'path' => '/team/videos/16110/real-madrid'],
            'barcelona' => ['name' => 'برشلونة', 'type' => 'team', 'id' => 15702, 'slug' => 'barcelona', 'path' => '/team/videos/15702/barcelona'],
            'liverpool' => ['name' => 'ليفربول', 'type' => 'team', 'id' => 9249, 'slug' => 'liverpool', 'path' => '/team/videos/9249/liverpool'],
            'manchester-city' => ['name' => 'مانشستر سيتي', 'type' => 'team', 'id' => 9259, 'slug' => 'manchester-city', 'path' => '/team/videos/9259/manchester-city'],
            'manchester-united' => ['name' => 'مانشستر يونايتد', 'type' => 'team', 'id' => 9260, 'slug' => 'manchester-united', 'path' => '/team/videos/9260/manchester-united'],
            'arsenal' => ['name' => 'أرسنال', 'type' => 'team', 'id' => 9002, 'slug' => 'arsenal', 'path' => '/team/videos/9002/arsenal'],
            'juventus' => ['name' => 'يوفنتوس', 'type' => 'team', 'id' => 11922, 'slug' => 'juventus', 'path' => '/team/videos/11922/juventus'],
            'pyramids' => ['name' => 'بيراميدز', 'type' => 'team', 'id' => 23165, 'slug' => 'asyouty-sport', 'path' => '/team/videos/23165/asyouty-sport'],
            'psg' => ['name' => 'باريس سان جيرمان', 'type' => 'team', 'id' => 10061, 'slug' => 'psg', 'path' => '/team/videos/10061/psg'],
        ];
    }
}
