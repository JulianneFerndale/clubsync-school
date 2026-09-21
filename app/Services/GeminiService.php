<?php

namespace App\Services;

use App\Models\AiNotificationQueue;
use App\Models\Club;
use App\Models\ClubActivity;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    /**
     * Output budget for the short drafts. "gemini-flash-latest" does not always honour
     * thinkingBudget: 0, and thinking tokens count against maxOutputTokens — at 512 it
     * could spend ~490 tokens thinking and stop after a sentence fragment. This leaves
     * room for the answer even when the model thinks anyway.
     */
    private const SHORT_OUTPUT_TOKENS = 2048;

    /** Output budget for the end-of-semester narrative, which covers every activity. */
    private const REPORT_OUTPUT_TOKENS = 8192;

    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('gemini.api_key', '');
        $this->model   = config('gemini.model', 'gemini-1.5-flash');
        $this->baseUrl = config('gemini.base_url');
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey) && $this->apiKey !== 'your_gemini_api_key';
    }

    /**
     * The model's finished answer, or null when there is none.
     *
     * A candidate that stopped for any reason other than STOP (MAX_TOKENS, SAFETY, ...)
     * holds a partial answer, and a partial answer must never be stored as a draft —
     * it would be flagged as a successful AI output while being cut off mid-sentence.
     * Treating it as a failed generation sends the caller down its manual-draft path
     * instead (POLICY.md: "AI Unavailable — Manual Draft Required").
     */
    private function answerText(Response $response, string $caller): ?string
    {
        $candidate = $response->json('candidates.0');
        $finish    = $candidate['finishReason'] ?? null;

        if ($finish !== null && $finish !== 'STOP') {
            Log::warning("GeminiService::{$caller} stopped early ({$finish}); the partial answer was discarded.");

            return null;
        }

        // Join every answer part, skipping any thought summaries the model returns.
        $text = collect($candidate['content']['parts'] ?? [])
            ->reject(fn ($part) => ! empty($part['thought']))
            ->pluck('text')
            ->filter(fn ($part) => is_string($part))
            ->implode('');

        $text = trim($text);

        return $text === '' ? null : $text;
    }

    /**
     * Generate an announcement draft for a student club.
     *
     * @param  string  $title    The announcement title / topic
     * @param  string  $type     'announcement' or 'letter'
     * @param  string  $clubName The club's name
     * @param  string  $context  Optional extra context from the officer
     * @return string|null       Generated content, or null on failure
     */
    public function draftAnnouncement(string $title, string $type, string $clubName, string $context = ''): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $typeLabel = $type === 'letter' ? 'formal letter' : 'announcement';

        // The officer may give a title, a free-text description of the topic, or both.
        $topic = $title !== ''
            ? "Title: {$title}"
            : 'No title was given — infer the topic from the officer\'s description below.';

        $brief = $context !== '' ? "Description from the officer: {$context}" : '';

        $prompt = <<<PROMPT
You are a student organization communications assistant for {$clubName}, a college club in the Philippines.

Write a professional {$typeLabel} for the following topic:
{$topic}
{$brief}

Requirements:
- Tone: professional but friendly, appropriate for a college club
- Length: 2–4 short paragraphs
- Language: English
- Use ONLY the details given above. Do NOT invent dates, times, venues, fees, names, or figures that were not provided — leave them out rather than guessing.
- Do NOT include greeting headers, sign-offs, or "Dear..." lines — only the body content
- Output plain text only, no markdown formatting
PROMPT;

        try {
            $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(15)->post($url, [
                'contents' => [
                    [
                        'parts' => [['text' => $prompt]],
                    ],
                ],
                'generationConfig' => [
                    'temperature'     => 0.7,
                    'maxOutputTokens' => self::SHORT_OUTPUT_TOKENS,
                    // Ask for no thinking so the budget goes on the answer (see SHORT_OUTPUT_TOKENS).
                    'thinkingConfig'  => ['thinkingBudget' => 0],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('Gemini API error: ' . $response->body());
                return null;
            }

            return $this->answerText($response, 'draftAnnouncement');
        } catch (\Throwable $e) {
            Log::warning('GeminiService::draftAnnouncement failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Draft an activity proposal from what the officer has sketched out — the
     * description and purpose/objectives, plus the turnout estimate and the year
     * levels the activity is aimed at.
     *
     * $roster is the club's own membership: the active members this activity would
     * reach, and how they split across year levels. The turnout estimate is anchored
     * to it rather than made up — a club of 14 cannot be drafted a proposal expecting
     * 200 — and with no roster to anchor to, no estimate is offered at all.
     *
     * This is a DRAFT for the officer to read, edit and submit themselves. It never
     * creates an activity: the human gate is the officer reviewing the form before
     * pressing submit (POLICY.md § AI & Automation).
     *
     * @param  array{total?: int, by_year?: array<int, int>}  $roster
     * @return array{description: string, purpose: string, expected_participants: int|null, target_year_levels: array<int, int>}|null
     *         Null on failure or when AI is off.
     */
    public function draftActivityProposal(
        string $title,
        string $clubName,
        string $venue,
        string $date,
        string $timeStart,
        string $timeEnd,
        string $context = '',
        array $roster = [],
    ): ?array {
        if (! $this->isAvailable()) {
            return null;
        }

        $extra = $context !== '' ? "Additional context from the officer: {$context}" : '';

        $rosterTotal = max(0, (int) ($roster['total'] ?? 0));

        $rosterLine = $rosterTotal > 0
            ? "Club membership on record: {$rosterTotal} active member(s)" . $this->yearLevelBreakdown($roster['by_year'] ?? []) . '.'
            : 'Club membership on record: not available.';

        // With no roster the model has nothing to reason from, so it is told to say so
        // rather than guess a number the officer might not think to check.
        $turnoutRule = $rosterTotal > 0
            ? '- "expected_participants": integer between 1 and ' . $rosterTotal . ' — how many people to realistically expect, reasoned from the membership figures above and the nature of the activity. Never more than the club has active members.'
            : '- "expected_participants": null — the club\'s membership figures are not available, so do not estimate a turnout.';

        $prompt = <<<PROMPT
You are helping an officer of {$clubName}, a college club in the Philippines, write a proposal for a campus activity.

Activity title: {$title}
Scheduled: {$date}, {$timeStart} to {$timeEnd}
Venue: {$venue}
{$rosterLine}
{$extra}

Return a JSON object with exactly four keys:
- "description": 2–3 sentences describing what the activity is and what will happen.
- "purpose": 2–3 sentences stating the purpose and the concrete objectives, phrased for a school administrator reviewing it for approval.
{$turnoutRule}
- "target_year_levels": array of integers from 1 to 4 — the year levels this activity is specifically for, e.g. [1] for a first-year orientation. Most activities are open to everyone: return an empty array unless the title or the officer's context says it is aimed at particular year levels.

Requirements:
- Tone: professional, concrete, appropriate for a college club submitting to school administration
- Language: English
- Plain text inside the JSON string values — no markdown, no bullet characters
- "expected_participants" is an estimate for the officer to correct, not a figure on record: base it only on the membership above, and never present it as recorded attendance
- Do not invent any other figures, budgets, or names of people
PROMPT;

        try {
            $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(15)->post($url, [
                'contents' => [
                    [
                        'parts' => [['text' => $prompt]],
                    ],
                ],
                'generationConfig' => [
                    'temperature'      => 0.6,
                    'maxOutputTokens'  => self::SHORT_OUTPUT_TOKENS,
                    'responseMimeType' => 'application/json',
                    'thinkingConfig'   => ['thinkingBudget' => 0],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('Gemini API error: ' . $response->body());
                return null;
            }

            $raw = $this->answerText($response, 'draftActivityProposal');

            if ($raw === null) {
                return null;
            }

            $decoded = json_decode($raw, true);

            if (! is_array($decoded) || ! isset($decoded['description'], $decoded['purpose'])) {
                Log::warning('GeminiService::draftActivityProposal returned unusable JSON.');
                return null;
            }

            // The prose is what makes a draft usable, so it alone decides whether there
            // is a draft at all. The two form fields below it are sanitised on their own
            // terms and simply come back empty when the model omits or spoils them — the
            // officer then fills them in exactly as they did before.
            return [
                'description'           => trim((string) $decoded['description']),
                'purpose'               => trim((string) $decoded['purpose']),
                'expected_participants' => $this->estimatedTurnout($decoded['expected_participants'] ?? null, $rosterTotal),
                'target_year_levels'    => $this->targetYearLevels($decoded['target_year_levels'] ?? null),
            ];
        } catch (\Throwable $e) {
            Log::warning('GeminiService::draftActivityProposal failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * The roster split by year level, for the prompt — " (1st Year: 4, 3rd Year: 9)".
     *
     * Year levels nobody is in are left out rather than written as zero: a club with
     * no 2nd years is not the same claim as a club whose 2nd years all stayed home,
     * and only the first is something the roster actually says.
     *
     * @param  array<int, int>  $byYear
     */
    private function yearLevelBreakdown(array $byYear): string
    {
        $parts = [];

        foreach ([1, 2, 3, 4] as $level) {
            $count = (int) ($byYear[$level] ?? 0);

            if ($count > 0) {
                $parts[] = year_level_label($level) . ": {$count}";
            }
        }

        return $parts ? ' (' . implode(', ', $parts) . ')' : '';
    }

    /**
     * Hold the drafted turnout inside what the club can actually field.
     *
     * The prompt asks for this, but a prompt is not a guarantee: the cap is applied
     * here too, so a model answering 500 for a club of 12 cannot put 500 in front of
     * the officer. An estimate with no roster behind it is dropped entirely — better
     * an empty field the officer must fill than a number they might not check.
     */
    private function estimatedTurnout(mixed $value, int $rosterTotal): ?int
    {
        if ($rosterTotal < 1 || ! is_numeric($value)) {
            return null;
        }

        $estimate = (int) $value;

        return $estimate < 1 ? null : min($estimate, $rosterTotal);
    }

    /**
     * Sanitise the drafted year levels: 1–4 only, unique, sorted.
     *
     * All four levels is the same activity as none — open to everybody — and the form
     * says that with no box ticked, so it is normalised to empty. That keeps the draft
     * from reading as deliberate year-level targeting when it is not.
     *
     * @return array<int, int>
     */
    private function targetYearLevels(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $levels = array_values(array_unique(array_filter(
            array_map(fn ($level) => is_numeric($level) ? (int) $level : 0, $value),
            fn (int $level) => $level >= 1 && $level <= 4,
        )));

        sort($levels);

        return count($levels) === 4 ? [] : $levels;
    }

    /**
     * The accuracy rules both narratives are written under.
     *
     * An activity's description and purpose are written when it is proposed, so on
     * their own they say what was planned, not what happened. Without these rules the
     * model turns "to ratify the committee heads" into "members ratified the committee
     * heads" — an outcome nothing on record confirms.
     */
    private const NARRATIVE_ACCURACY_RULES = <<<'RULES'
- The description and purpose were written by the officer BEFORE the activity took place. Present them only as what the activity was planned or organised to do (for example "was organised to", "aimed to"). Never state that an objective was achieved, that participants learned, enjoyed, completed, voted on or produced something, or describe how the activity went, unless the officer's post-activity report says so.
- Use every figure exactly as recorded. Do not calculate new figures, round differently, or estimate.
- Do not invent names, speakers, quotes, feedback, results, or any detail that is not in the records.
- If attendance was not recorded for an activity, say so plainly.
- Mention an officer's post-activity report only where one was submitted; do not remark on its absence.
RULES;

    /**
     * Draft a short post-activity recap for the club bulletin from the activity's record.
     * The output is a DRAFT only — it must pass through adviser review before members see it.
     *
     * @return string|null Generated narrative, or null on failure / when AI is unavailable
     */
    public function draftClubNarrative(ClubActivity $activity, Club $club): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $facts = NarrativeReportData::activityText(NarrativeReportData::activity($activity));
        $rules = self::NARRATIVE_ACCURACY_RULES;

        $prompt = <<<PROMPT
You are a student organization communications assistant for {$club->name}, a college club at Saint Columban College of Pagadian City, Philippines.

Write a short recap of this completed activity for the club bulletin, so members and the community can see what the club has been up to.

The record below comes from ClubSync and is the only source of truth:
{$facts}

Requirements:
- Tone: warm and engaging, but strictly factual
- Length: 2-3 short paragraphs
- Language: English
- State the activity type, date, time and venue, and its attendance (actual attendees against expected participants), exactly as recorded.
{$rules}
- Do NOT include headers, titles, or sign-offs — only the recap body
- Output plain text only, no markdown formatting
PROMPT;

        try {
            $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(30)->post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature'     => 0.5,
                    'maxOutputTokens' => self::SHORT_OUTPUT_TOKENS,
                    // Ask for no thinking so the budget goes on the answer (see SHORT_OUTPUT_TOKENS).
                    'thinkingConfig'  => ['thinkingBudget' => 0],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('Gemini API error: ' . $response->body());
                return null;
            }

            return $this->answerText($response, 'draftClubNarrative');
        } catch (\Throwable $e) {
            Log::warning('GeminiService::draftClubNarrative failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Draft the end-of-semester narrative report a club submits to the DSA, covering
     * every activity it completed that semester.
     *
     * $data is NarrativeReportData::semester(). The output is a DRAFT only: the club
     * adviser reviews and edits it before it is submitted (POLICY.md § AI & Automation).
     *
     * @return string|null Generated narrative, or null on failure / when AI is unavailable
     */
    public function draftSemesterNarrative(array $data): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $facts = NarrativeReportData::semesterText($data);
        $rules = self::NARRATIVE_ACCURACY_RULES;

        $prompt = <<<PROMPT
You are preparing the end-of-semester narrative report that {$data['club']}, a student club at Saint Columban College of Pagadian City, Philippines, submits to the Director of Student Affairs (DSA). The club adviser will review and edit your draft before it is submitted.

The records below come from ClubSync. Treat them as the only source of truth.

{$facts}

Write the body of the narrative report.

Structure:
1. An opening paragraph summarising the semester: how many activities were completed, of which types, and the total attendance figures given above.
2. One paragraph for each activity, in the order listed. Name the activity by its exact title in double quotes, and state its type, date, time and venue, what it was (from its description), what it was organised to achieve (from its purpose), and its attendance: the number of actual attendees against the expected participants.
3. A closing paragraph summarising the club's participation over the semester, using only the totals given above.

Accuracy rules — these matter more than style:
{$rules}

Format:
- Formal, objective tone suitable for a school administrator, written in the third person
- Language: English
- Plain text only: no markdown, no bullet points, no headings, no title, no sign-off
- Separate paragraphs with a blank line
PROMPT;

        try {
            $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(90)->post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature'     => 0.3,
                    'maxOutputTokens' => self::REPORT_OUTPUT_TOKENS,
                    'thinkingConfig'  => ['thinkingBudget' => 0],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('Gemini API error: ' . $response->body());
                return null;
            }

            return $this->answerText($response, 'draftSemesterNarrative');
        } catch (\Throwable $e) {
            Log::warning('GeminiService::draftSemesterNarrative failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Draft a compliance/violation reminder statement for a club, on behalf of the DSA.
     */
    public function generateViolationStatement(AiNotificationQueue $violation, Club $club): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $prompt = <<<PROMPT
You are drafting an official compliance reminder from the Director of Student Affairs of Saint Columban College of Pagadian City to the officers and adviser of the {$club->name} club.

The violation is: {$violation->gap_label}.
Details: {$violation->description}

Write a professional, firm, but respectful reminder statement in 2-3 short paragraphs. Include what action is required and a general deadline of 5 working days. Do not include a signature block — it will be added separately. Output plain text only, no markdown formatting.
PROMPT;

        try {
            $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(15)->post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature'     => 0.6,
                    'maxOutputTokens' => self::SHORT_OUTPUT_TOKENS,
                    // Ask for no thinking so the budget goes on the answer (see SHORT_OUTPUT_TOKENS).
                    'thinkingConfig'  => ['thinkingBudget' => 0],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('Gemini API error: ' . $response->body());
                return null;
            }

            return $this->answerText($response, 'generateViolationStatement');
        } catch (\Throwable $e) {
            Log::warning('GeminiService::generateViolationStatement failed: ' . $e->getMessage());
            return null;
        }
    }
}
