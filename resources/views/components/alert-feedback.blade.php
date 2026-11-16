@php
    $feedback = [];
    if (session('action_feedback')) $feedback[] = session('action_feedback');
    if (session('success')) $feedback[] = \App\Support\ActionFeedback::flash('success', (string) session('success_title', session('success')), (string) session('success'));
    if (session('status')) $feedback[] = \App\Support\ActionFeedback::flash('info', 'Account update', (string) session('status'));
    if ($errors->any()) $feedback[] = \App\Support\ActionFeedback::flash('error', 'Please review the form', implode("\n", $errors->all()));
@endphp
@foreach ($feedback as $item)
    <div data-hoa-feedback="{{ json_encode($item) }}">
        <p data-hoa-feedback-fallback role="{{ $item['level'] === 'error' ? 'alert' : 'status' }}">{{ $item['message'] }}</p>
    </div>
@endforeach
