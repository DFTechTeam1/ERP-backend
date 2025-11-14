<x-mail::message>
# 🔔 UPCOMING: Team Assignment Deadline

**To:** {{ $marcommPic->name }}  
**Priority:** Medium

Reminder to assign your teams to the following upcoming project(s):

## 📋 Projects Requiring Assignments:
<x-mail::table>
| # | Project Name | Start Date | Venue/Location |
| :---: | --- | --- | --- |
@foreach($projects as $index => $project)
| {{ $index + 1 }} | {{ $project->name }} | {{ date('d F Y', strtotime($project->project_date)) }} | {{ $project->venue }} {{ $project->country->name }}, {{ $project->state->name }}, {{ $project->city->name }} |
@endforeach
</x-mail::table>

## 📍 Assignment Portal: {{ $assignmentPortal }}

Please address this before the deadline to ensure proper resource allocation and project readiness.

<x-mail::button :url="$assignmentPortal">
Assign Team Members
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>