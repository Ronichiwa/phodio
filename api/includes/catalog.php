<?php
/**
 * Soul Print's service catalog and preference-matching recommendation engine.
 * The catalog is the single source of truth for client booking and recommendations.
 */

function phodio_service_types(): array
{
    return [
        'portrait' => 'Portrait session',
        'graduation' => 'Graduation portraits',
        'couple' => 'Couple / duo session',
        'family' => 'Family / group session',
        'creative' => 'Creative / themed session',
        'event' => 'Special occasion / event portraits',
    ];
}

function phodio_style_preferences(): array
{
    return [
        'any' => 'Open to suggestions',
        'classic' => 'Simple / classic',
        'creative' => 'Creative / themed',
        'student' => 'Student-friendly',
        'backdrop' => 'Backdrop-focused',
    ];
}

function phodio_package_catalog(): array
{
    return [
        'self_solo' => [
            'key' => 'self_solo', 'name' => 'Self Photography — Solo', 'category' => 'Self-Photography',
            'price' => 350, 'duration' => '20 min', 'min_people' => 1, 'max_people' => 1,
            'styles' => ['classic'], 'events' => ['portrait', 'graduation'], 'backdrop' => false,
        ],
        'self_duo' => [
            'key' => 'self_duo', 'name' => 'Self Photography — Duo', 'category' => 'Self-Photography',
            'price' => 550, 'duration' => '30 min', 'min_people' => 2, 'max_people' => 2,
            'styles' => ['classic'], 'events' => ['couple', 'portrait'], 'backdrop' => false,
        ],
        'self_group' => [
            'key' => 'self_group', 'name' => 'Self Photography — 3–4 People', 'category' => 'Self-Photography',
            'price' => 750, 'duration' => '40 min', 'min_people' => 3, 'max_people' => 4,
            'styles' => ['classic'], 'events' => ['family', 'event'], 'backdrop' => false,
        ],
        'student_solo' => [
            'key' => 'student_solo', 'name' => 'Student Promo — Solo', 'category' => 'Student Promo',
            'price' => 300, 'duration' => '20 min', 'min_people' => 1, 'max_people' => 1,
            'styles' => ['student', 'classic'], 'events' => ['graduation', 'portrait'], 'backdrop' => false,
        ],
        'student_duo' => [
            'key' => 'student_duo', 'name' => 'Student Promo — Duo', 'category' => 'Student Promo',
            'price' => 500, 'duration' => '30 min', 'min_people' => 2, 'max_people' => 2,
            'styles' => ['student', 'classic'], 'events' => ['graduation', 'couple'], 'backdrop' => false,
        ],
        'student_group' => [
            'key' => 'student_group', 'name' => 'Student Promo — 3–4 People', 'category' => 'Student Promo',
            'price' => 700, 'duration' => '40 min', 'min_people' => 3, 'max_people' => 4,
            'styles' => ['student', 'classic'], 'events' => ['graduation', 'family', 'event'], 'backdrop' => false,
        ],
        'creative_solo' => [
            'key' => 'creative_solo', 'name' => 'Creative Session — Solo', 'category' => 'Creative Session',
            'price' => 550, 'duration' => '30 min', 'min_people' => 1, 'max_people' => 1,
            'styles' => ['creative'], 'events' => ['creative', 'portrait'], 'backdrop' => false,
        ],
        'creative_duo' => [
            'key' => 'creative_duo', 'name' => 'Creative Session — Duo', 'category' => 'Creative Session',
            'price' => 700, 'duration' => '45 min', 'min_people' => 2, 'max_people' => 2,
            'styles' => ['creative'], 'events' => ['creative', 'couple'], 'backdrop' => false,
        ],
        'creative_group' => [
            'key' => 'creative_group', 'name' => 'Creative Session — 3–4 People', 'category' => 'Creative Session',
            'price' => 1500, 'duration' => '1 hr', 'min_people' => 3, 'max_people' => 4,
            'styles' => ['creative'], 'events' => ['creative', 'family', 'event'], 'backdrop' => false,
        ],
        'student_creative_solo' => [
            'key' => 'student_creative_solo', 'name' => 'Student Creative — Solo', 'category' => 'Student Creative',
            'price' => 500, 'duration' => '20 min', 'min_people' => 1, 'max_people' => 1,
            'styles' => ['student', 'creative'], 'events' => ['graduation', 'creative', 'portrait'], 'backdrop' => false,
        ],
        'student_creative_duo' => [
            'key' => 'student_creative_duo', 'name' => 'Student Creative — Duo', 'category' => 'Student Creative',
            'price' => 650, 'duration' => '30 min', 'min_people' => 2, 'max_people' => 2,
            'styles' => ['student', 'creative'], 'events' => ['graduation', 'creative', 'couple'], 'backdrop' => false,
        ],
        'student_creative_group' => [
            'key' => 'student_creative_group', 'name' => 'Student Creative — 3–4 People', 'category' => 'Student Creative',
            'price' => 1450, 'duration' => '1 hr', 'min_people' => 3, 'max_people' => 4,
            'styles' => ['student', 'creative'], 'events' => ['graduation', 'creative', 'family', 'event'], 'backdrop' => false,
        ],
        'backdrop' => [
            'key' => 'backdrop', 'name' => 'Creative Session with Backdrop', 'category' => 'Backdrop Add-On',
            'price' => 1500, 'duration' => '1 hr 30 min', 'min_people' => 1, 'max_people' => 4,
            'styles' => ['creative', 'backdrop'], 'events' => ['creative', 'portrait', 'family', 'event'], 'backdrop' => true,
        ],
    ];
}

/**
 * Return the highest matching service options first. This is an explainable,
 * weighted recommendation engine: hard constraints are group size; event,
 * price, style, and backdrop preference contribute to the ranking.
 */
function phodio_recommend_packages(string $eventType, int $people, int $budget, string $style, bool $wantsBackdrop = false, string $requirements = ''): array
{
    $packages = phodio_package_catalog();
    $ranked = [];
    $requirements = strtolower($requirements);
    $requirementEvents = [];
    $eventKeywords = [
        'portrait' => '/\b(portrait|headshot|solo)\b/i',
        'graduation' => '/\b(graduation|graduate|grad|toga)\b/i',
        'couple' => '/\b(couple|partner|duo)\b/i',
        'family' => '/\b(family|friends|group)\b/i',
        'creative' => '/\b(creative|theme|themed|concept)\b/i',
        'event' => '/\b(occasion|event|birthday|celebration)\b/i',
    ];
    foreach ($eventKeywords as $event => $pattern) {
        if (preg_match($pattern, $requirements)) {
            $requirementEvents[] = $event;
        }
    }
    $requirementBackdrop = (bool) preg_match('/\b(backdrop|background|set-up|setup)\b/i', $requirements);
    $requirementStudent = (bool) preg_match('/\b(student|school|college|campus)\b/i', $requirements);
    $requirementCreative = (bool) preg_match('/\b(creative|theme|themed|concept|colorful)\b/i', $requirements);
    $wantsBackdrop = $wantsBackdrop || $requirementBackdrop;

    foreach ($packages as $package) {
        if ($people < $package['min_people'] || $people > $package['max_people']) {
            continue;
        }

        $score = 35; // A package that can serve the requested group is a viable match.
        $reasons = ['Fits your group size'];

        if (in_array($eventType, $package['events'], true)) {
            $score += 25;
            $reasons[] = 'Matches your session type';
        }
        if (array_intersect($requirementEvents, $package['events'])) {
            $score += 10;
            $reasons[] = 'Matches details in your requirements';
        }
        if ($requirementStudent && strpos($package['category'], 'Student') !== false) {
            $score += 8;
            $reasons[] = 'Relevant to your student-related requirements';
        }
        if ($requirementCreative && in_array('creative', $package['styles'], true)) {
            $score += 6;
            $reasons[] = 'Relevant to your creative requirements';
        }

        if ($package['price'] <= $budget) {
            $score += 25;
            $reasons[] = 'Within your budget';
        } else {
            $over = $package['price'] - $budget;
            $score += max(-20, 10 - (int) ceil($over / 50));
            $reasons[] = '₱' . number_format($over) . ' over your budget';
        }

        if ($style !== 'any' && in_array($style, $package['styles'], true)) {
            $score += 20;
            $reasons[] = 'Matches your preferred style';
        }

        if ($wantsBackdrop) {
            if ($package['backdrop']) {
                $score += 20;
                $reasons[] = 'Includes the backdrop option';
            } else {
                $score -= 8;
            }
        } elseif ($package['backdrop']) {
            $score -= 3;
        }

        $ranked[] = [
            'key' => $package['key'],
            'name' => $package['name'],
            'category' => $package['category'],
            'price' => $package['price'],
            'duration' => $package['duration'],
            'score' => max(0, min(100, $score)),
            'reasons' => $reasons,
        ];
    }

    usort($ranked, static function (array $a, array $b): int {
        if ($a['score'] === $b['score']) {
            return $a['price'] <=> $b['price'];
        }
        return $b['score'] <=> $a['score'];
    });

    return array_slice($ranked, 0, 3);
}
