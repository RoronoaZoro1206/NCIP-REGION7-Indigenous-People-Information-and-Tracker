<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        .scroll-top-btn {
        position: fixed;
        bottom: 40px;
        right: 40px;
        width: 35px;
        height: 35px;
        background: url("images/bg-pattern.png");
        border: none;
        border-radius: 50%;
        /* box-shadow: 0 2px 8px rgba(0,0,0,0.2); */
        cursor: pointer;
        z-index: 999;
        overflow: hidden;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.5s ease, transform 0.5s ease, box-shadow 0.5s ease;
        }

        .scroll-top-btn.show {
            opacity: 1;
            pointer-events: auto;
        }

        .scroll-top-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 8px rgba(145, 147, 3, 0.7);
        }

        .scroll-top-btn .arrow-up {
        position: absolute;
        top: 45%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 16px;
        color: #0c243d;
        }

        .progress-ring {
        position: absolute;
        top: 0;
        left: 0;
        transform: rotate(-90deg);
        width: 100%;
        height: 100%;
        }

        .progress-ring__circle {
        stroke-dasharray: 283; /* circumference: 2 * π * r */
        stroke-dashoffset: 283;
        transition: stroke-dashoffset 0.35s;
        transform: rotate(0deg);
        transform-origin: 50% 50%;
        stroke: #919303;
        }
    </style>
</head>
<body>
    <button id="scrollToTop" class="scroll-top-btn">
        <svg viewBox="0 0 100 100" class="progress-ring">
            <circle class="progress-ring__circle" stroke="black" stroke-width="4" fill="transparent" r="45" cx="50" cy="50" />
        </svg>
        <span class="arrow-up">↑</span>
    </button>

    <script>
        const scrollToTopBtn = document.getElementById('scrollToTop');
        const progressCircle = document.querySelector('.progress-ring__circle');

        function updateProgress() {
        const scrollTotal = document.documentElement.scrollHeight - window.innerHeight;
        const scrollY = window.scrollY;
        const progress = scrollY / scrollTotal;
        const circumference = 2 * Math.PI * 45; // r=45
        const offset = circumference * (1 - progress);
        progressCircle.style.strokeDashoffset = offset;

        // Show or hide the button
        if (scrollY > 100) { // after 100px scroll
            scrollToTopBtn.classList.add('show');
        } else {
            scrollToTopBtn.classList.remove('show');
        }
        }

        window.addEventListener('scroll', updateProgress);

        scrollToTopBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>
</body>
</html>