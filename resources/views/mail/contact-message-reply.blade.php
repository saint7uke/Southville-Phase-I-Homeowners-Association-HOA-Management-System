<x-mail::message>
<x-mail::title :text="$activity->subject ?? __('Response from the HOA office')" />

<x-mail::prose :text="__('Hello :name,', ['name' => $activity->contactMessage->name])" />

<x-mail::prose :text="$activity->body" />

<x-mail::details :rows="[__('Inquiry reference') => $activity->contactMessage->reference]" />

<x-mail::prose :text="__('Southville Phase I HOA Office')" />
</x-mail::message>
