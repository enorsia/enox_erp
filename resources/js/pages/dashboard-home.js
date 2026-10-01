// Dashboard home — London Clock

(function () {
    "use strict";

    const TZ = "Europe/London";
    let timerId = null;

    function getLondonDate() {
        return new Date(
            new Date().toLocaleString("en-US", { timeZone: TZ }),
        );
    }

    function getTzLabel(date = new Date()) {
        return (
            new Intl.DateTimeFormat("en-GB", {
                timeZone: TZ,
                timeZoneName: "short",
            })
                .formatToParts(date)
                .find((p) => p.type === "timeZoneName")?.value ?? "GMT"
        );
    }

    function pad(n) {
        return String(n).padStart(2, "0");
    }

    function updateClock() {
        const hourHand = document.getElementById("londonHourHand");
        const minuteHand = document.getElementById("londonMinuteHand");
        const secondHand = document.getElementById("londonSecondHand");
        const digitalTime = document.getElementById("londonDigitalTime");
        const digitalDate = document.getElementById("londonDigitalDate");
        const tzLabel = document.getElementById("londonTzLabel");

        if (!hourHand || !minuteHand || !secondHand) return;

        const london = getLondonDate();
        const hours = london.getHours();
        const minutes = london.getMinutes();
        const seconds = london.getSeconds();
        const ms = london.getMilliseconds();
        const totalSeconds = seconds + ms / 1000;

        const secondDeg = totalSeconds * 6;
        const minuteDeg = minutes * 6 + totalSeconds * 0.1;
        const hourDeg = (hours % 12) * 30 + minutes * 0.5 + totalSeconds * (0.5 / 60);

        secondHand.style.transform = `translateX(-50%) rotate(${secondDeg}deg)`;
        minuteHand.style.transform = `translateX(-50%) rotate(${minuteDeg}deg)`;
        hourHand.style.transform = `translateX(-50%) rotate(${hourDeg}deg)`;

        if (digitalTime) {
            digitalTime.textContent = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
        }

        if (digitalDate) {
            digitalDate.textContent = london.toLocaleDateString("en-GB", {
                timeZone: TZ,
                weekday: "long",
                day: "2-digit",
                month: "short",
                year: "numeric",
            });
        }

        if (tzLabel) {
            tzLabel.textContent = getTzLabel();
        }
    }

    function tick() {
        updateClock();
        timerId = window.requestAnimationFrame(tick);
    }

    function init() {
        if (!document.getElementById("enox_home")) return;
        if (timerId) window.cancelAnimationFrame(timerId);
        updateClock();
        timerId = window.requestAnimationFrame(tick);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
