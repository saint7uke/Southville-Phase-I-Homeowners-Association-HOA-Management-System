<!doctype html>
<html lang="en">
<body>
    <p>{{ __('Hello :name,', ['name' => $activity->contactMessage->name]) }}</p>
    <p>{!! nl2br(e($activity->body)) !!}</p>
    <p>{{ __('Inquiry reference: :reference', ['reference' => $activity->contactMessage->reference]) }}</p>
    <p>{{ __('Southville Phase I HOA Office') }}</p>
</body>
</html>
