type Phase = 'intro' | 'appearing' | 'falling' | 'pause' | 'lost' | 'won';

type Point = { x: number; y: number };

type FallingStar = {
    start: number;
    xRatio: number;
    yRatio: number;
    x: number;
    y: number;
    rotation: number;
    scale: number;
    opacity: number;
    trail: Point[];
};

const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value));
const random = (min: number, max: number) => min + Math.random() * (max - min);

export const setupNotFoundGame = () => {
    const root = document.querySelector<HTMLElement>('[data-not-found-game]');
    if (!root || root.dataset.gameReady === '1') return;
    root.dataset.gameReady = '1';

    const stage = root.querySelector<HTMLElement>('[data-game-stage]');
    const canvas = root.querySelector<HTMLCanvasElement>('[data-game-sky]');
    const scoreNode = root.querySelector<HTMLElement>('[data-game-score]');
    const scoreDigits = scoreNode?.querySelectorAll<HTMLElement>('[data-game-digit]');
    const title = root.querySelector<HTMLElement>('[data-game-title]');
    const planet = root.querySelector<HTMLElement>('[data-game-planet]');
    const orbit = root.querySelector<HTMLElement>('[data-game-orbit]');
    const catcher = root.querySelector<HTMLElement>('[data-game-catcher]');
    const starNode = root.querySelector<HTMLImageElement>('[data-game-star]');
    const retry = root.querySelector<HTMLButtonElement>('[data-game-retry]');
    const status = root.querySelector<HTMLElement>('[data-game-status]');
    const context = canvas?.getContext('2d');
    if (!stage || !canvas || !scoreNode || scoreDigits?.length !== 3 || !title || !planet || !orbit || !catcher || !starNode || !retry || !status || !context) return;

    let phase: Phase = 'intro';
    let lives = 4;
    let score = 4;
    let wave = 0;
    let phaseUntil = performance.now() + 5000;
    let activeStar: FallingStar | null = null;
    let targetAngle = 0;
    let catcherAngle = 0;
    let lastFrame = performance.now();
    let width = 0;
    let height = 0;
    let planetX = 0;
    let planetY = 0;
    let planetRadius = 0;
    let orbitRadius = 0;

    const skyStars = Array.from({ length: 96 }, () => ({
        x: Math.random(),
        y: Math.random(),
        radius: random(0.45, 1.8),
        phase: random(0, Math.PI * 2),
        speed: random(0.0007, 0.0021),
    }));

    const speed = () => Math.min(3.5, 1 + wave * 0.08);
    const setScore = (announce = true) => {
        const value = `${lives}${String(score).padStart(2, '0')}`;
        scoreNode.dataset.score = value;
        scoreDigits.forEach((digit, index) => { digit.textContent = value[index]; });
        if (announce) status.textContent = value;
    };

    const resize = () => {
        const rect = stage.getBoundingClientRect();
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        width = rect.width;
        height = rect.height;
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;
        context.setTransform(ratio, 0, 0, ratio, 0, 0);

        const diameter = clamp(Math.min(width * 0.3, height * 0.78), 210, 540);
        planetRadius = diameter / 2;
        planetX = width / 2;
        planetY = height;
        orbitRadius = planetRadius + catcher.offsetWidth * 0.3;

        planet.style.width = `${diameter}px`;
        planet.style.height = `${diameter}px`;
        planet.style.transform = `translate(${planetX - planetRadius}px, ${planetY - planetRadius}px)`;
        orbit.style.width = `${orbitRadius * 2}px`;
        orbit.style.height = `${orbitRadius * 2}px`;
        orbit.style.transform = `translate(${planetX - orbitRadius}px, ${planetY - orbitRadius}px)`;
    };

    const catcherPoint = (angle: number) => ({
        x: planetX + Math.sin(angle) * orbitRadius,
        y: planetY - Math.cos(angle) * orbitRadius,
    });

    const moveTarget = (clientX: number) => {
        if (phase === 'lost' || phase === 'won') return;
        const rect = stage.getBoundingClientRect();
        const pointerX = clientX - rect.left;
        const normalizedOffset = clamp((pointerX - planetX) / planetRadius, -1, 1);
        targetAngle = normalizedOffset * (Math.PI / 2);
    };

    const spawnStar = (now: number) => {
        activeStar = {
            start: now,
            xRatio: random(0.1, 0.9),
            yRatio: random(0.08, 0.43),
            x: 0,
            y: 0,
            rotation: random(-25, 25),
            scale: 0.02,
            opacity: 0,
            trail: [],
        };
        phase = 'appearing';
        starNode.hidden = false;
    };

    const hideStar = () => {
        activeStar = null;
        starNode.hidden = true;
    };

    const scheduleNext = (now: number) => {
        phase = 'pause';
        phaseUntil = now + random(1200, 3000) / speed();
        wave += 1;
    };

    const insideCatcherMask = (star: FallingStar) => {
        const point = catcherPoint(catcherAngle);
        const size = catcher.offsetWidth;
        const cos = Math.cos(catcherAngle);
        const sin = Math.sin(catcherAngle);
        const dx = star.x - point.x;
        const dy = star.y - point.y;
        const localX = dx * cos + dy * sin;
        const localY = -dx * sin + dy * cos;
        if (localY < size * -0.34 || localY > size * 0.22) return false;
        const halfWidth = size * 0.47 - Math.max(0, localY + size * 0.12) * 0.18;
        return Math.abs(localX) <= halfWidth;
    };

    const catchStar = (now: number) => {
        hideStar();
        score += 1;
        setScore();
        catcher.classList.remove('is-catching');
        void catcher.offsetWidth;
        catcher.classList.add('is-catching');
        if (score >= 99) {
            phase = 'won';
            root.classList.add('is-won');
            title.textContent = root.dataset.winText || title.textContent;
            status.textContent = title.textContent;
            return;
        }
        scheduleNext(now);
    };

    const hitPlanet = (now: number) => {
        hideStar();
        lives -= 1;
        setScore();
        planet.classList.remove('is-hit');
        void planet.offsetWidth;
        planet.classList.add('is-hit');
        if (lives <= 0) {
            phase = 'lost';
            root.classList.add('is-lost');
            status.textContent = retry.textContent || 'Retry';
            window.setTimeout(() => {
                if (root.isConnected) retry.hidden = false;
            }, 2800);
            return;
        }
        scheduleNext(now + 560);
    };

    const drawSky = (now: number) => {
        context.clearRect(0, 0, width, height);
        for (const dot of skyStars) {
            const alpha = 0.22 + (Math.sin(now * dot.speed + dot.phase) + 1) * 0.3;
            context.beginPath();
            context.fillStyle = `rgba(255, 241, 218, ${alpha})`;
            context.arc(dot.x * width, dot.y * height, dot.radius, 0, Math.PI * 2);
            context.fill();
        }

        if (!activeStar) return;
        const trail = activeStar.trail;
        if (trail.length > 1) {
            const oldest = trail[trail.length - 1];
            const newest = trail[0];
            const glow = context.createLinearGradient(oldest.x, oldest.y, newest.x, newest.y);
            glow.addColorStop(0, 'rgba(255, 255, 255, 0)');
            glow.addColorStop(1, `rgba(255, 255, 255, ${0.78 * activeStar.opacity})`);

            context.save();
            context.beginPath();
            context.moveTo(oldest.x, oldest.y);
            for (let index = trail.length - 2; index >= 0; index -= 1) {
                context.lineTo(trail[index].x, trail[index].y);
            }
            context.strokeStyle = glow;
            context.lineWidth = 3.5;
            context.lineCap = 'round';
            context.lineJoin = 'round';
            context.shadowColor = 'rgba(255, 255, 255, .7)';
            context.shadowBlur = 6;
            context.stroke();
            context.restore();
        }
    };

    const updateStar = (now: number) => {
        if (!activeStar) return;
        const star = activeStar;
        const startX = star.xRatio * width;
        const startY = star.yRatio * height;

        if (phase === 'appearing') {
            const duration = 1500 / speed();
            const progress = clamp((now - star.start) / duration, 0, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            star.x = startX;
            star.y = startY;
            star.scale = eased;
            star.opacity = eased;
            star.rotation += 0.35;
            star.trail = [];
            if (progress >= 1) {
                phase = 'falling';
                star.start = now;
            }
        } else if (phase === 'falling') {
            const distance = Math.hypot(planetX - startX, planetY - startY);
            const duration = (1550 + distance * 0.72) / speed();
            const progress = clamp((now - star.start) / duration, 0, 1);
            const eased = progress * progress;
            star.x = startX + (planetX - startX) * eased;
            star.y = startY + (planetY - startY) * eased;
            star.scale = 1 - progress * 0.25;
            star.opacity = 0.9 + Math.sin(now * 0.012) * 0.1;
            star.rotation += 1.3 + progress * 3.2;
            star.trail.unshift({ x: star.x, y: star.y });
            star.trail.length = Math.min(star.trail.length, 30);

            if (insideCatcherMask(star)) {
                catchStar(now);
                return;
            }
            if (Math.hypot(star.x - planetX, star.y - planetY) <= planetRadius * 0.82 || progress >= 1) {
                hitPlanet(now);
                return;
            }
        }

        const flicker = 0.9 + Math.sin(now * 0.01) * 0.1;
        starNode.style.opacity = String(star.opacity);
        starNode.style.filter = `brightness(${flicker}) drop-shadow(0 0 12px rgba(255, 255, 255, .9))`;
        starNode.style.transform = `translate(${star.x}px, ${star.y}px) translate(-50%, -50%) rotate(${star.rotation}deg) scale(${star.scale})`;
    };

    const frame = (now: number) => {
        if (!root.isConnected) {
            window.removeEventListener('resize', resize);
            window.removeEventListener('pointermove', onPointerMove);
            return;
        }
        const delta = Math.min(now - lastFrame, 40);
        lastFrame = now;

        if (phase !== 'lost' && phase !== 'won') {
            catcherAngle += (targetAngle - catcherAngle) * (1 - Math.pow(0.74, delta / 16.67));
            const point = catcherPoint(catcherAngle);
            catcher.style.transform = `translate(${point.x}px, ${point.y}px) translate(-50%, -50%) rotate(${catcherAngle}rad)`;
        }

        if ((phase === 'intro' || phase === 'pause') && now >= phaseUntil) spawnStar(now);
        updateStar(now);
        drawSky(now);
        requestAnimationFrame(frame);
    };

    const onPointerMove = (event: PointerEvent) => moveTarget(event.clientX);
    window.addEventListener('pointermove', onPointerMove, { passive: true });
    stage.addEventListener('pointerdown', (event) => {
        stage.focus({ preventScroll: true });
        moveTarget(event.clientX);
    });
    stage.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        targetAngle = clamp(targetAngle + (event.key === 'ArrowLeft' ? -0.12 : 0.12), -Math.PI / 2, Math.PI / 2);
    });
    retry.addEventListener('click', () => window.location.reload());
    window.addEventListener('resize', resize, { passive: true });

    resize();
    setScore(false);
    starNode.hidden = true;
    requestAnimationFrame(frame);
};
