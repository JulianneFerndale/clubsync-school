@props(['status'])

@php
$config = match(strtolower($status)) {
    'pending', 'scheduled', 'pending_review', 'draft' => ['bg-[#F9A825]/20 text-[#F9A825]', 'Pending'],
    'approved' => ['bg-green-50 text-[#07873e] border border-green-300', 'Approved'],
    'rejected' => ['bg-red-50 text-red-600 border border-red-200', 'Rejected'],
    'published', 'completed', 'paid', 'active', 'ready' => ['bg-green-100 text-green-700', ucfirst($status)],
    'cancelled', 'overdue', 'inactive' => ['bg-red-100 text-red-600', ucfirst($status)],
    'revision_required' => ['bg-orange-100 text-orange-600', 'Revision Needed'],
    'submitted' => ['bg-blue-100 text-blue-600', 'Submitted'],
    'academic' => ['bg-green-100 text-green-700', 'Academic'],
    'non_academic' => ['bg-blue-100 text-blue-600', 'Non-Academic'],
    'urgent' => ['bg-red-100 text-red-600', 'Urgent'],
    'no_approval_needed' => ['bg-gray-100 text-gray-500', 'No Approval Needed'],
    'pending_approval' => ['bg-yellow-50 text-[#F9A825] border border-yellow-300', 'Pending Approval'],
    'event' => ['bg-purple-100 text-purple-600', 'Event'],
    'notice' => ['bg-blue-100 text-blue-600', 'Notice'],
    'general' => ['bg-gray-100 text-gray-600', 'General'],
    default => ['bg-gray-100 text-gray-600', ucfirst(str_replace('_', ' ', $status))],
};
@endphp

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $config[0] }}">
    {{ $config[1] }}
</span>
