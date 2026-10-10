// Keeps every "time left" label on the page counting down.
// The server puts the remaining seconds in data-seconds-left.

function formatTimeLeft(seconds) {
    if (seconds <= 0) {
        return 'Ended';
    }
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (days > 0) {
        return days + 'd ' + hours + 'h left';
    }
    if (hours > 0) {
        return hours + 'h ' + minutes + 'm left';
    }
    return minutes + 'm ' + (seconds % 60) + 's left';
}

document.querySelectorAll('[data-seconds-left]').forEach(function (label) {
    const endsAt = Date.now() + Number(label.dataset.secondsLeft) * 1000;

    function update() {
        const seconds = Math.floor((endsAt - Date.now()) / 1000);
        label.textContent = formatTimeLeft(seconds);
        label.classList.toggle('soon', seconds > 0 && seconds < 3600);
    }

    update();
    setInterval(update, 1000);
});
