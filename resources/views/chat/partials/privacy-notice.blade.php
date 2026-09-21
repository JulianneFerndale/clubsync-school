{{--
    Chat privacy notice (RA 10173 — transparency). Shown in full before first use,
    and kept one tap away afterwards. Keep it accurate: if chat starts collecting,
    sharing or keeping anything new, this text must change with it.
--}}
<div class="space-y-4 text-sm text-gray-700 leading-relaxed">
    <div>
        <h3 class="font-semibold text-gray-900 mb-1">What is collected</h3>
        <p>The messages you send, who you send them to, when you sent them, and the date you accepted this notice.
           Nothing else is added to your profile.</p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 mb-1">Why</h3>
        <p>Only to deliver your messages to the person you are talking to. Messages are never used for
           club records, reports, engagement scores or AI features.</p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 mb-1">Who can read your messages</h3>
        <p>Only you and the person you are messaging. Messages are encrypted when stored, and no part of
           ClubSync lets club officers, advisers, the Director of Student Affairs or system administrators
           open your conversations. When someone searches for you, they see only your name, a general role
           (for example "Student") and your profile photo. Your email and EDP number are not shown.</p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 mb-1">How long messages are kept</h3>
        <ul class="list-disc pl-5 space-y-1">
            <li>Messages are deleted automatically after {{ $retentionDays }} days.</li>
            <li>You can unsend any message you sent, at any time. Its text is erased.</li>
            <li>Deleting a conversation removes it from your inbox. Once both people have deleted it,
                the messages are erased from the system.</li>
            <li>A security log records that a message was sent, by whom and to whom, but never what it says.
                School policy requires keeping that log for at least one academic year.</li>
        </ul>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 mb-1">Your rights (Data Privacy Act of 2012)</h3>
        <p>You may ask to access, correct or erase your personal data, or object to how it is processed.
           Contact the Director of Student Affairs or the school's Data Protection Officer.</p>
    </div>

    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-amber-900">
        <p class="font-semibold mb-1">Please be responsible</p>
        <p>Don't share other people's personal information (EDP numbers, grades, addresses, photos) without
           their consent. Don't use chat for official submissions. If someone makes you uncomfortable,
           block them from the conversation menu.</p>
    </div>
</div>
