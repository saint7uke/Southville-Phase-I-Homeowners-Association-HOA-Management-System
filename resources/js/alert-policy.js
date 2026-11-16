export const errorMessages = {
    400: 'Please review the submitted information.',
    401: 'Please sign in to continue.',
    403: 'You do not have permission to perform this action.',
    404: 'This record is no longer available. Refresh the page before continuing.',
    419: 'Your session has expired. Refresh the page and sign in again before retrying.',
    422: 'Please review the highlighted fields.',
    429: 'Too many requests. Please wait before trying again.',
};

export function requestFeedback(error = {}) {
    const status = Number(error.response?.status ?? error.status ?? 0);
    const errors = error.response?.data?.errors ?? error.errors;
    const validation = [400, 422].includes(status) && errors && typeof errors === 'object'
        ? [...new Set(Object.values(errors).flat().filter(value => typeof value === 'string'))].slice(0, 8) : [];
    return {
        level: 'error', title: validation.length ? 'Please review the form' : 'Unable to complete the action',
        message: validation.length ? validation.join('\n') : (errorMessages[status] ?? (status >= 500
            ? 'The server could not complete the request. Check the record before trying again.'
            : 'The connection was interrupted. Check whether the action completed before trying again.')),
    };
}

export function createFeedbackQueue(display) {
    const seen = new Set();
    let queue = Promise.resolve();
    return function enqueue(feedback) {
        if (feedback.id && seen.has(feedback.id)) return Promise.resolve(false);
        if (feedback.id) seen.add(feedback.id);
        const task = queue.then(() => display(feedback));
        queue = task.catch(() => {});
        return task.catch(() => {
            if (feedback.id) seen.delete(feedback.id);
            return false;
        });
    };
}
