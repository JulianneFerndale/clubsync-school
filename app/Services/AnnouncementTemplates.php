<?php

namespace App\Services;

/**
 * Ready-made structures for the announcements and letters clubs post most often.
 *
 * The AI draft is optional; these always work: offline, while Gemini is down, or when
 * the AI is not configured at all (POLICY.md § AI & Automation: manual alternatives stay
 * fully functional). Each one fills the content box with the usual structure, leaving
 * the details in [brackets] for the officer to fill in. Nothing is invented: what the
 * system cannot know stays a bracket, and the page warns before a bracket is posted.
 */
class AnnouncementTemplates
{
    /**
     * @return array<string, array{label: string, type: string, content: string}>
     */
    public static function all(string $clubName): array
    {
        $club = trim($clubName) !== '' ? $clubName : '[club name]';

        return [
            'meeting' => [
                'label'   => 'Meeting or general assembly',
                'type'    => 'announcement',
                'content' => "All members are invited to [meeting name] on [day and date] at [time], at [venue].\n\n"
                    . "We will take up the following: [agenda item 1], [agenda item 2] and [agenda item 3]. "
                    . "Please come on time and bring [what to bring, if any].\n\n"
                    . 'Attendance will be recorded in ClubSync. For questions, message any club officer.',
            ],
            'invitation' => [
                'label'   => 'Activity invitation',
                'type'    => 'announcement',
                'content' => "{$club} invites you to [activity title], to be held on [date] from [start time] to [end time] at [venue].\n\n"
                    . "[One or two sentences on what the activity is and why members should join.]\n\n"
                    . '[How to register or confirm attendance, and by when.] We hope to see you there!',
            ],
            'fee' => [
                'label'   => 'Fee reminder',
                'type'    => 'announcement',
                'content' => "This is a reminder that the [fee name] of PHP [amount] is due on [due date].\n\n"
                    . "Please pay the club treasurer at [where and when to pay]. An official receipt will be issued, "
                    . "and your payment will be recorded in ClubSync.\n\n"
                    . 'Members who have already paid may disregard this reminder. Thank you!',
            ],
            'change' => [
                'label'   => 'Schedule change or cancellation',
                'type'    => 'announcement',
                'content' => "Please be informed that [activity title], originally scheduled on [original date and time], "
                    . "has been [moved to the new date, time and venue / postponed / cancelled].\n\n"
                    . "This is because [reason].\n\n"
                    . '[What members need to do, if anything.] We apologize for the inconvenience and thank you for your understanding.',
            ],
            'volunteers' => [
                'label'   => 'Call for volunteers or participants',
                'type'    => 'announcement',
                'content' => "We are looking for [number] members to [role or task] for [activity title] on [date].\n\n"
                    . "Volunteers will [what they will do]. [Requirements, such as year level or skills, if any.]\n\n"
                    . 'To sign up, [how to sign up] on or before [deadline].',
            ],
            'thanks' => [
                'label'   => 'Thank-you and recap',
                'type'    => 'announcement',
                'content' => "Thank you to everyone who joined [activity title] on [date]!\n\n"
                    . "[One or two sentences on what was done.]\n\n"
                    . '[Next steps or the next activity, if any.] See you at our next activity!',
            ],
            'request_letter' => [
                'label'   => 'Request to use a venue or equipment',
                'type'    => 'letter',
                'content' => "{$club} respectfully requests permission to use [venue or equipment] on [date] "
                    . "from [start time] to [end time] for [activity title].\n\n"
                    . "The activity aims to [purpose]. We expect about [number] participants, and the officers will be "
                    . "responsible for the orderly use and return of the [venue or equipment].\n\n"
                    . 'We hope for your favorable consideration of this request. Thank you.',
            ],
            'invitation_letter' => [
                'label'   => 'Invitation to a speaker or guest',
                'type'    => 'letter',
                'content' => "{$club} is honored to invite you to be our [resource speaker / guest] at [activity title], "
                    . "on [date] at [time], at [venue].\n\n"
                    . "The activity aims to [purpose]. [What the guest would be asked to do, and for how long.]\n\n"
                    . 'We hope you can accept this invitation. Kindly let us know your response by [date]. Thank you.',
            ],
        ];
    }
}
